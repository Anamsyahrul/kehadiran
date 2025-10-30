<?php
require __DIR__ . '/../bootstrap.php';
session_start();
setcookie('rm_sel','',time()-3600,'/');
setcookie('rm_val','',time()-3600,'/');
session_destroy();
header('Location: /kehadiran/web/public/login.php');
exit;