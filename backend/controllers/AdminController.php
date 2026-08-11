<?php

class AdminController
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
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($headers['Authorization'] ?? ($headers['authorization'] ?? ''));
        if (!$header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if (!preg_match('/Bearer\s+(\S+)/i', $header, $m)) return null;
        $hash = hash('sha256', $m[1]);
        $stmt = $this->db->prepare("SELECT u.id,u.full_name,u.email,u.phone,u.role,u.status FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=:hash AND s.expires_at > NOW() AND u.status='active' LIMIT 1");
        $stmt->execute(['hash' => $hash]);
        return $stmt->fetch() ?: null;
    }

    private function admin(): ?array
    {
        $user = $this->currentUser();
        if (!$user) { $this->respond(false, 'Authentication required', 401); return null; }
        if ($user['role'] !== 'admin') { $this->respond(false, 'Administrator access required', 403); return null; }
        return $user;
    }

    public function dashboard(): void
    {
        if (!$this->admin()) return;
        $stats = $this->db->query("SELECT
            (SELECT COUNT(*) FROM users) total_users,
            (SELECT COUNT(*) FROM users WHERE role='customer' AND status='active') active_customers,
            (SELECT COUNT(*) FROM users WHERE role='rider' AND status='active') active_riders,
            (SELECT COUNT(*) FROM users WHERE role='dispatcher' AND status='active') active_dispatchers,
            (SELECT COUNT(*) FROM dispatch_orders WHERE DATE(created_at)=CURDATE()) orders_today,
            (SELECT COUNT(*) FROM dispatch_orders WHERE status='delivered' AND DATE(updated_at)=CURDATE()) delivered_today,
            (SELECT COUNT(*) FROM dispatch_orders WHERE status IN ('pending','assigned','picked_up','in_transit')) live_orders,
            (SELECT COALESCE(SUM(delivery_fee),0) FROM dispatch_orders WHERE DATE(created_at)=CURDATE()) revenue_today,
            (SELECT COUNT(*) FROM users WHERE role='rider' AND status='pending') pending_riders")->fetch();

        $recent = $this->db->query("SELECT o.id,o.tracking_number,o.status,o.delivery_fee,o.created_at,c.full_name customer_name,r.full_name rider_name FROM dispatch_orders o JOIN users c ON c.id=o.customer_user_id LEFT JOIN users r ON r.id=o.rider_user_id ORDER BY o.created_at DESC LIMIT 8")->fetchAll();
        $riders = $this->db->query("SELECT u.id,u.full_name,u.phone,u.status,COALESCE(r.availability,'off_duty') availability,COALESCE(r.verification_status,'pending') verification_status FROM users u LEFT JOIN riders r ON r.user_id=u.id WHERE u.role='rider' ORDER BY u.created_at DESC LIMIT 8")->fetchAll();
        $this->respond(true, 'Admin dashboard loaded', 200, ['stats'=>$stats,'recent_orders'=>$recent,'riders'=>$riders]);
    }

    public function users(): void
    {
        if (!$this->admin()) return;
        $role = trim($_GET['role'] ?? '');
        $sql = "SELECT id,full_name,email,phone,role,status,created_at FROM users";
        $params = [];
        if (in_array($role, ['customer','rider','dispatcher','admin'], true)) { $sql .= " WHERE role=:role"; $params['role']=$role; }
        $sql .= " ORDER BY created_at DESC";
        $stmt=$this->db->prepare($sql); $stmt->execute($params);
        $this->respond(true,'Users retrieved',200,['users'=>$stmt->fetchAll()]);
    }

    public function updateUserStatus(): void
    {
        $admin=$this->admin(); if (!$admin) return;
        $data=json_decode(file_get_contents('php://input'),true)?:[];
        $id=(int)($data['user_id']??0); $status=trim($data['status']??'');
        if(!$id || !in_array($status,['active','pending','suspended','inactive'],true)){ $this->respond(false,'Valid user and status are required',400); return; }
        if($id===(int)$admin['id'] && $status!=='active'){ $this->respond(false,'You cannot suspend your own administrator account',400); return; }
        $stmt=$this->db->prepare("UPDATE users SET status=:status WHERE id=:id"); $stmt->execute(['status'=>$status,'id'=>$id]);
        $this->respond(true,$stmt->rowCount()?'User status updated':'No changes made');
    }

    public function riders(): void
    {
        if (!$this->admin()) return;
        $stmt=$this->db->query("SELECT u.id,u.full_name,u.email,u.phone,u.status,COALESCE(r.id,0) rider_id,COALESCE(r.id_number,'') id_number,COALESCE(r.vehicle_type,'Not supplied') vehicle_type,COALESCE(r.vehicle_number,'Not supplied') vehicle_number,COALESCE(r.verification_status,'pending') verification_status,COALESCE(r.availability,'off_duty') availability FROM users u LEFT JOIN riders r ON r.user_id=u.id WHERE u.role='rider' ORDER BY CASE COALESCE(r.verification_status,'pending') WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,u.created_at DESC");
        $this->respond(true,'Riders retrieved',200,['riders'=>$stmt->fetchAll()]);
    }

    public function verifyRider(): void
    {
        $admin=$this->admin(); if(!$admin)return;
        $data=json_decode(file_get_contents('php://input'),true)?:[]; $userId=(int)($data['user_id']??0); $status=trim($data['verification_status']??'');
        if(!$userId || !in_array($status,['pending','approved','rejected','suspended'],true)){ $this->respond(false,'Valid rider and verification status are required',400); return; }
        $check=$this->db->prepare("SELECT id FROM users WHERE id=:id AND role='rider' LIMIT 1"); $check->execute(['id'=>$userId]); if(!$check->fetch()){ $this->respond(false,'Rider not found',404); return; }
        $stmt=$this->db->prepare("INSERT INTO riders (user_id,verification_status,approved_by,approved_at) VALUES (:user,:status,:admin,CASE WHEN :status2='approved' THEN NOW() ELSE NULL END) ON DUPLICATE KEY UPDATE verification_status=VALUES(verification_status),approved_by=VALUES(approved_by),approved_at=VALUES(approved_at)");
        $stmt->execute(['user'=>$userId,'status'=>$status,'admin'=>$admin['id'],'status2'=>$status]);
        $userStatus=$status==='approved'?'active':($status==='suspended'?'suspended':'pending');
        $u=$this->db->prepare("UPDATE users SET status=:status WHERE id=:id"); $u->execute(['status'=>$userStatus,'id'=>$userId]);
        $this->respond(true,'Rider verification updated');
    }

    public function reports(): void
    {
        if (!$this->admin()) return;
        $rows=$this->db->query("SELECT DATE(created_at) day, COUNT(*) orders, COALESCE(SUM(delivery_fee),0) revenue, SUM(status='delivered') delivered, SUM(status='failed') failed FROM dispatch_orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll();
        $statuses=$this->db->query("SELECT status,COUNT(*) total FROM dispatch_orders GROUP BY status ORDER BY total DESC")->fetchAll();
        $this->respond(true,'Reports retrieved',200,['daily'=>$rows,'statuses'=>$statuses]);
    }

    public function branches(): void
    {
        if (!$this->admin()) return;
        $rows=$this->db->query("SELECT b.id,b.name,b.city,b.code,COUNT(o.id) orders,COALESCE(SUM(o.delivery_fee),0) revenue FROM branches b LEFT JOIN dispatch_orders o ON o.branch=b.city GROUP BY b.id,b.name,b.city,b.code ORDER BY b.name")->fetchAll();
        $this->respond(true,'Branches retrieved',200,['branches'=>$rows]);
    }

    public function notifications(): void
    {
        $user=$this->currentUser(); if(!$user){$this->respond(false,'Authentication required',401);return;}
        $stmt=$this->db->prepare("SELECT id,title,message,status,created_at FROM notifications WHERE user_id=:id ORDER BY created_at DESC LIMIT 12"); $stmt->execute(['id'=>$user['id']]);
        $this->respond(true,'Notifications retrieved',200,['notifications'=>$stmt->fetchAll()]);
    }
}
