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

echo json_encode([
    "success" => true,
    "message" => "GOFAST API is running",
    "database" => "connected"
]);