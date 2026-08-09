<?php

require_once __DIR__ . "/../controllers/AuthController.php";

$authController = new AuthController($pdo);

$method = $_SERVER["REQUEST_METHOD"];

$action = $_GET["action"] ?? "";

if ($method === "POST" && $action === "register") {

    $authController->register();
    exit;
}

if ($method === "POST" && $action === "login") {

    $authController->login();
    exit;
}

http_response_code(404);

echo json_encode([
    "success" => false,
    "message" => "Authentication endpoint not found"
]);