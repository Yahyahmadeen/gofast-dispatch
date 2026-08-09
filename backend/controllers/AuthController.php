<?php

require_once __DIR__ . "/../models/User.php";

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
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $fullName = trim($data["full_name"] ?? "");
        $email = trim($data["email"] ?? "");
        $phone = trim($data["phone"] ?? "");
        $password = $data["password"] ?? "";

        if ($fullName === "" || $password === "") {
            $this->response(
                false,
                "Full name and password are required",
                400
            );
            return;
        }

        if ($email === "" && $phone === "") {
            $this->response(
                false,
                "Email or phone number is required",
                400
            );
            return;
        }

        if (strlen($password) < 8) {
            $this->response(
                false,
                "Password must be at least 8 characters",
                400
            );
            return;
        }

        if ($email !== "" && $this->userModel->findByEmail($email)) {
            $this->response(
                false,
                "Email is already registered",
                409
            );
            return;
        }

        if ($phone !== "" && $this->userModel->findByPhone($phone)) {
            $this->response(
                false,
                "Phone number is already registered",
                409
            );
            return;
        }

        try {

            $userId = $this->userModel->create(
                $fullName,
                $email !== "" ? $email : null,
                $phone !== "" ? $phone : null,
                $password,
                "customer"
            );

            $stmt = $this->db->prepare("
                INSERT INTO customers
                (
                    user_id,
                    customer_type
                )
                VALUES
                (
                    :user_id,
                    'individual'
                )
            ");

            $stmt->execute([
                "user_id" => $userId
            ]);

            $this->response(
                true,
                "Customer account created successfully",
                201,
                [
                    "user_id" => $userId
                ]
            );

        } catch (PDOException $e) {

            $this->response(
                false,
                "Unable to create account",
                500
            );
        }
    }

    public function login(): void
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $email = trim($data["email"] ?? "");
        $password = $data["password"] ?? "";

        if ($email === "" || $password === "") {
            $this->response(
                false,
                "Email and password are required",
                400
            );
            return;
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            $this->response(
                false,
                "Invalid email or password",
                401
            );
            return;
        }

        if (!password_verify(
            $password,
            $user["password_hash"]
        )) {
            $this->response(
                false,
                "Invalid email or password",
                401
            );
            return;
        }

        if ($user["status"] !== "active") {
            $this->response(
                false,
                "Your account is not active",
                403
            );
            return;
        }

        $token = bin2hex(random_bytes(32));

        $tokenHash = hash(
            "sha256",
            $token
        );

        $expiresAt = date(
            "Y-m-d H:i:s",
            time() + (60 * 60 * 24 * 7)
        );

        $stmt = $this->db->prepare("
            INSERT INTO sessions
            (
                user_id,
                token_hash,
                ip_address,
                user_agent,
                expires_at
            )
            VALUES
            (
                :user_id,
                :token_hash,
                :ip_address,
                :user_agent,
                :expires_at
            )
        ");

        $stmt->execute([
            "user_id" => $user["id"],
            "token_hash" => $tokenHash,
            "ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
            "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? null,
            "expires_at" => $expiresAt
        ]);

        $this->response(
            true,
            "Login successful",
            200,
            [
                "token" => $token,
                "user" => [
                    "id" => $user["id"],
                    "full_name" => $user["full_name"],
                    "email" => $user["email"],
                    "phone" => $user["phone"],
                    "role" => $user["role"]
                ]
            ]
        );
    }

    private function response(
        bool $success,
        string $message,
        int $status = 200,
        array $data = []
    ): void {

        http_response_code($status);

        echo json_encode([
            "success" => $success,
            "message" => $message,
            "data" => $data
        ]);
    }
}