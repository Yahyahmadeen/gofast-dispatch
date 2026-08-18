<?php
require_once __DIR__ . '/../controllers/OrderController.php';
$controller = new OrderController($pdo);
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && $action === 'create') { $controller->create(); exit; }
if ($method === 'GET' && $action === 'customer') { $controller->customerOrders(); exit; }
if ($method === 'GET' && $action === 'dispatcher') { $controller->dispatcherOrders(); exit; }
if ($method === 'GET' && $action === 'available-riders') { $controller->availableRiders(); exit; }
if ($method === 'GET' && $action === 'rider') { $controller->riderOrders(); exit; }
if ($method === 'POST' && $action === 'assign') { $controller->assign(); exit; }
if ($method === 'POST' && $action === 'accept') { $controller->riderAccept(); exit; }
if ($method === 'POST' && $action === 'status') { $controller->updateStatus(); exit; }
http_response_code(404); echo json_encode(['success'=>false,'message'=>'Order endpoint not found']);
