<?php

require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/paystack.php";

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
        $required = ['pickup_address','dropoff_address','recipient_name','recipient_phone','package_description','delivery_fee'];
        foreach ($required as $field) {
            if (trim((string)($data[$field] ?? '')) === '') { $this->respond(false, ucfirst(str_replace('_',' ',$field)).' is required', 400); return; }
        }
        $deliveryFee = (float)($data['delivery_fee'] ?? 0);
        if ($deliveryFee <= 0) { $this->respond(false, 'A delivery fee greater than ₦0 is required because GOFAST is online-payment only.', 400); return; }
        $tracking = 'GF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $this->db->prepare("INSERT INTO dispatch_orders (tracking_number,customer_user_id,branch,pickup_address,dropoff_address,recipient_name,recipient_phone,package_description,cod_amount,delivery_fee,payment_status,status,notes) VALUES (:tracking,:customer,:branch,:pickup,:dropoff,:recipient,:phone,:package,0,:fee,'pending','pending',:notes)");
        $stmt->execute([
            'tracking'=>$tracking, 'customer'=>$user['id'], 'branch'=>trim($data['branch'] ?? 'Yola') ?: 'Yola',
            'pickup'=>trim($data['pickup_address']), 'dropoff'=>trim($data['dropoff_address']), 'recipient'=>trim($data['recipient_name']),
            'phone'=>trim($data['recipient_phone']), 'package'=>trim($data['package_description']),
            'fee'=>$deliveryFee, 'notes'=>trim($data['notes'] ?? '') ?: null
        ]);
        $orderId=(int)$this->db->lastInsertId();
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,NULL,'pending',:user,'Order created by customer')");
        $h->execute(['order'=>$orderId,'user'=>$user['id']]);
        $this->respond(true,'Delivery created. Payment is required before dispatch.',201,['order_id'=>$orderId,'tracking_number'=>$tracking,'status'=>'pending','payment_status'=>'pending','payment_required'=>true]);
    }

    public function customerOrders(): void
    {
        $user=$this->requireRole(['customer']); if(!$user)return;
        $stmt=$this->db->prepare("SELECT o.*, r.full_name rider_name FROM dispatch_orders o LEFT JOIN users r ON r.id=o.rider_user_id WHERE o.customer_user_id=:user ORDER BY o.created_at DESC");
        $stmt->execute(['user'=>$user['id']]);
        $this->respond(true,'Orders retrieved',200,['orders'=>$stmt->fetchAll()]);
    }

    public function dispatcherOrders(): void
    {
        $user=$this->requireRole(['dispatcher','admin']); if(!$user)return;
        $stmt=$this->db->query("SELECT o.*, c.full_name customer_name, c.phone customer_phone, r.full_name rider_name FROM dispatch_orders o JOIN users c ON c.id=o.customer_user_id LEFT JOIN users r ON r.id=o.rider_user_id WHERE o.payment_status='paid' ORDER BY FIELD(o.status,'pending','assigned','picked_up','in_transit','failed','returned','delivered'), o.created_at DESC");
        $this->respond(true,'Live orders retrieved',200,['orders'=>$stmt->fetchAll()]);
    }

    public function riderOrders(): void
    {
        $user=$this->requireRole(['rider']); if(!$user)return;
        $stmt=$this->db->prepare("SELECT o.*, c.full_name customer_name, c.phone customer_phone FROM dispatch_orders o JOIN users c ON c.id=o.customer_user_id WHERE o.payment_status='paid' AND (o.rider_user_id=:rider OR (o.rider_user_id IS NULL AND o.status='pending')) ORDER BY o.created_at DESC");
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
        if(($order['payment_status'] ?? 'pending')!=='paid'){$this->respond(false,'This order must be paid before a rider can accept it',409);return;}
        if($order['status']!=='pending' || $order['rider_user_id']!==null){$this->respond(false,'This order is no longer available',409);return;}
        $u=$this->db->prepare("UPDATE dispatch_orders SET rider_user_id=:rider,status='assigned' WHERE id=:id AND status='pending' AND rider_user_id IS NULL");
        $u->execute(['rider'=>$user['id'],'id'=>$orderId]);
        if($u->rowCount()!==1){$this->respond(false,'Another rider has already taken this order',409);return;}
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,'pending','assigned',:user,'Rider accepted incoming order')");
        $h->execute(['order'=>$orderId,'user'=>$user['id']]);
        $this->respond(true,'Order accepted successfully',200,['order_id'=>$orderId,'status'=>'assigned']);
    }

    public function availableRiders(): void
    {
        $user=$this->requireRole(['dispatcher','admin']); if(!$user)return;
        try {
            $q=$this->db->query("SELECT u.id AS user_id, u.full_name, u.phone,
                r.vehicle_type, r.vehicle_number, r.availability,
                COALESCE(v.verification_status, r.verification_status) AS verification_status
                FROM users u
                INNER JOIN riders r ON r.user_id=u.id
                LEFT JOIN rider_verifications v ON v.user_id=u.id
                WHERE u.role='rider'
                  AND u.status='active'
                  AND r.availability='available'
                  AND COALESCE(v.verification_status, r.verification_status)='approved'
                ORDER BY u.full_name ASC");
            $riders=$q->fetchAll();
            $this->respond(true,'Available riders retrieved',200,[
                'riders'=>$riders,
                'count'=>count($riders)
            ]);
        } catch(Throwable $e) {
            error_log('GOFAST available riders: '.$e->getMessage());
            $this->respond(false,'Unable to load available riders: '.$e->getMessage(),500);
        }
    }

    public function assign(): void
    {
        $user=$this->requireRole(['dispatcher','admin']); if(!$user)return;
        $data=json_decode(file_get_contents('php://input'),true)?:[];
        $orderId=(int)($data['order_id']??0); $riderId=(int)($data['rider_user_id']??0);
        if(!$orderId||!$riderId){$this->respond(false,'Order and rider are required',400);return;}
        try {
            $r=$this->db->prepare("SELECT u.id FROM users u INNER JOIN riders r ON r.user_id=u.id LEFT JOIN rider_verifications v ON v.user_id=u.id WHERE u.id=:id AND u.role='rider' AND u.status='active' AND r.availability='available' AND COALESCE(v.verification_status,r.verification_status)='approved' LIMIT 1");
            $r->execute(['id'=>$riderId]);
            if(!$r->fetch()){$this->respond(false,'Rider is not active, approved, or available',404);return;}
            $o=$this->db->prepare("SELECT status,payment_status FROM dispatch_orders WHERE id=:id LIMIT 1");
            $o->execute(['id'=>$orderId]); $order=$o->fetch();
            if(!$order){$this->respond(false,'Order not found',404);return;}
            if(($order['payment_status'] ?? 'pending')!=='paid'){$this->respond(false,'Order must be paid before rider assignment',409);return;}
            if($order['status']!=='pending'){$this->respond(false,'This order is no longer waiting for rider assignment',409);return;}

            $this->db->beginTransaction();
            // Re-check availability inside the transaction to avoid assigning the same rider twice.
            $lock=$this->db->prepare("SELECT id FROM riders WHERE user_id=:rider AND availability='available' FOR UPDATE");
            $lock->execute(['rider'=>$riderId]);
            if(!$lock->fetch()) { $this->db->rollBack(); $this->respond(false,'That rider is no longer available. Please choose another rider.',409); return; }

            $u=$this->db->prepare("UPDATE dispatch_orders SET rider_user_id=:rider,status='assigned' WHERE id=:id AND status='pending' AND rider_user_id IS NULL");
            $u->execute(['rider'=>$riderId,'id'=>$orderId]);
            if($u->rowCount()!==1){$this->db->rollBack();$this->respond(false,'This order was already assigned. Refresh and try again.',409);return;}

            $ra=$this->db->prepare("UPDATE riders SET availability='on_delivery' WHERE user_id=:rider");
            $ra->execute(['rider'=>$riderId]);
            $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,:old,'assigned',:user,:note)");
            $h->execute(['order'=>$orderId,'old'=>$order['status'],'user'=>$user['id'],'note'=>'Order assigned to rider']);
            $this->db->commit();
            $this->respond(true,'Rider assigned successfully',200,['order_id'=>$orderId,'rider_user_id'=>$riderId,'status'=>'assigned']);
        } catch(Throwable $e) {
            if($this->db->inTransaction()) $this->db->rollBack();
            error_log('GOFAST assign rider: '.$e->getMessage());
            $this->respond(false,'Unable to assign rider: '.$e->getMessage(),500);
        }
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
                $rate=(float)gofastEnv('RIDER_EARNING_RATE','0.70'); if($rate<=0||$rate>1)$rate=0.70;
                $earning=round(((float)$order['delivery_fee']) * $rate, 2);
                if($earning>0) {
                    $wallet=$this->db->prepare("INSERT INTO rider_wallet_transactions (rider_user_id,order_id,type,amount,description) VALUES (:rider,:order,'earning',:amount,'Delivery earning')");
                    $wallet->execute(['rider'=>$order['rider_user_id'],'order'=>$orderId,'amount'=>$earning]);
                }
            }
        }
        if(in_array($new,['delivered','failed','returned'],true) && !empty($order['rider_user_id'])) {
            $ra=$this->db->prepare("UPDATE riders SET availability='available' WHERE user_id=:rider");
            $ra->execute(['rider'=>$order['rider_user_id']]);
        }
        $h=$this->db->prepare("INSERT INTO dispatch_order_status_history(order_id,old_status,new_status,changed_by_user_id,note) VALUES(:order,:old,:new,:user,:note)");$h->execute(['order'=>$orderId,'old'=>$order['status'],'new'=>$new,'user'=>$user['id'],'note'=>trim($data['note']??'')?:null]);
        $this->respond(true,'Order status updated',200,['status'=>$new]);
    }
}
