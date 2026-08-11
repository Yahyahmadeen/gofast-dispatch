<?php

function gofastEnv(string $key, ?string $default = null): ?string
{
    static $loaded = false;
    static $values = [];

    if (!$loaded) {
        $path = dirname(__DIR__) . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $values[trim($k)] = trim($v, " \t\"'");
            }
        }
        $loaded = true;
    }

    return getenv($key) !== false ? getenv($key) : ($values[$key] ?? $default);
}

function paystackRequest(string $method, string $path, ?array $payload = null): array
{
    $secret = gofastEnv('PAYSTACK_SECRET_KEY');
    if (!$secret) {
        throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured');
    }

    $ch = curl_init('https://api.paystack.co' . $path);
    $headers = [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('Paystack request failed: ' . $error);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) throw new RuntimeException('Invalid Paystack response');

    if ($status >= 400 || empty($decoded['status'])) {
        throw new RuntimeException($decoded['message'] ?? 'Paystack request failed');
    }

    return $decoded;
}
