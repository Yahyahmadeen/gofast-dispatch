<?php

require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../config/auth.php';

class FinanceController
{
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    private function respond(bool $ok,string $msg,int $status=200,array $data=[]):void{http_response_code($status);echo json_encode(['success'=>$ok,'message'=>$msg,'data'=>$data]);}
    private function currentUser():?array{ return gofastCurrentUser($this->db); }
    private function requireRole(array $roles):?array{$u=$this->currentUser();if(!$u){$this->respond(false,'Authentication required',401);return null;}if(!in_array($u['role'],$roles,true)){$this->respond(false,'You do not have permission for this action',403);return null;}return $u;}

    public function initializePayment():void{
        $u=$this->requireRole(['customer']);if(!$u)return;
        $d=json_decode(file_get_contents('php://input'),true)?:[];$orderId=(int)($d['order_id']??0);
        $q=$this->db->prepare("SELECT * FROM dispatch_orders WHERE id=:id AND customer_user_id=:user LIMIT 1");$q->execute(['id'=>$orderId,'user'=>$u['id']]);$o=$q->fetch();
        if(!$o){$this->respond(false,'Order not found',404);return;}
        $amount=(float)$o['delivery_fee']+(float)$o['cod_amount'];if($amount<=0){$this->respond(false,'This order has no online payment amount',400);return;}
        $reference='GF-PAY-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
        try{
            $r=paystackRequest('POST','/transaction/initialize',['email'=>$u['email'],'amount'=>(int)round($amount*100),'currency'=>'NGN','reference'=>$reference,'metadata'=>['order_id'=>$orderId,'customer_user_id'=>(int)$u['id']]]);
            $stmt=$this->db->prepare("INSERT INTO payments (order_id,customer_user_id,reference,amount,currency,provider,status,authorization_url) VALUES (:order,:user,:ref,:amount,'NGN','paystack','pending',:url)");
            $stmt->execute(['order'=>$orderId,'user'=>$u['id'],'ref'=>$reference,'amount'=>$amount,'url'=>$r['data']['authorization_url']??null]);
            $this->respond(true,'Payment initialized',200,['reference'=>$reference,'authorization_url'=>$r['data']['authorization_url']??null,'amount'=>$amount]);
        }catch(Throwable $e){$this->respond(false,$e->getMessage(),503);}
    }

    public function verifyPayment():void{
        $u=$this->requireRole(['customer','dispatcher','admin']);if(!$u)return;$ref=trim($_GET['reference']??'');
        if($ref===''){$this->respond(false,'Payment reference is required',400);return;}
        try{
            $r=paystackRequest('GET','/transaction/verify/'.rawurlencode($ref));$data=$r['data']??[];
            $status=($data['status']??'')==='success'?'paid':'failed';
            $stmt=$this->db->prepare("UPDATE payments SET status=:status,paid_at=CASE WHEN :status='paid' THEN NOW() ELSE paid_at END WHERE reference=:ref");$stmt->execute(['status'=>$status,'ref'=>$ref]);
            $this->respond(true,'Payment verification complete',200,['status'=>$status,'reference'=>$ref]);
        }catch(Throwable $e){$this->respond(false,$e->getMessage(),503);}
    }

    public function riderWallet():void{
        $u=$this->requireRole(['rider']);if(!$u)return;
        $q=$this->db->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('earning','adjustment') THEN amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN type='payout' THEN amount ELSE 0 END),0) balance,COALESCE(SUM(CASE WHEN type IN ('earning','adjustment') THEN amount ELSE 0 END),0) earned,COALESCE(SUM(CASE WHEN type='payout' THEN amount ELSE 0 END),0) paid FROM rider_wallet_transactions WHERE rider_user_id=:user");$q->execute(['user'=>$u['id']]);$summary=$q->fetch()?:[];
        $p=$this->db->prepare("SELECT * FROM payout_requests WHERE rider_user_id=:user ORDER BY requested_at DESC LIMIT 20");$p->execute(['user'=>$u['id']]);
        $this->respond(true,'Wallet retrieved',200,['summary'=>$summary,'payouts'=>$p->fetchAll()]);
    }

    public function requestPayout():void{
        $u=$this->requireRole(['rider']);if(!$u)return;$d=json_decode(file_get_contents('php://input'),true)?:[];$amount=(float)($d['amount']??0);
        if($amount<1000){$this->respond(false,'Minimum payout request is ₦1,000',400);return;}
        $v=$this->db->prepare("SELECT verification_status FROM rider_verifications WHERE user_id=:user LIMIT 1");$v->execute(['user'=>$u['id']]);if($v->fetchColumn()!=='approved'){$this->respond(false,'Your rider verification must be approved before requesting a payout',403);return;}
        $b=$this->db->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('earning','adjustment') THEN amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN type='payout' THEN amount ELSE 0 END),0) FROM rider_wallet_transactions WHERE rider_user_id=:user");$b->execute(['user'=>$u['id']]);$balance=(float)$b->fetchColumn();
        if($amount>$balance){$this->respond(false,'Requested amount exceeds your available balance',400);return;}
        $a=$this->db->prepare("SELECT status FROM rider_payout_accounts WHERE rider_user_id=:user LIMIT 1");$a->execute(['user'=>$u['id']]);if(!$a->fetch()){$this->respond(false,'Add a payout bank account first',400);return;}
        $s=$this->db->prepare("INSERT INTO payout_requests (rider_user_id,amount,note) VALUES (:user,:amount,:note)");$s->execute(['user'=>$u['id'],'amount'=>$amount,'note'=>trim($d['note']??'')?:null]);
        $this->respond(true,'Payout request submitted to the dispatcher',201,['payout_request_id'=>(int)$this->db->lastInsertId(),'amount'=>$amount]);
    }

    public function dispatcherPayouts():void{
        $u=$this->requireRole(['dispatcher','admin']);if(!$u)return;
        $q=$this->db->query("SELECT p.*,u.full_name rider_name,u.phone rider_phone,a.bank_name,a.account_name,a.account_number_last4 FROM payout_requests p JOIN users u ON u.id=p.rider_user_id LEFT JOIN rider_payout_accounts a ON a.rider_user_id=p.rider_user_id ORDER BY FIELD(p.status,'pending','approved','paid','rejected'),p.requested_at ASC");
        $this->respond(true,'Payout queue retrieved',200,['payouts'=>$q->fetchAll()]);
    }

    public function processPayout():void{
        $u=$this->requireRole(['dispatcher']);if(!$u)return;$d=json_decode(file_get_contents('php://input'),true)?:[];$id=(int)($d['payout_request_id']??0);$action=$d['action']??'paid';
        $q=$this->db->prepare("SELECT * FROM payout_requests WHERE id=:id LIMIT 1");$q->execute(['id'=>$id]);$p=$q->fetch();if(!$p){$this->respond(false,'Payout request not found',404);return;}
        if($p['status']!=='pending' && $action!=='reject'){$this->respond(false,'This payout has already been processed',409);return;}
        $new=$action==='reject'?'rejected':($action==='approve'?'approved':'paid');
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare("UPDATE payout_requests SET status=:status,processed_by=:user,processed_at=NOW(),payment_reference=:ref,note=:note WHERE id=:id");$s->execute(['status'=>$new,'user'=>$u['id'],'ref'=>$new==='paid'?('GF-PAYOUT-'.date('YmdHis').'-'.$id):null,'note'=>trim($d['note']??'')?:$p['note'],'id'=>$id]);
            if($new==='paid'){
                $w=$this->db->prepare("INSERT INTO rider_wallet_transactions (rider_user_id,payout_request_id,type,amount,description) VALUES (:rider,:payout,'payout',:amount,'Dispatcher payout')");$w->execute(['rider'=>$p['rider_user_id'],'payout'=>$id,'amount'=>$p['amount']]);
            }
            $this->db->commit();$this->respond(true,'Payout '.$new.' successfully');
        }catch(Throwable $e){$this->db->rollBack();$this->respond(false,'Unable to process payout',500);}
    }
}
