<?php
require_once __DIR__ . '/../controllers/ManagementController.php';
$c=new ManagementController($pdo);$action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD'];
if($method==='GET'&&$action==='riders'){ $c->riderApplications();exit; }
if($method==='POST'&&$action==='review-rider'){ $c->reviewRider();exit; }
if($method==='GET'&&$action==='users'){ $c->users();exit; }
http_response_code(404);echo json_encode(['success'=>false,'message'=>'Management endpoint not found']);
