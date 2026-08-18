<?php

require_once __DIR__ . "/../config/auth.php";

class OrderController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function respond(bool $success, string $message, int $status = 200, array $data = []): void
    {
        http_response_code($status);
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    }

    private function currentUser(): ?array
    {
        return gofastCurrentUser($this->db);
    }

    private function requireRole(array $roles): ?array
    {
        $user = $this->currentUser();
        if (!$user) { $this->respond(false, 'Authentication required', 401); return null; }
        if (!in_array($user['role'], $roles, true)) { $this->respond(false, 'You do not have permission for this action', 403); return null; }
        return $user;
    }

    public function create(): void
    {
        $user = $this->requireRole(['customer']);
        if (!$user) return;
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $required = ['pickup_address','dropoff_address','recipient_name','recipient_phone','package_description'];
        foreach ($required as $field) {
            if (trim((string)($data[$field] ?? '')) === '') { $this->respond(false, ucfirst(str_replace('_',' ',$field)).' is required', 400); return; }
        }
        $tracking = 'GF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $this->db->prepare("INSERT INTO dispatch_orders (tracking_number,customer_user_id,branch,pickup_address,dropoff_address,recipient_name,recipient_phone,package_description,cod_amount,delivery_fee,status,notes) VALUES (:tracking,:customer,:branch,:pickup,:dropoff,:recipient,:phone,:package,:cod,:fee,'pending',:notes)");
        $stmt->execute([
            'tracking'=>$tracking, 'customer'=>$user['id'], 'branch'=>trim($data['branch'] ?? 'Yola') ?: 'Yola',
            'pickup'=>trim($data['pickup_address']), 'dropoff'=>trim($data['dropoff_address']), 'recipient'=>trim($data['recipient_name']),
            'phone'=>trim($data['recipient_phone']), 'package'=>trim($data['package_description']), 'cod'=>max(0,(float)($data['cod_amount'] ?? 0)),
            'fee'=>max(0,(float)($data['delivery_fee'] ?? 0)), 'notes'=>trim($data['notes'] ?? '') ?: null
        ]);
        $orderId=(int)$this->db->lastInsertId();
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,NULL,'pending',:user,'Order created by customer')");
        $h->execute(['order'=>$orderId,'user'=>$user['id']]);
        $this->respond(true,'Delivery order created successfully',201,['order_id'=>$orderId,'tracking_number'=>$tracking,'status'=>'pending']);
    }

    public function customerOrders(): void
    {
        $user=$this->requireRole(['customer']); if(!$user)return;
        $stmt=$this->db->prepare("SELECT o.*, r.full_name rider_name, COALESCE((SELECT p.status FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1),'unpaid') payment_status, (SELECT p.reference FROM payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) payment_reference FROM dispatch_orders o LEFT JOIN users r ON r.id=o.rider_user_id WHERE o.customer_user_id=:user ORDER BY o.created_at DESC");
        $stmt->execute(['user'=>$user['id']]);
        $this->respond(true,'Orders retrieved',200,['orders'=>$stmt->fetchAll()]);
    }

    public function dispatcherOrders(): void
    {
        $user=$this->requireRole(['dispatcher','admin']); if(!$user)return;
        $stmt=$this->db->query("SELECT o.*, c.full_name customer_name, c.phone customer_phone, r.full_name rider_name FROM dispatch_orders o JOIN users c ON c.id=o.customer_user_id LEFT JOIN users r ON r.id=o.rider_user_id ORDER BY FIELD(o.status,'pending','assigned','picked_up','in_transit','failed','returned','delivered'), o.created_at DESC");
        $this->respond(true,'Live orders retrieved',200,['orders'=>$stmt->fetchAll()]);
    }

    public function riderOrders(): void
    {
        $user=$this->requireRole(['rider']); if(!$user)return;
        $stmt=$this->db->prepare("SELECT o.*, c.full_name customer_name, c.phone customer_phone FROM dispatch_orders o JOIN users c ON c.id=o.customer_user_id WHERE o.rider_user_id=:rider OR (o.rider_user_id IS NULL AND o.status='pending') ORDER BY o.created_at DESC");
        $stmt->execute(['rider'=>$user['id']]);
        $this->respond(true,'Rider orders retrieved',200,['orders'=>$stmt->fetchAll()]);
    }

    public function riderAccept(): void
    {
        $user=$this->requireRole(['rider']); if(!$user)return;
        $vr=$this->db->prepare("SELECT verification_status FROM rider_verifications WHERE user_id=:user LIMIT 1");
        $vr->execute(['user'=>$user['id']]);
        if($vr->fetchColumn()!=='approved'){$this->respond(false,'Your rider application must be approved before accepting orders',403);return;}
        $data=json_decode(file_get_contents('php://input'),true)?:[];
        $orderId=(int)($data['order_id']??0);
        if(!$orderId){$this->respond(false,'Order ID is required',400);return;}
        $q=$this->db->prepare("SELECT * FROM dispatch_orders WHERE id=:id LIMIT 1");
        $q->execute(['id'=>$orderId]); $order=$q->fetch();
        if(!$order){$this->respond(false,'Order not found',404);return;}
        if($order['status']!=='pending' || $order['rider_user_id']!==null){$this->respond(false,'This order is no longer available',409);return;}
        $u=$this->db->prepare("UPDATE dispatch_orders SET rider_user_id=:rider,status='assigned' WHERE id=:id AND status='pending' AND rider_user_id IS NULL");
        $u->execute(['rider'=>$user['id'],'id'=>$orderId]);
        if($u->rowCount()!==1){$this->respond(false,'Another rider has already taken this order',409);return;}
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,'pending','assigned',:user,'Rider accepted incoming order')");
        $h->execute(['order'=>$orderId,'user'=>$user['id']]);
        $this->respond(true,'Order accepted successfully',200,['order_id'=>$orderId,'status'=>'assigned']);
    }

    public function assign(): void
    {
        $user=$this->requireRole(['dispatcher','admin']); if(!$user)return;
        $data=json_decode(file_get_contents('php://input'),true)?:[];
        $orderId=(int)($data['order_id']??0); $riderId=(int)($data['rider_user_id']??0);
        if(!$orderId||!$riderId){$this->respond(false,'Order and rider are required',400);return;}
        $r=$this->db->prepare("SELECT id FROM users WHERE id=:id AND role='rider' AND status='active' LIMIT 1"); $r->execute(['id'=>$riderId]); if(!$r->fetch()){$this->respond(false,'Rider not found or inactive',404);return;}
        $o=$this->db->prepare("SELECT status FROM dispatch_orders WHERE id=:id LIMIT 1");$o->execute(['id'=>$orderId]);$order=$o->fetch();if(!$order){$this->respond(false,'Order not found',404);return;}
        $new='assigned'; $u=$this->db->prepare("UPDATE dispatch_orders SET rider_user_id=:rider,status=:status WHERE id=:id");$u->execute(['rider'=>$riderId,'status'=>$new,'id'=>$orderId]);
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,:old,:new,:user,:note)");$h->execute(['order'=>$orderId,'old'=>$order['status'],'new'=>$new,'user'=>$user['id'],'note'=>'Order assigned to rider']);
        $this->respond(true,'Rider assigned successfully');
    }

    public function updateStatus(): void
    {
        $user=$this->requireRole(['rider','dispatcher','admin']); if(!$user)return;
        $data=json_decode(file_get_contents('php://input'),true)?:[];$orderId=(int)($data['order_id']??0);$new=trim($data['status']??'');
        $allowed=['picked_up','in_transit','delivered','failed','returned'];
        if(!$orderId||!in_array($new,$allowed,true)){$this->respond(false,'Valid order and status are required',400);return;}
        $q=$this->db->prepare("SELECT * FROM dispatch_orders WHERE id=:id LIMIT 1");$q->execute(['id'=>$orderId]);$order=$q->fetch();if(!$order){$this->respond(false,'Order not found',404);return;}
        if($user['role']==='rider' && (int)$order['rider_user_id']!==(int)$user['id']){$this->respond(false,'This order is not assigned to you',403);return;}
        if($user['role']==='rider') {
            $vr=$this->db->prepare("SELECT verification_status FROM rider_verifications WHERE user_id=:user LIMIT 1");
            $vr->execute(['user'=>$user['id']]);
            if($vr->fetchColumn()!=='approved'){$this->respond(false,'Your rider account must be approved before you can progress deliveries',403);return;}
        }
        if($new==='delivered' && empty($data['proof_type'])){$this->respond(false,'Delivery proof is required before marking delivered',400);return;}
        $u=$this->db->prepare("UPDATE dispatch_orders SET status=:status,proof_type=:proof WHERE id=:id");$u->execute(['status'=>$new,'proof'=>$new==='delivered'?($data['proof_type']??'none'):$order['proof_type'],'id'=>$orderId]);
        if($new==='delivered' && !empty($order['rider_user_id'])) {
            $exists=$this->db->prepare("SELECT id FROM rider_wallet_transactions WHERE order_id=:order AND type='earning' LIMIT 1");
            $exists->execute(['order'=>$orderId]);
            if(!$exists->fetch()) {
                $earning=round(((float)$order['delivery_fee']) * 0.70, 2);
                if($earning>0) {
                    $wallet=$this->db->prepare("INSERT INTO rider_wallet_transactions (rider_user_id,order_id,type,amount,description) VALUES (:rider,:order,'earning',:amount,'Delivery earning')");
                    $wallet->execute(['rider'=>$order['rider_user_id'],'order'=>$orderId,'amount'=>$earning]);
                }
            }
        }
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,:old,:new,:user,:note)");$h->execute(['order'=>$orderId,'old'=>$order['status'],'new'=>$new,'user'=>$user['id'],'note'=>trim($data['note']??'')?:null]);
        $this->respond(true,'Order status updated',200,['status'=>$new]);
    }
}
