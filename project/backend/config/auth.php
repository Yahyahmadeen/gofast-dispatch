<?php

function gofastAuthToken(): string
{
    $token = trim($_SERVER['HTTP_X_GOFAST_TOKEN'] ?? '');
    if ($token !== '') return $token;

    $header = trim($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return trim($matches[1]);
    }

    // Apache/PHP on some Windows setups exposes the Authorization header this way.
    $apache = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(\S+)/i', $apache, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function gofastCurrentUser(PDO $db): ?array
{
    $token = gofastAuthToken();
    if ($token === '') return null;

    $stmt = $db->prepare("SELECT u.id,u.full_name,u.email,u.phone,u.role,u.status
        FROM sessions s
        JOIN users u ON u.id=s.user_id
        WHERE s.token_hash=:hash AND s.expires_at>NOW() AND u.status='active'
        LIMIT 1");
    $stmt->execute(['hash' => hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}
