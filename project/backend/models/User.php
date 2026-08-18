<?php

class User
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            "email" => $email
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        $sql = "SELECT * FROM users WHERE phone = :phone LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            "phone" => $phone
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(
        string $fullName,
        ?string $email,
        ?string $phone,
        string $password,
        string $role = "customer",
        int $emailVerified = 0
    ): int {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "
            INSERT INTO users
            (
                full_name,
                email,
                phone,
                password_hash,
                role,
                status,
                email_verified
            )
            VALUES
            (
                :full_name,
                :email,
                :phone,
                :password_hash,
                :role,
                'active',
                :email_verified
            )
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            "full_name" => $fullName,
            "email" => $email,
            "phone" => $phone,
            "password_hash" => $passwordHash,
            "role" => $role,
            "email_verified" => $emailVerified
        ]);

        return (int) $this->db->lastInsertId();
    }
}