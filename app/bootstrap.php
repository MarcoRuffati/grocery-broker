<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Rome');
const ROOT = __DIR__ . '/..';
function dataDir(): string {
    if(getenv('GROCERY_DB'))return dirname(getenv('GROCERY_DB'));
    return ROOT.'/var';
}
function db(): PDO {
    static $db;
    if ($db) return $db;
    $path = getenv('GROCERY_DB') ?: dataDir().'/grocery-broker.sqlite';
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
    $db->exec(file_get_contents(__DIR__.'/schema.sql'));
    seed($db);
    return $db;
}
function setting(string $key, $default=null) {
    $q=db()->prepare('SELECT value FROM settings WHERE key=?'); $q->execute([$key]);
    $v=$q->fetchColumn(); return $v===false ? $default : json_decode($v,true);
}
function setSetting(string $key,$value): void {
    db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$key,json_encode($value,JSON_UNESCAPED_UNICODE)]);
}
function seed(PDO $db): void {
    if ($db->query('SELECT COUNT(*) FROM sources')->fetchColumn()) return;
    $db->exec('BEGIN IMMEDIATE');
    try {
    if ($db->query('SELECT COUNT(*) FROM sources')->fetchColumn()) {$db->exec('COMMIT');return;}
    $q=$db->prepare('INSERT INTO sources(id,name,status,url,note) VALUES(?,?,?,?,?)');
    $store=$db->prepare('INSERT INTO stores(id,chain,name,address,city,cap,source_url,verified_at) VALUES(?,?,?,?,?,?,?,?)');
    foreach (['a','b','c','d'] as $letter) {
        $id='market-'.$letter;
        $name='Example Market '.strtoupper($letter);
        $q->execute([$id,$name,'assisted','https://example.com','Fictional store. Import your own authorized data; no live collector is configured.']);
        $store->execute([$id.'-main',$id,$name,'Example Street','Demo City','00000','https://example.com',date('Y-m-d')]);
    }
    $db->prepare('INSERT INTO settings VALUES(?,?)')->execute(['caps','["00000"]']);
    $db->exec('COMMIT');
    } catch(Throwable $e) {$db->exec('ROLLBACK');throw $e;}
}
function now(): string {return date('c');}
function norm(string $v): string {return trim(preg_replace('/\s+/u',' ',mb_strtolower($v)));}
function requireValue(bool $condition,string $message): void {if(!$condition)throw new InvalidArgumentException($message);}
require_once __DIR__.'/domain.php';
require_once __DIR__.'/imports.php';
require_once __DIR__.'/connectors.php';
