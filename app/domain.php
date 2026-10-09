<?php
// Deterministic, local matching: prepared foods never collapse into fresh ingredients.
function classify(string $name): array {
    $s=norm($name);
    $map=[
      'detersivo_piatti'=>'/\b(detersivo|detergente|sapone)\b.*\bpiatti\b/u',
      'pollo'=>'/\bpollo\b/u','pomodori'=>'/\b(pomodori|pomodoro|pomodorini)\b/u',
      'mele'=>'/\b(mele|mela)\b/u','pere'=>'/\b(pere|pera)\b/u','banane'=>'/\b(banane|banana)\b/u',
      'uva'=>'/\buva\b/u','patate'=>'/\bpatat[ae]\b/u','zucchine'=>'/\bzucchin[aeio]\b/u',
      'carote'=>'/\bcarot[ae]\b/u','cipolle'=>'/\bcipoll[ae]\b/u','latte'=>'/\blatte\b/u',
      'uova'=>'/\buov[ao]\b/u','pasta'=>'/\b(pasta|spaghetti|penne|fusilli|rigatoni)\b/u',
      'riso'=>'/\briso\b/u','pane'=>'/\bpane\b/u','tonno'=>'/\btonno\b/u','olio'=>'/\bolio\b/u',
      'yogurt'=>'/\byogurt\b/u','caffe'=>'/\bcaff[eè]\b/u',
    ];
    $category='altro:'.$s;foreach($map as $k=>$re)if(preg_match($re,$s)){$category=$k;break;}
    $variant='';
    if(preg_match('/kebab|cotolett|nugget|impanat|arrostit|burger|affettat|arrosticin|cotto|cotta|spiedin|farci[tao]|rotolo|salsicc|polpett|tramezzin|pront[oaie]|passata|pelati|sugo|succo|purea|patatine/u',$s))$variant='preparato';
    elseif(preg_match('/surgelat|congelat/u',$s))$variant='surgelato';
    elseif($category==='pollo'){
        foreach(['petto'=>'petto|fettine','cosce'=>'cosce|fusi|sovracosce','intero'=>'intero','parti'=>'in parti','ali'=>'ali'] as $v=>$re)if(preg_match('/\b('.$re.')\b/u',$s)){$variant=$v;break;}
    }
    foreach(['bio'=>'/\b(bio|biologico|biologica|biologici|biologiche)\b/u','integrale'=>'/\bintegrale\b/u','senza lattosio'=>'/senza lattosio/u','senza glutine'=>'/senza glutine/u'] as $v=>$re)if(preg_match($re,$s))$variant=trim($variant.' '.$v);
    return [$category,$variant];
}
function defaultUnit(string $cat): string {return in_array($cat,['pollo','pomodori','mele','pere','banane','uva','patate','zucchine','carote','cipolle'])?'kg':'conf';}
function parseList(string $text): array {
    requireValue(mb_strlen($text)<=10000,'Lista troppo lunga (massimo 10.000 caratteri).');
    $out=[];
    foreach(preg_split('/\r?\n|;/u',$text) as $line){
        $line=trim($line);if(!$line)continue;
        $qty=1;$unit=null;$name=$line;
        if(preg_match('/(?:^|\s)(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|pz|pezzi|pezzo|confezioni|confezione|conf|flaconi|flacone)\b/iu',$line,$m)){
            $qty=(float)str_replace(',','.',$m[1]);$unit=mb_strtolower($m[2]);$name=trim(str_replace($m[0],' ',$line));
            if($unit==='g'){$qty/=1000;$unit='kg';}elseif($unit==='ml'){$qty/=1000;$unit='l';}
            elseif(in_array($unit,['pezzi','pezzo']))$unit='pz';
            elseif(!in_array($unit,['kg','l','pz','conf']))$unit='conf';
        }
        requireValue($qty>0 && $qty<=10000 && $name!=='','Quantità o nome non valido: '.$line);
        [$cat,$variant]=classify($name);
        $out[]=['name'=>$name,'category'=>$cat,'variant'=>$variant,'quantity'=>$qty,'unit'=>$unit??defaultUnit($cat),'done'=>0];
    }
    requireValue(count($out)<=150,'Massimo 150 prodotti per lista.');return $out;
}
function saveList(array $items): void {
    requireValue(count($items)<=150,'Massimo 150 prodotti.');$clean=[];
    foreach($items as $i){
        $name=trim((string)($i['name']??''));$qty=$i['quantity']??0;$unit=$i['unit']??'';
        requireValue($name!==''&&mb_strlen($name)<=160&&is_numeric($qty)&&$qty>0&&$qty<=10000&&in_array($unit,['kg','l','pz','conf']),'Prodotto, quantità o unità non validi.');
        [$cat,$inferred]=classify($name);$variant=(string)($i['variant']??$inferred);
        requireValue(mb_strlen($variant)<=80,'Variante non valida.');
        requireValue(!in_array($unit,['pz','conf']) || floor((float)$qty)==(float)$qty,'Pezzi e confezioni richiedono quantità intere.');
        $key=norm($name).'|'.$variant.'|'.$unit;
        if(isset($clean[$key])){$clean[$key][3]+=(float)$qty;requireValue($clean[$key][3]<=10000,'Quantità totale eccessiva.');$clean[$key][5]=(int)($clean[$key][5]&&!empty($i['done']));}
        else $clean[$key]=[$name,$cat,$variant,(float)$qty,$unit,!empty($i['done'])?1:0];
    }
    $db=db();$db->beginTransaction();try{$db->exec('DELETE FROM list_items');$q=$db->prepare('INSERT INTO list_items(name,category,variant,quantity,unit,done) VALUES(?,?,?,?,?,?)');foreach($clean as $i)$q->execute($i);$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
}
function quoteOffer(array $item,array $o): ?array {
    if($item['unit']==='conf'){
        if($o['loose'])return null;
        $packs=(int)ceil($item['quantity']);
        if($packs<(int)$o['min_packs'])return null;
        $cost=$packs*(int)$o['price_cents'];$bought=$packs*(float)$o['pack_size'];
    }else{
        if($item['unit']!==$o['unit'])return null;
        if($o['loose']){$packs=null;$bought=(float)$item['quantity'];$cost=(int)round($bought/(float)$o['pack_size']*(int)$o['price_cents']);}
        else{$packs=(int)ceil(round($item['quantity']/$o['pack_size'],8));if($packs<(int)$o['min_packs'])return null;$bought=$packs*(float)$o['pack_size'];$cost=$packs*(int)$o['price_cents'];}
    }
    return ['offer'=>$o,'cost'=>$cost,'packs'=>$packs,'bought'=>$bought,'unit_price'=>(int)round($o['price_cents']/$o['pack_size']),'estimated_weight'=>(bool)$o['loose']];
}
function comparison(?string $today=null): array {
    $today=$today??date('Y-m-d');$stores=db()->query('SELECT * FROM stores WHERE favorite=1 ORDER BY chain,name')->fetchAll();
    $items=db()->query('SELECT * FROM list_items ORDER BY id')->fetchAll();
    $q=db()->prepare('SELECT * FROM offers WHERE conditions_known=1 AND valid_from<=? AND valid_to>=? ORDER BY id');$q->execute([$today,$today]);$offers=$q->fetchAll();
    $totals=[];foreach($stores as $s)$totals[$s['id']]=['store'=>$s,'total'=>0,'covered'=>0,'items'=>[]];
    $rows=[];$groups=[];$sum=0;$covered=0;
    foreach($items as $item){
        $candidates=[];$variants=[];
        foreach($offers as $o){
            if($o['category']!==$item['category'])continue;
            // Generic ingredients never include prepared/frozen/dietary variants by accident.
            if($item['variant']==='*') {if(preg_match('/preparato|surgelato|bio|integrale|senza lattosio|senza glutine/',$o['variant']))continue;}
            elseif($item['variant']!=='' && $o['variant']!==$item['variant'])continue;
            elseif($item['variant']==='' && $item['category']!=='pollo' && $o['variant']!=='')continue;
            elseif($item['variant']==='' && preg_match('/preparato|surgelato/',$o['variant']))continue;
            foreach($stores as $s){
                if($s['chain']!==$o['chain']||($o['store_id']!==null && $o['store_id']!==$s['id'])||($o['loyalty']&&!$s['loyalty']))continue;
                $quote=quoteOffer($item,$o);if(!$quote)continue;
                $variants[$o['variant']?:'generico']=true;$candidates[$s['id']][]=$quote;
            }
        }
        $ambiguous=$item['category']==='pollo' && $item['variant']==='' && count($candidates)>0;
        $cells=[];$best=null;
        foreach($stores as $s){
            $choices=$candidates[$s['id']]??[];usort($choices,fn($a,$b)=>($a['cost']<=>$b['cost'])?:($a['offer']['id']<=>$b['offer']['id']));
            $cell=(!$ambiguous&&$choices)?$choices[0]:null;
            $cells[$s['id']]=['best'=>$cell,'alternatives'=>$choices];
            if($cell){$totals[$s['id']]['total']+=$cell['cost'];$totals[$s['id']]['covered']++;$totals[$s['id']]['items'][]=['item'=>$item,'quote'=>$cell];
                if($best===null||$cell['cost']<$best['quote']['cost'])$best=['store'=>$s,'quote'=>$cell];}
        }
        if($best){$covered++;$sum+=$best['quote']['cost'];$sid=$best['store']['id'];if(!isset($groups[$sid]))$groups[$sid]=['store'=>$best['store'],'total'=>0,'items'=>[]];$groups[$sid]['total']+=$best['quote']['cost'];$groups[$sid]['items'][]=['item'=>$item,'quote'=>$best['quote']];}
        $rows[]=['item'=>$item,'cells'=>$cells,'best'=>$best,'ambiguous'=>$ambiguous,'variants'=>array_keys($variants)];
    }
    foreach($totals as &$t)$t['complete']=count($items)>0&&$t['covered']===count($items);unset($t);
    return ['stores'=>$stores,'rows'=>$rows,'totals'=>array_values($totals),'groups'=>array_values($groups),'total'=>$sum,'covered'=>$covered,'count'=>count($items),'complete'=>count($items)>0&&$covered===count($items),'date'=>$today];
}
