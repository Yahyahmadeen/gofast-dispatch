<?php

require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../config/paystack.php";

class AuthController
{
    private PDO $db;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->userModel = new User($db);
    }

    public function register(): void
    {
        $data = json_decode(file_get_contents("php://input"), true) ?: [];
        $role = strtolower(trim($data["role"] ?? "customer"));

        if (!in_array($role, ["customer", "rider"], true)) {
            $this->response(false, "Only customer and rider registration is available", 400);
            return;
        }

        $fullName = trim($data["full_name"] ?? "");
        $email = trim($data["email"] ?? "");
        $phone = trim($data["phone"] ?? "");
        $password = $data["password"] ?? "";

        if ($fullName === "" || $email === "" || $phone === "" || $password === "") {
            $this->response(false, "Full name, email, phone and password are required", 400);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response(false, "Enter a valid email address", 400);
            return;
        }
        if (strlen($password) < 8) {
            $this->response(false, "Password must be at least 8 characters", 400);
            return;
        }
        if ($this->userModel->findByEmail($email)) {
            $this->response(false, "Email is already registered", 409);
            return;
        }
        if ($this->userModel->findByPhone($phone)) {
            $this->response(false, "Phone number is already registered", 409);
            return;
        }

        if ($role === 'rider') {
            $nin = preg_replace('/\D+/', '', (string)($data['nin'] ?? ''));
            $bvn = preg_replace('/\D+/', '', (string)($data['bvn'] ?? ''));
            $vehicleType = trim($data['vehicle_type'] ?? '');
            $vehicleNumber = trim($data['vehicle_number'] ?? '');
            $bankName = trim($data['bank_name'] ?? '');
            $accountName = trim($data['account_name'] ?? '');
            $accountNumber = preg_replace('/\D+/', '', (string)($data['account_number'] ?? ''));

            if (strlen($nin) !== 11 || ($bvn !== '' && strlen($bvn) !== 11)) {
                $this->response(false, "Rider NIN must contain 11 digits and BVN, when provided, must contain 11 digits", 400);
                return;
            }
            if ($vehicleType === '' || $vehicleNumber === '' || $bankName === '' || $accountName === '' || strlen($accountNumber) !== 10) {
                $this->response(false, "Vehicle and payout account details are required for rider registration", 400);
                return;
            }

            try {
                $this->db->beginTransaction();
                $userId = $this->userModel->create($fullName, $email, $phone, $password, 'rider', 0);

                $key = gofastEnv('GOFAST_ENCRYPTION_KEY', 'CHANGE_THIS_LOCAL_KEY');
                $cipher = function(string $value) use ($key): string {
                    $iv = random_bytes(16);
                    return base64_encode($iv . openssl_encrypt($value, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv));
                };

                $verification = $this->db->prepare("INSERT INTO rider_verifications (user_id,nin_last4,nin_encrypted,bvn_last4,bvn_encrypted,vehicle_type,vehicle_number,verification_status) VALUES (:user,:nin4,:nin,:bvn4,:bvn,:vehicle,:number,'pending')");
                $verification->execute([
                    'user' => $userId,
                    'nin4' => substr($nin, -4),
                    'nin' => $cipher($nin),
                    'bvn4' => $bvn !== '' ? substr($bvn, -4) : null,
                    'bvn' => $bvn !== '' ? $cipher($bvn) : null,
                    'vehicle' => $vehicleType,
                    'number' => $vehicleNumber,
                ]);

                $accountIv = random_bytes(16);
                $accountCipher = base64_encode($accountIv . openssl_encrypt($accountNumber, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $accountIv));
                $payout = $this->db->prepare("INSERT INTO rider_payout_accounts (rider_user_id,bank_name,account_name,account_number_last4,account_number_encrypted,status) VALUES (:user,:bank,:name,:last4,:account,'pending')");
                $payout->execute([
                    'user' => $userId,
                    'bank' => $bankName,
                    'name' => $accountName,
                    'last4' => substr($accountNumber, -4),
                    'account' => $accountCipher,
                ]);

                // Keep the legacy rider table populated for availability/order modules.
                try {
                    $legacy = $this->db->prepare("INSERT INTO riders (user_id,id_number,vehicle_type,vehicle_number,verification_status) VALUES (:user,:id_number,:vehicle,:number,'pending') ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type),vehicle_number=VALUES(vehicle_number),verification_status='pending'");
                    $legacy->execute(['user'=>$userId,'id_number'=>'NIN-'.substr($nin,-4),'vehicle'=>$vehicleType,'number'=>$vehicleNumber]);
                } catch (Throwable $ignored) {}

                $this->db->commit();
                $verificationUrl = $this->createVerificationToken($userId, $email);
                $this->response(true, "Rider application submitted. Verify your email, then wait for GOFAST approval.", 201, [
                    'user_id' => $userId,
                    'role' => 'rider',
                    'verification_status' => 'pending',
                    'verification_url' => $verificationUrl,
                ]);
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                $this->response(false, "Unable to create rider account: " . $e->getMessage(), 500);
            }
            return;
        }

        try {
            $userId = $this->userModel->create($fullName, $email, $phone, $password, 'customer', 0);
            $stmt = $this->db->prepare("INSERT INTO customers (user_id,customer_type) VALUES (:user_id,'individual')");
            $stmt->execute(['user_id' => $userId]);
            $verificationUrl = $this->createVerificationToken($userId, $email);
            $this->response(true, "Customer account created. Verify your email before signing in.", 201, [
                'user_id' => $userId,
                'role' => 'customer',
                'verification_url' => $verificationUrl,
            ]);
        } catch (PDOException $e) {
            $this->response(false, "Unable to create account", 500);
        }
    }

    private function createVerificationToken(int $userId, string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $stmt = $this->db->prepare("INSERT INTO email_verifications (user_id,token_hash,expires_at) VALUES (:user,:hash,:expires)");
        $stmt->execute(['user' => $userId, 'hash' => $hash, 'expires' => $expires]);

        $base = gofastEnv('GOFAST_APP_URL', 'http://localhost:5174');
        return rtrim($base, '/') . '/verify-email?token=' . urlencode($token);
    }

    public function verifyEmail(): void
    {
        $token = trim($_GET['token'] ?? '');
        if ($token === '') { $this->response(false, 'Verification token is required', 400); return; }
        $stmt = $this->db->prepare("SELECT * FROM email_verifications WHERE token_hash=:hash AND expires_at>NOW() AND verified_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) { $this->response(false, 'Verification link is invalid or expired', 400); return; }
        $this->db->beginTransaction();
        $this->db->prepare("UPDATE users SET email_verified=1 WHERE id=:user")->execute(['user'=>$row['user_id']]);
        $this->db->prepare("UPDATE email_verifications SET verified_at=NOW() WHERE id=:id")->execute(['id'=>$row['id']]);
        $this->db->commit();
        $this->response(true, 'Email verified successfully. You can now sign in.');
    }

    public function login(): void
    {
        $data = json_decode(file_get_contents("php://input"), true) ?: [];
        $email = trim($data["email"] ?? "");
        $password = $data["password"] ?? "";
        if ($email === "" || $password === "") { $this->response(false, "Email and password are required", 400); return; }
        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user["password_hash"])) { $this->response(false, "Invalid email or password", 401); return; }
        if ((int)($user['email_verified'] ?? 1) !== 1) { $this->response(false, "Please verify your email before signing in", 403, ['verification_required'=>true]); return; }
        if ($user["status"] !== "active") { $this->response(false, "Your account is not active", 403); return; }

        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("INSERT INTO sessions (user_id,token_hash,ip_address,user_agent,expires_at) VALUES (:user,:hash,:ip,:ua,:expires)");
        $stmt->execute([
            "user"=>$user["id"], "hash"=>hash('sha256',$token), "ip"=>$_SERVER["REMOTE_ADDR"]??null,
            "ua"=>$_SERVER["HTTP_USER_AGENT"]??null, "expires"=>date("Y-m-d H:i:s",time()+604800)
        ]);
        $extra = [];
        if ($user['role'] === 'rider') {
            $q = $this->db->prepare("SELECT verification_status FROM rider_verifications WHERE user_id=:user LIMIT 1");
            $q->execute(['user'=>$user['id']]);
            $extra['verification_status'] = $q->fetchColumn() ?: 'pending';
        }
        $loginUser = [
            "id"=>$user["id"], "full_name"=>$user["full_name"], "email"=>$user["email"],
            "phone"=>$user["phone"], "role"=>$user["role"]
        ];
        $this->response(true,"Login successful",200,["token"=>$token,"user"=>array_merge($loginUser,$extra)]);
    }

    private function response(bool $success,string $message,int $status=200,array $data=[]): void
    {
        http_response_code($status);
        echo json_encode(["success"=>$success,"message"=>$message,"data"=>$data]);
    }
}
