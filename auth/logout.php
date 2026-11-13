<?php
require_once __DIR__.'/../config/bootstrap.php';
start_secure_session();
unset($_SESSION['member_discord_id'],$_SESSION['member_username'],$_SESSION['member_avatar'],$_SESSION['member_role'],$_SESSION['discord_oauth_state'],$_SESSION['discord_oauth_created'],$_SESSION['discord_oauth_return_to']);
session_regenerate_id(true);
header('Cache-Control: no-store');
header('Location: /calendario', true, 302);
exit;
