<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
try {
    $pdo=db();
    $st=$pdo->prepare("SELECT id,name,race,class_name,class_color,level,role,status,notes,COALESCE(NULLIF(roster_group,''),'A') AS roster_group FROM roster WHERE faction=? ORDER BY FIELD(COALESCE(NULLIF(roster_group,''),'A'),'A','B','20','40'),status DESC,name");
    $st->execute([nexus_faction()]);
    json_response($st->fetchAll(PDO::FETCH_ASSOC));
} catch(Throwable $e){
    json_response(['error'=>'Error del servidor'],500);
}
