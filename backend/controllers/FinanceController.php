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
        $amount=(float)$o['delivery_fee'];if($amount<=0){$this->respond(false,'A valid delivery fee is required before payment',400);return;}
        if(($o['payment_status'] ?? 'pending')==='paid'){$this->respond(true,'Order is already paid',200,['order_id'=>$orderId,'status'=>'paid']);return;}
        // Reuse an existing pending checkout for this order when possible.
        $existing=$this->db->prepare("SELECT reference,authorization_url,amount FROM payments WHERE order_id=:order AND customer_user_id=:user AND status='pending' ORDER BY id DESC LIMIT 1");
        $existing->execute(['order'=>$orderId,'user'=>$u['id']]);
        $pending=$existing->fetch();
        if($pending && !empty($pending['authorization_url'])){
            $this->respond(true,'Payment already initialized',200,[
                'reference'=>$pending['reference'],
                'authorization_url'=>$pending['authorization_url'],
                'amount'=>(float)$pending['amount'],
            ]);
            return;
        }

        $reference='GF-PAY-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
        try{
            $callback=rtrim(gofastEnv('GOFAST_APP_URL','http://localhost:5174'),'/').'/customer/payment?order_id='.$orderId;
            $r=paystackRequest('POST','/transaction/initialize',[
                'email'=>$u['email'],
                'amount'=>(int)round($amount*100),
                'currency'=>'NGN',
                'reference'=>$reference,
                'callback_url'=>$callback,
                'channels'=>['card','bank','ussd','qr','mobile_money','bank_transfer'],
                'metadata'=>['order_id'=>$orderId,'customer_user_id'=>(int)$u['id']],
            ]);
            $authorizationUrl=$r['data']['authorization_url']??null;
            if(!$authorizationUrl){
                throw new RuntimeException('Paystack did not return a checkout URL');
            }
            $stmt=$this->db->prepare("INSERT INTO payments (order_id,customer_user_id,reference,amount,currency,provider,status,authorization_url) VALUES (:order,:user,:ref,:amount,'NGN','paystack','pending',:url)");
            $stmt->execute(['order'=>$orderId,'user'=>$u['id'],'ref'=>$reference,'amount'=>$amount,'url'=>$authorizationUrl]);
            $this->respond(true,'Payment initialized',200,[
                'reference'=>$reference,
                'authorization_url'=>$authorizationUrl,
                'amount'=>$amount,
            ]);
        }catch(Throwable $e){$this->respond(false,$e->getMessage(),503);}
    }

    public function verifyPayment():void{
        $u=$this->requireRole(['customer','dispatcher','admin']);if(!$u)return;$ref=trim($_GET['reference']??'');
        if($ref===''){$this->respond(false,'Payment reference is required',400);return;}
        try{
            $r=paystackRequest('GET','/transaction/verify/'.rawurlencode($ref));$data=$r['data']??[];
            $payment=$this->db->prepare("SELECT * FROM payments WHERE reference=:ref LIMIT 1");$payment->execute(['ref'=>$ref]);$paymentRow=$payment->fetch();
            if(!$paymentRow){$this->respond(false,'Payment record not found',404);return;}
            if($u['role']==='customer' && (int)$paymentRow['customer_user_id']!==(int)$u['id']){$this->respond(false,'You do not have permission to verify this payment',403);return;}
            $expected=(float)$paymentRow['amount']; $received=((float)($data['amount']??0))/100;
            if(abs($expected-$received)>0.01){$this->respond(false,'Payment amount does not match the order amount',409);return;}
            $status=($data['status']??'')==='success'?'paid':'failed';
            $this->db->beginTransaction();
            $stmt=$this->db->prepare("UPDATE payments SET status=:status,paid_at=CASE WHEN :status='paid' THEN NOW() ELSE paid_at END WHERE reference=:ref");$stmt->execute(['status'=>$status,'ref'=>$ref]);
            if($status==='paid'){
                $orderUpdate=$this->db->prepare("UPDATE dispatch_orders SET payment_status='paid' WHERE id=:order AND customer_user_id=:user");
                $orderUpdate->execute(['order'=>$paymentRow['order_id'],'user'=>$paymentRow['customer_user_id']]);
            }
            $this->db->commit();
            $this->respond(true,'Payment verification complete',200,['status'=>$status,'reference'=>$ref,'order_id'=>(int)$paymentRow['order_id']]);
        }catch(Throwable $e){$this->respond(false,$e->getMessage(),503);}
    }

    private function availableBalance(int $riderId): float
    {
        $q=$this->db->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('earning','adjustment') THEN amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN type='payout' THEN amount ELSE 0 END),0) FROM rider_wallet_transactions WHERE rider_user_id=:user");
        $q->execute(['user'=>$riderId]);
        $wallet=(float)$q->fetchColumn();
        $reserved=$this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE rider_user_id=:user AND status IN ('pending','approved')");
        $reserved->execute(['user'=>$riderId]);
        return max(0, $wallet-(float)$reserved->fetchColumn());
    }

    public function riderWallet():void{
        $u=$this->requireRole(['rider']);if(!$u)return;
        $q=$this->db->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('earning','adjustment') THEN amount ELSE 0 END),0) earned,COALESCE(SUM(CASE WHEN type='payout' THEN amount ELSE 0 END),0) paid FROM rider_wallet_transactions WHERE rider_user_id=:user");$q->execute(['user'=>$u['id']]);$summary=$q->fetch()?:[];
        $summary['balance']=$this->availableBalance((int)$u['id']);
        $r=$this->db->prepare("SELECT id,bank_name,account_name,account_number_last4,status,created_at,updated_at FROM rider_payout_accounts WHERE rider_user_id=:user LIMIT 1");$r->execute(['user'=>$u['id']]);
        $account=$r->fetch()?:null;
        $p=$this->db->prepare("SELECT * FROM payout_requests WHERE rider_user_id=:user ORDER BY requested_at DESC LIMIT 30");$p->execute(['user'=>$u['id']]);
        $this->respond(true,'Wallet retrieved',200,['summary'=>$summary,'account'=>$account,'payouts'=>$p->fetchAll()]);
    }

    public function savePayoutAccount():void{
        $u=$this->requireRole(['rider']);if(!$u)return;
        $d=json_decode(file_get_contents('php://input'),true)?:[];
        $bank=trim((string)($d['bank_name']??''));$name=trim((string)($d['account_name']??''));$number=preg_replace('/\D+/','',(string)($d['account_number']??''));
        if($bank===''||$name===''||strlen($number)!==10){$this->respond(false,'Bank name, account name and a valid 10-digit account number are required',400);return;}
        $key=gofastEnv('GOFAST_ENCRYPTION_KEY');
        if(!$key||str_contains($key,'CHANGE_ME')){$this->respond(false,'GOFAST_ENCRYPTION_KEY is not configured on the backend',500);return;}
        try{
            $iv=random_bytes(16);$cipher=base64_encode($iv . openssl_encrypt($number,'AES-256-CBC',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv));
            $q=$this->db->prepare("INSERT INTO rider_payout_accounts (rider_user_id,bank_name,account_name,account_number_last4,account_number_encrypted,status) VALUES (:user,:bank,:name,:last4,:encrypted,'pending') ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name),account_name=VALUES(account_name),account_number_last4=VALUES(account_number_last4),account_number_encrypted=VALUES(account_number_encrypted),status='pending'");
            $q->execute(['user'=>$u['id'],'bank'=>$bank,'name'=>$name,'last4'=>substr($number,-4),'encrypted'=>$cipher]);
            $this->respond(true,'Payout account saved and sent for verification',200,['bank_name'=>$bank,'account_name'=>$name,'account_number_last4'=>substr($number,-4),'status'=>'pending']);
        }catch(Throwable $e){$this->respond(false,'Unable to save payout account',500);}
    }

    public function requestPayout():void{
        $u=$this->requireRole(['rider']);if(!$u)return;$d=json_decode(file_get_contents('php://input'),true)?:[];$amount=round((float)($d['amount']??0),2);
        if($amount<1000){$this->respond(false,'Minimum payout request is ₦1,000',400);return;}
        $v=$this->db->prepare("SELECT verification_status FROM rider_verifications WHERE user_id=:user LIMIT 1");$v->execute(['user'=>$u['id']]);if($v->fetchColumn()!=='approved'){$this->respond(false,'Your rider verification must be approved before requesting a payout',403);return;}
        $a=$this->db->prepare("SELECT status FROM rider_payout_accounts WHERE rider_user_id=:user LIMIT 1");$a->execute(['user'=>$u['id']]);$account=$a->fetch();if(!$account){$this->respond(false,'Add a payout bank account first',400);return;}
        $balance=$this->availableBalance((int)$u['id']);
        if($amount>$balance){$this->respond(false,'Requested amount exceeds your available balance of ₦'.number_format($balance,2),400);return;}
        $s=$this->db->prepare("INSERT INTO payout_requests (rider_user_id,amount,note) VALUES (:user,:amount,:note)");$s->execute(['user'=>$u['id'],'amount'=>$amount,'note'=>trim($d['note']??'')?:null]);
        $this->respond(true,'Payout request submitted to the dispatcher',201,['payout_request_id'=>(int)$this->db->lastInsertId(),'amount'=>$amount]);
    }

    public function dispatcherPayouts():void{
        $u=$this->requireRole(['dispatcher','admin']);if(!$u)return;
        $q=$this->db->query("SELECT p.*,u.full_name rider_name,u.phone rider_phone,a.bank_name,a.account_name,a.account_number_last4,a.status account_status FROM payout_requests p JOIN users u ON u.id=p.rider_user_id LEFT JOIN rider_payout_accounts a ON a.rider_user_id=p.rider_user_id ORDER BY FIELD(p.status,'pending','approved','paid','rejected'),p.requested_at ASC");
        $stats=$this->db->query("SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) pending_amount,COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) paid_amount FROM payout_requests")->fetch()?:[];
        $this->respond(true,'Payout queue retrieved',200,['payouts'=>$q->fetchAll(),'stats'=>$stats]);
    }

    public function processPayout():void{
        $u=$this->requireRole(['dispatcher']);if(!$u)return;$d=json_decode(file_get_contents('php://input'),true)?:[];$id=(int)($d['payout_request_id']??0);$action=$d['action']??'paid';
        if(!in_array($action,['approve','paid','reject'],true)){$this->respond(false,'Invalid payout action',400);return;}
        $q=$this->db->prepare("SELECT p.*,a.status account_status FROM payout_requests p LEFT JOIN rider_payout_accounts a ON a.rider_user_id=p.rider_user_id WHERE p.id=:id LIMIT 1");$q->execute(['id'=>$id]);$p=$q->fetch();if(!$p){$this->respond(false,'Payout request not found',404);return;}
        if($action==='approve' && $p['status']!=='pending'){$this->respond(false,'Only pending payouts can be approved',409);return;}
        if($action==='reject' && !in_array($p['status'],['pending','approved'],true)){$this->respond(false,'This payout has already been completed',409);return;}
        if($action==='paid' && !in_array($p['status'],['pending','approved'],true)){$this->respond(false,'This payout has already been processed',409);return;}
        if($action==='paid' && empty($p['bank_name'])){$this->respond(false,'Rider has no payout bank account',400);return;}
        $new=$action==='reject'?'rejected':($action==='approve'?'approved':'paid');
        $this->db->beginTransaction();
        try{
            if($new==='paid'){
                $balance=$this->availableBalance((int)$p['rider_user_id'])+(float)$p['amount'];
                // Add the current request back because it is being consumed now.
                if((float)$p['amount']>$balance){throw new RuntimeException('Rider has insufficient available balance');}
                $ref='GF-PAYOUT-'.date('YmdHis').'-'.$id.'-'.strtoupper(bin2hex(random_bytes(2)));
                $s=$this->db->prepare("UPDATE payout_requests SET status='paid',processed_by=:user,processed_at=NOW(),payment_reference=:ref,note=:note WHERE id=:id AND status IN ('pending','approved')");$s->execute(['user'=>$u['id'],'ref'=>$ref,'note'=>trim($d['note']??'')?:$p['note'],'id'=>$id]);
                if($s->rowCount()!==1)throw new RuntimeException('Payout was changed by another user');
                $w=$this->db->prepare("INSERT INTO rider_wallet_transactions (rider_user_id,payout_request_id,type,amount,description) VALUES (:rider,:payout,'payout',:amount,'Dispatcher payout')");$w->execute(['rider'=>$p['rider_user_id'],'payout'=>$id,'amount'=>$p['amount']]);
            } else {
                $s=$this->db->prepare("UPDATE payout_requests SET status=:status,processed_by=:user,processed_at=NOW(),note=:note WHERE id=:id");$s->execute(['status'=>$new,'user'=>$u['id'],'note'=>trim($d['note']??'')?:$p['note'],'id'=>$id]);
            }
            $this->db->commit();$this->respond(true,'Payout '.$new.' successfully',200,['status'=>$new]);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();$this->respond(false,$e->getMessage(),500);}
    }

    public function adminPayouts():void{
        $u=$this->requireRole(['admin']);if(!$u)return;
        $q=$this->db->query("SELECT p.*,u.full_name rider_name,a.bank_name,a.account_name,a.account_number_last4,d.full_name processed_by_name FROM payout_requests p JOIN users u ON u.id=p.rider_user_id LEFT JOIN rider_payout_accounts a ON a.rider_user_id=p.rider_user_id LEFT JOIN users d ON d.id=p.processed_by ORDER BY p.requested_at DESC");
        $stats=$this->db->query("SELECT COUNT(*) total,COALESCE(SUM(amount),0) requested,COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) pending,COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) paid FROM payout_requests")->fetch()?:[];
        $this->respond(true,'Admin payout report retrieved',200,['payouts'=>$q->fetchAll(),'stats'=>$stats]);
    }

}
