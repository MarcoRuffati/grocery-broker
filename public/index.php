<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$host=$_SERVER['HTTP_HOST']??'';
if(!preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/',$host)){http_response_code(403);exit('Solo accesso locale.');}
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','path'=>'/']);session_start();
if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];session_write_close();
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/api/')){
    header('Content-Type: application/json; charset=utf-8');
    try{
        $method=$_SERVER['REQUEST_METHOD'];
        if($method!=='GET'){
            requireValue($method==='POST','Metodo non supportato.');
            if(!hash_equals($csrf,$_SERVER['HTTP_X_CSRF_TOKEN']??'')){http_response_code(403);throw new InvalidArgumentException('Sessione scaduta: ricarica la pagina.');}
            requireValue((int)($_SERVER['CONTENT_LENGTH']??0)<=12*1024*1024,'Richiesta troppo grande.');
        }
        $in=$method==='POST'&&!isset($_FILES['file'])?json_decode(file_get_contents('php://input'),true):[];$in=$in??[];
        switch($method.' '.$path){
          case 'GET /api/state':
            $out=['csrf'=>$csrf,'caps'=>setting('caps',['00000']),'items'=>db()->query('SELECT * FROM list_items ORDER BY id')->fetchAll(),'stores'=>db()->query('SELECT * FROM stores ORDER BY chain,city,name')->fetchAll(),'sources'=>db()->query('SELECT * FROM sources ORDER BY name')->fetchAll(),'runs'=>db()->query('SELECT * FROM runs ORDER BY id DESC LIMIT 15')->fetchAll(),'offer_count'=>(int)db()->query("SELECT COUNT(*) FROM offers WHERE conditions_known=1 AND valid_from<=date('now','localtime') AND valid_to>=date('now','localtime')")->fetchColumn(),'demo'=>(bool)setting('demo',false),'last_attempt'=>setting('last_sync_attempt',0)];break;
          case 'POST /api/list/parse':$out=['items'=>parseList((string)($in['text']??''))];break;
          case 'POST /api/list':saveList($in['items']??[]);$out=['ok'=>true];break;
          case 'POST /api/preferences':
            $caps=$in['caps']??[];requireValue(is_array($caps)&&count($caps)<=50,'Massimo 50 CAP.');foreach($caps as $cap)requireValue(is_string($cap)&&preg_match('/^\d{5}$/',$cap)===1,'Ogni CAP deve contenere cinque cifre.');setSetting('caps',array_values(array_unique($caps)));$out=['ok'=>true];break;
          case 'POST /api/store':
            $q=db()->prepare('UPDATE stores SET favorite=?,loyalty=? WHERE id=?');$q->execute([flag($in['favorite']??0),flag($in['loyalty']??0),$in['id']??'']);requireValue($q->rowCount()>0,'Negozio non trovato.');$out=['ok'=>true];break;
          case 'GET /api/compare':$out=comparison();break;
          case 'GET /api/offers':$out=['offers'=>db()->query('SELECT o.*,s.name as store_name FROM offers o LEFT JOIN stores s ON o.store_id=s.id ORDER BY fetched_at DESC,id DESC LIMIT 500')->fetchAll()];break;
          case 'POST /api/offers/delete':$q=db()->prepare('DELETE FROM offers WHERE id=? AND origin<>?');$q->execute([(int)($in['id']??0),'automatic']);$out=['deleted'=>$q->rowCount()];break;
          case 'POST /api/import/preview':
            if(isset($_FILES['file'])){
                $f=$_FILES['file'];requireValue($f['error']===UPLOAD_ERR_OK&&$f['size']<=10*1024*1024,'Caricamento non riuscito o file oltre 10 MB.');
                $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
                if($ext==='pdf')$out=['text'=>pdfText($f['tmp_name']),'rows'=>[]];
                else{requireValue($ext==='csv','Sono accettati solo CSV e PDF.');$out=['rows'=>previewCsv(file_get_contents($f['tmp_name']))];}
            }else $out=['rows'=>previewCsv((string)($in['text']??''))];break;
          case 'POST /api/import':requireValue(($in['reviewed']??false)===true,'Conferma di aver verificato le offerte.');$out=['count'=>importRows($in['rows']??[])];break;
          case 'POST /api/sync':
            requireValue(!setting('demo',false),'La demo usa dati fittizi isolati e non aggiorna fonti reali.');
            set_time_limit(120);$out=['results'=>syncSources(true)];break;
          default:http_response_code(404);$out=['error'=>'Risorsa non trovata.'];
        }
        echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(InvalidArgumentException $e){if(http_response_code()!==403)http_response_code(422);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
    catch(Throwable $e){http_response_code(500);error_log((string)$e);echo json_encode(['error'=>'Operazione non riuscita. I dati già salvati sono conservati. Consulta il registro del server.']);}
    exit;
}
if($path!=='/'&&$path!=='/index.php'){http_response_code(404);exit('Pagina non trovata.');}
?><!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#245b45"><meta name="csrf-token" content="<?=htmlspecialchars($csrf)?>"><title>Grocery Broker · La spesa, al posto giusto</title><link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/style.css"><script src="/assets/app.js" defer></script></head>
<body>
<div id="demo-banner" hidden>MODALITÀ DEMO · Prezzi fittizi, database separato dalle offerte reali.</div>
<header class="header"><a class="brand" href="#lista" aria-label="Grocery Broker, la mia lista"><img src="/assets/leaf.svg" width="38" height="38" alt=""><span>grocery<span class="brand-light">broker</span><span class="brand-dot">.</span></span></a><nav aria-label="Navigazione principale"><a href="#lista" data-view="lista">La mia lista</a><a href="#confronto" data-view="confronto">Confronta</a><a href="#supermercati" data-view="supermercati">Supermercati</a></nav><button id="settings-button" class="icon-button" aria-label="Fonti e impostazioni" title="Fonti e impostazioni">⚙</button></header>
<main id="main"><div class="loading">Prepariamo la tua spesa…</div></main>
<footer class="footer"><span><i class="status-dot"></i> Locale, semplice, senza abbonamenti.</span><span>Negozi dimostrativi <span aria-hidden="true">↗</span></span></footer>
<div id="toast" role="status" aria-live="polite" hidden></div>
<dialog id="detail"><div id="detail-content"></div><button class="button secondary" id="close-detail">Chiudi</button></dialog>
<noscript>Attiva JavaScript per usare la lista e confrontare le offerte.</noscript>
</body></html>
