<?php
declare(strict_types=1);
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
$cfgFile=__DIR__.'/../config/config.php';
if (is_file($cfgFile)) { ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NEXUS | Instalación completada</title><link rel="stylesheet" href="/css/global.css"><link rel="stylesheet" href="/Admin/assets/admin.css"></head><body class="admin-body"><main class="admin-main"><div class="admin-card login-admin"><h1>Instalación ya realizada</h1><p>Por seguridad, elimina <code>Admin/setup.php</code> del servidor.</p><a class="btn primary" href="/Admin/">Ir al login</a></div></main></body></html><?php exit; }
$error=''; $done=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $host=trim($_POST['db_host']??'localhost'); $name=trim($_POST['db_name']??''); $user=trim($_POST['db_user']??''); $pass=$_POST['db_pass']??''; $admin=trim($_POST['admin_user']??'admin'); $adminPass=$_POST['admin_pass']??'';
  if($name===''||$user===''||strlen($admin)<3||strlen($adminPass)<10){$error='Completa todos los campos. La contraseña del administrador debe tener al menos 10 caracteres.';} else {
    try {
      $pdo=new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
      $sql=file_get_contents(__DIR__.'/../database.sql'); foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $statement){$pdo->exec($statement);} 
      $hash=password_hash($adminPass,PASSWORD_DEFAULT); $stmt=$pdo->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?, 'admin')"); $stmt->execute([$admin,$hash]);
      // Import the old static calendar once.
      $count=(int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
      if($count===0 && is_file(__DIR__.'/../datos/eventos.json')){ $events=json_decode(file_get_contents(__DIR__.'/../datos/eventos.json'),true); if(is_array($events)){ $ins=$pdo->prepare('INSERT INTO events(event_date,event_time,title,type,description) VALUES(?,?,?,?,?)'); foreach($events as $ev){$ins->execute([$ev['date']??date('Y-m-d'),$ev['time']??null,$ev['title']??'Evento',$ev['type']??'Evento',$ev['description']??'']);}}}
      $config=[
        'db_host'=>$host,'db_name'=>$name,'db_user'=>$user,'db_pass'=>$pass,'db_charset'=>'utf8mb4','app_url'=>'https://www.nexus-forever.es'
      ];
      $php="<?php\nreturn ".var_export($config,true).";\n";
      if(file_put_contents($cfgFile,$php)===false) throw new RuntimeException('No se pudo escribir config/config.php. Revisa permisos de la carpeta config.');
      @chmod($cfgFile,0600); $done=true;
    }catch(Throwable $e){$error='Error de instalación: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');}
  }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalación NEXUS</title><link rel="stylesheet" href="/css/global.css"><link rel="stylesheet" href="/Admin/assets/admin.css"></head><body class="admin-body"><main class="admin-main"><div class="admin-card login-admin"><div class="eyebrow">NEXUS · INSTALACIÓN</div><?php if($done): ?><h1>Instalación completada</h1><p>La base de datos está preparada y se ha creado el administrador.</p><p><strong>Por seguridad, elimina ahora <code>Admin/setup.php</code>.</strong></p><a class="btn primary" href="/Admin/">Entrar al panel</a><?php else: ?><h1>Configurar NEXUS</h1><p>Antes de usar el panel necesitas crear una base de datos MySQL y un usuario desde cPanel.</p><?php if($error): ?><div class="error-box"><?=$error?></div><?php endif; ?><form class="admin-form" method="post"><h3>Base de datos MySQL</h3><label>Host<input name="db_host" value="<?=e($_POST['db_host']??'localhost')?>"></label><label>Nombre de BD<input name="db_name" value="<?=e($_POST['db_name']??'')?>" required></label><label>Usuario BD<input name="db_user" value="<?=e($_POST['db_user']??'')?>" required></label><label>Contraseña BD<input name="db_pass" type="password"></label><h3>Administrador</h3><label>Usuario administrador<input name="admin_user" value="<?=e($_POST['admin_user']??'admin')?>" required></label><label>Contraseña administrador<input name="admin_pass" type="password" minlength="10" required></label><button class="btn primary" type="submit">Instalar NEXUS</button></form><?php endif; ?></div></main></body></html>