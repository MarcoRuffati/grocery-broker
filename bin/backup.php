#!/usr/bin/env php
<?php
require __DIR__.'/../app/bootstrap.php';
$dir=dataDir().'/backups';if(!is_dir($dir))mkdir($dir,0700,true);
$path=$dir.'/grocery-broker-'.date('Ymd-His').'-'.bin2hex(random_bytes(2)).'.sqlite';
// SQLite produces a consistent standalone copy even if the live DB uses WAL.
db()->exec('VACUUM INTO '.db()->quote($path));echo $path.PHP_EOL;
