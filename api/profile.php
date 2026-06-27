<?php
require __DIR__ . '/../config.php';
$user=api_user(); if(!verify_csrf($_POST['csrf']??null)) json_response(['ok'=>false,'error'=>'Bad CSRF token.'],403); $pdo=db(); $action=$_POST['action']??'';
if($action==='status'){ $status=in_array(($_POST['status']??'online'),['online','dnd','offline'],true)?$_POST['status']:'online'; $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status,$user['id']]); json_response(['ok'=>true,'status'=>$status]); }
if($action==='avatar' && !empty($_FILES['avatar']['name'])){ $path=save_image_upload($_FILES['avatar'],'uploads/avatars',5*1024*1024); $pdo->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$path,$user['id']]); json_response(['ok'=>true,'avatar'=>$path]); }
json_response(['ok'=>false,'error'=>'Unknown action.'],400);
