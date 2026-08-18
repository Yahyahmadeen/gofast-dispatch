<?php

require_once __DIR__ . "/config/cors.php";

header("Content-Type: application/json");

require_once __DIR__ . "/config/database.php";

$route = $_GET["route"] ?? "";
$action = $_GET["action"] ?? "";

if ($route === "auth") {
    require_once __DIR__ . "/routes/auth.php";
    exit;
}

if ($route === "orders") {
    require_once __DIR__ . "/routes/orders.php";
    exit;
}

if ($route === "finance") {
    require_once __DIR__ . "/routes/finance.php";
    exit;
}

if ($route === "management") {
    require_once __DIR__ . "/routes/management.php";
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "GOFAST API is running",
    "database" => "connected"
]);