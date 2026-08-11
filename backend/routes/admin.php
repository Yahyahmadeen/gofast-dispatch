<?php
require_once __DIR__ . '/../controllers/AdminController.php';
$controller = new AdminController($pdo);
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET' && $action === 'dashboard') { $controller->dashboard(); exit; }
if ($method === 'GET' && $action === 'users') { $controller->users(); exit; }
if ($method === 'POST' && $action === 'user-status') { $controller->updateUserStatus(); exit; }
if ($method === 'GET' && $action === 'riders') { $controller->riders(); exit; }
if ($method === 'POST' && $action === 'verify-rider') { $controller->verifyRider(); exit; }
if ($method === 'GET' && $action === 'reports') { $controller->reports(); exit; }
if ($method === 'GET' && $action === 'branches') { $controller->branches(); exit; }
if ($method === 'GET' && $action === 'notifications') { $controller->notifications(); exit; }
http_response_code(404);
echo json_encode(['success'=>false,'message'=>'Admin endpoint not found']);
