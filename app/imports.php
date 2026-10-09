<?php
function cents($value): int {
    $s=str_replace(["\xc2\xa0",'€',' '],'',trim((string)$value));
    requireValue((bool)preg_match('/^\d{1,5}(?:[.,]\d{1,2})?$/',$s),'Prezzo non valido: usa euro, ad esempio 2,49.');
    $v=(int)round((float)str_replace(',','.',$s)*100);requireValue($v>0,'Il prezzo deve essere positivo.');return $v;
}
function validDate(string $s): bool {$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);return $d && $d->format('Y-m-d')===$s;}
function safeUrl(string $url): bool {return filter_var($url,FILTER_VALIDATE_URL)!==false && parse_url($url,PHP_URL_SCHEME)==='https' && !parse_url($url,PHP_URL_USER);}
function flag($value): int {return in_array($value,[1,'1',true,'true','si','sì'],true)?1:0;}
function validateOffer(array $r,string $origin): array {
    $title=trim((string)($r['title']??''));requireValue($title!==''&&mb_strlen($title)<=250,'Nome offerta obbligatorio (massimo 250 caratteri).');
    $chain=$r['chain']??'';$q=db()->prepare('SELECT id FROM sources WHERE id=?');$q->execute([$chain]);requireValue((bool)$q->fetchColumn(),'Insegna non valida.');
    $store=$r['store_id']??null;$store=$store?:null;
    if($store){$q=db()->prepare('SELECT chain FROM stores WHERE id=?');$q->execute([$store]);requireValue($q->fetchColumn()===$chain,'Negozio e insegna non corrispondono.');}
    requireValue($origin==='automatic' || $store!==null,'Seleziona il punto vendita dell’offerta.');
    $from=(string)($r['valid_from']??'');$to=(string)($r['valid_to']??'');
    requireValue(validDate($from)&&validDate($to)&&$from<=$to,'Date non valide: usa AAAA-MM-GG e fine successiva all’inizio.');
    requireValue((strtotime($to)-strtotime($from))<=366*86400,'Validità massima: un anno.');
    $url=trim((string)($r['url']??''));requireValue(safeUrl($url),'Indica una fonte HTTPS valida.');
    $unit=$r['unit']??'pz';$size=str_replace(',','.',(string)($r['pack_size']??1));
    requireValue(in_array($unit,['kg','g','l','ml','pz'])&&is_numeric($size)&&(float)$size>0&&(float)$size<=10000,'Formato o unità non valido.');
    $size=(float)$size;if($unit==='g'){$size/=1000;$unit='kg';}if($unit==='ml'){$size/=1000;$unit='l';}
    $loose=flag($r['loose']??0);requireValue(!$loose||in_array($unit,['kg','l']),'Il prezzo a peso richiede kg o litri.');
    $min=$r['min_packs']??1;requireValue(filter_var($min,FILTER_VALIDATE_INT)!==false&&(int)$min>=1&&(int)$min<=100,'Numero minimo confezioni non valido.');requireValue(!$loose||(int)$min===1,'Promozioni multiple a peso non supportate.');
    [$category,$variant]=classify($title);
    $o=['chain'=>$chain,'store_id'=>$store,'title'=>$title,'category'=>$category,'variant'=>$variant,
      'price_cents'=>isset($r['price_cents'])&&$origin==='automatic'?(int)$r['price_cents']:cents($r['price']??''),
      'unit'=>$unit,'pack_size'=>$size,'loose'=>$loose,'min_packs'=>(int)$min,'loyalty'=>flag($r['loyalty']??0),
      'conditions'=>mb_substr(trim((string)($r['conditions']??'')),0,500),'conditions_known'=>flag($r['conditions_known']??1),
      'valid_from'=>$from,'valid_to'=>$to,'url'=>$url,'origin'=>$origin,'scope'=>$store?'Punto vendita selezionato':(string)($r['scope']??'Ambito nazionale verificato'),'fetched_at'=>now()];
    requireValue($o['price_cents']>0 && $o['price_cents']<=9999999,'Prezzo fuori intervallo.');
    // Identity excludes price so a corrected price replaces the same offer, rather than retaining a cheaper obsolete duplicate.
    $o['fingerprint']=hash('sha256',json_encode([$chain,$store,$title,$size,$unit,$loose,$from,$to,$o['loyalty'],$origin,$o['min_packs'],$url],JSON_UNESCAPED_UNICODE));
    return $o;
}
function persistOffers(array $offers,?string $replaceChain=null): int {
    if(!$offers)return 0;
    $db=db();$db->beginTransaction();try{
        if($replaceChain!==null){
            foreach($offers as $o)requireValue($o['chain']===$replaceChain && $o['origin']==='automatic','Snapshot incoerente.');
            $ids=array_column($offers,'fingerprint');
            $delete=$db->prepare('DELETE FROM offers WHERE chain=? AND origin=\'automatic\' AND fingerprint NOT IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $delete->execute(array_merge([$replaceChain],$ids));
        }
        $keys=array_keys($offers[0]);$updates=implode(',',array_map(fn($k)=>$k.'=excluded.'.$k,array_filter($keys,fn($k)=>$k!=='fingerprint')));
        $q=$db->prepare('INSERT INTO offers('.implode(',',$keys).') VALUES('.implode(',',array_fill(0,count($keys),'?')).') ON CONFLICT(fingerprint) DO UPDATE SET '.$updates);
        foreach($offers as $o)$q->execute(array_values($o));$db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
    return count($offers);
}
function importRows(array $rows): int {
    requireValue(count($rows)>0&&count($rows)<=300,'Importa da 1 a 300 offerte alla volta.');$clean=[];
    foreach($rows as $index=>$r){try{$clean[]=validateOffer($r,'assisted');}catch(InvalidArgumentException $e){throw new InvalidArgumentException('Riga '.($index+1).': '.$e->getMessage());}}
    $count=persistOffers($clean);db()->prepare('INSERT INTO runs(chain,started_at,finished_at,status,count,message) VALUES(NULL,?,?,?, ?,?)')->execute([now(),now(),'success',$count,'Importazione assistita confermata dall’utente.']);return $count;
}
function previewCsv(string $text): array {
    requireValue(strlen($text)<=2*1024*1024,'CSV troppo grande (massimo 2 MB).');
    $text=preg_replace('/^\xEF\xBB\xBF/','',$text);$first=strtok($text,"\n")?:'';
    $delimiter=substr_count($first,';')>=substr_count($first,',')?';':',';
    $f=fopen('php://temp','r+');fwrite($f,$text);rewind($f);$headers=fgetcsv($f,0,$delimiter);
    requireValue(is_array($headers)&&in_array('title',$headers)&&in_array('price',$headers),'Intestazione richiesta: title;price;pack_size;unit;valid_from;valid_to;url');
    requireValue(count($headers)===count(array_unique($headers)),'Intestazioni duplicate nel CSV.');
    $rows=[];while(($values=fgetcsv($f,0,$delimiter))!==false){if($values===[null])continue;requireValue(count($values)===count($headers),'Numero colonne CSV incoerente.');$rows[]=array_combine($headers,$values);requireValue(count($rows)<=300,'Massimo 300 righe.');}fclose($f);return $rows;
}
function pdfText(string $path): string {
    requireValue(substr(file_get_contents($path,false,null,0,5),0,5)==='%PDF-','Il file non è un PDF.');
    $bin=null;foreach(['/opt/homebrew/bin/pdftotext','/usr/local/bin/pdftotext','/usr/bin/pdftotext'] as $p)if(is_executable($p)){$bin=$p;break;}
    requireValue($bin!==null,'pdftotext non disponibile: installa Poppler oppure usa il CSV.');
    // proc_open with an argument array avoids shell interpretation of file names.
    $p=proc_open([$bin,'-f','1','-l','40','-layout',$path,'-'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    requireValue(is_resource($p),'Impossibile avviare pdftotext.');fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $out='';$start=microtime(true);
    do{$out.=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);$status=proc_get_status($p);if(microtime(true)-$start>15||strlen($out)>1000000){proc_terminate($p);fclose($pipes[1]);fclose($pipes[2]);proc_close($p);throw new InvalidArgumentException('PDF troppo complesso: massimo 40 pagine e 15 secondi.');}usleep(20000);}while($status['running']);
    $out.=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);proc_close($p);
    requireValue(preg_match('/\S/u',$out)===1,'PDF senza testo leggibile: usa l’inserimento manuale.');return $out;
}
