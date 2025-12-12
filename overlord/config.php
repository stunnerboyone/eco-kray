<?php
$external = '/var/www/ekokrai/data//www/vault/overlord/config.php';
if (file_exists($external) && is_readable($external)) {
    require_once $external;
    return;
}
header('HTTP/1.1 500 Internal Server Error', true, 500);
echo "Missing admin configuration file. Expected: $external";
exit;
