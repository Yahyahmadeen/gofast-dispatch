<?php
require_once __DIR__ . '/../controllers/FinanceController.php';
$c=new FinanceController($pdo);$action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD'];
if($method==='POST'&&$action==='initialize'){ $c->initializePayment();exit; }
if($method==='GET'&&$action==='verify'){ $c->verifyPayment();exit; }
if($method==='GET'&&$action==='wallet'){ $c->riderWallet();exit; }
if($method==='POST'&&$action==='payout-account'){ $c->savePayoutAccount();exit; }
if($method==='GET'&&$action==='admin-payouts'){ $c->adminPayouts();exit; }
if($method==='POST'&&$action==='payout-request'){ $c->requestPayout();exit; }
if($method==='GET'&&$action==='payouts'){ $c->dispatcherPayouts();exit; }
if($method==='POST'&&$action==='process-payout'){ $c->processPayout();exit; }
http_response_code(404);echo json_encode(['success'=>false,'message'=>'Finance endpoint not found']);
