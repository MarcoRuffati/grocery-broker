<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($path,['/assets/app.js','/assets/style.css','/assets/leaf.svg','/favicon.svg'],true)||preg_match('~^/assets/logos/(market-a|market-b|market-c|market-d)\.svg$~',$path))return false;
require __DIR__.'/index.php';
