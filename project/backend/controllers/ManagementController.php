<?php
require_once __DIR__ . '/../config/auth.php';

class ManagementController
{
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    private function respond(bool $ok,string $msg,int $status=200,array $data=[]):void{http_response_code($status);echo json_encode(['success'=>$ok,'message'=>$msg,'data'=>$data]);}
    private function admin():?array{
        $u=gofastCurrentUser($this->db);
        if(!$u){$this->respond(false,'Authentication required',401);return null;}
        if($u['role']!=='admin'){$this->respond(false,'Admin access required',403);return null;}
        return $u;
    }

    public function riderApplications():void{
        $u=$this->admin();if(!$u)return;
        try{
            $q=$this->db->query("SELECT u.id,u.full_name,u.email,u.phone,u.created_at,
                COALESCE(v.verification_status,'pending') AS verification_status,
                v.nin_last4,v.bvn_last4,v.vehicle_type,v.vehicle_number,v.rejection_reason,
                a.bank_name,a.account_name,a.account_number_last4,a.status AS payout_account_status
                FROM users u
                LEFT JOIN rider_verifications v ON v.user_id=u.id
                LEFT JOIN rider_payout_accounts a ON a.rider_user_id=u.id
                WHERE u.role='rider'
                ORDER BY FIELD(COALESCE(v.verification_status,'pending'),'pending','approved','rejected','suspended'),u.created_at DESC");
            $rows=$q->fetchAll();
            $this->respond(true,'Rider applications retrieved',200,[
                'riders'=>$rows,
                'pending_count'=>count(array_filter($rows,fn($r)=>$r['verification_status']==='pending'))
            ]);
        }catch(Throwable $e){
            error_log('GOFAST rider applications: '.$e->getMessage());
            $this->respond(false,'Unable to load rider verification data: '.$e->getMessage(),500);
        }
    }

    public function reviewRider():void{
        $u=$this->admin();if(!$u)return;
        $d=json_decode(file_get_contents('php://input'),true)?:[];
        $rider=(int)($d['rider_user_id']??0);$status=$d['status']??'';$reason=trim($d['reason']??'');
        if(!$rider||!in_array($status,['approved','rejected','suspended'],true)){$this->respond(false,'Valid rider and review status are required',400);return;}
        try{
            $exists=$this->db->prepare("SELECT id FROM users WHERE id=:id AND role='rider' LIMIT 1");
            $exists->execute(['id'=>$rider]);
            if(!$exists->fetch()){$this->respond(false,'Rider account was not found',404);return;}

            $q=$this->db->prepare("INSERT INTO rider_verifications (user_id,verification_status,rejection_reason,reviewed_by,reviewed_at)
                VALUES (:rider,:status,:reason,:admin,NOW())
                ON DUPLICATE KEY UPDATE verification_status=VALUES(verification_status),rejection_reason=VALUES(rejection_reason),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at)");
            $q->execute(['status'=>$status,'reason'=>$reason?:null,'admin'=>$u['id'],'rider'=>$rider]);

            // Approving the rider also verifies the payout account that was submitted during onboarding.
            try {
                $pa=$this->db->prepare("UPDATE rider_payout_accounts SET status=CASE WHEN :status='approved' THEN 'verified' WHEN :status IN ('rejected','suspended') THEN 'disabled' ELSE status END WHERE rider_user_id=:rider");
                $pa->execute(['status'=>$status,'rider'=>$rider]);
            } catch(Throwable $ignored) {}

            // Keep the main rider record in sync when it exists in the legacy table.
            try {
                $r=$this->db->prepare("UPDATE riders SET verification_status=:status,approved_by=CASE WHEN :status='approved' THEN :admin ELSE approved_by END,approved_at=CASE WHEN :status='approved' THEN NOW() ELSE approved_at END WHERE user_id=:rider");
                $r->execute(['status'=>$status,'admin'=>$u['id'],'rider'=>$rider]);
            } catch(Throwable $ignored) {}

            $this->respond(true,$status==='approved'?'Rider approved successfully':($status==='rejected'?'Rider application rejected':'Rider suspended'));
        }catch(Throwable $e){
            error_log('GOFAST rider review: '.$e->getMessage());
            $this->respond(false,'Unable to update rider verification: '.$e->getMessage(),500);
        }
    }

    public function users():void{
        $u=$this->admin();if(!$u)return;
        try{
            $q=$this->db->query("SELECT id,full_name,email,phone,role,status,email_verified,created_at FROM users ORDER BY created_at DESC");
            $users=$q->fetchAll();
            $this->respond(true,'Users retrieved',200,['users'=>$users,'counts'=>[
                'customers'=>count(array_filter($users,fn($x)=>$x['role']==='customer')),
                'riders'=>count(array_filter($users,fn($x)=>$x['role']==='rider')),
                'dispatchers'=>count(array_filter($users,fn($x)=>$x['role']==='dispatcher')),
                'admins'=>count(array_filter($users,fn($x)=>$x['role']==='admin')),
            ]]);
        }catch(Throwable $e){$this->respond(false,'Unable to load users: '.$e->getMessage(),500);}
    }
}
