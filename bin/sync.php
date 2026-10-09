#!/usr/bin/env php
<?php
require __DIR__.'/../app/bootstrap.php';
try{db();echo json_encode(syncSources(!in_array('--if-due',$argv,true)),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
