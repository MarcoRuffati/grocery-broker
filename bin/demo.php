#!/usr/bin/env php
<?php
// Demo writes only to its own database; never copy demo prices to the real SQLite file.
putenv('GROCERY_DB='.__DIR__.'/../var/demo.sqlite');
require __DIR__.'/../app/bootstrap.php';
$db=db();if(setting('demo',false)){echo "Demo already initialized.\n";exit;}
setSetting('demo',true);$db->exec("UPDATE stores SET favorite=1 WHERE id IN ('market-a-main','market-b-main','market-c-main','market-d-main')");
saveList(parseList("petto di pollo 1 kg\npomodori 1 kg\nmele 1 kg\ndetersivo per piatti 1 l"));
$from=date('Y-m-d',strtotime('monday this week'));$to=date('Y-m-d',strtotime('sunday this week'));
$data=[
 ['market-a-main','Petto di pollo',4.99,0.5,'kg'],['market-a-main','Pomodori ciliegino',1.49,0.3,'kg'],['market-a-main','Mele Gala',2.19,2,'kg'],['market-a-main','Detersivo per piatti',1.39,0.5,'l'],
 ['market-b-main','Petto di pollo',4.39,0.5,'kg'],['market-b-main','Pomodori',1.29,0.5,'kg'],['market-b-main','Mele Golden',1.49,1,'kg'],['market-b-main','Detersivo per piatti',1.19,0.5,'l'],
 ['market-c-main','Petto di pollo',5.29,0.5,'kg'],['market-c-main','Pomodori',1.89,1,'kg'],['market-c-main','Mele',1.89,1,'kg'],
 ['market-d-main','Petto di pollo',4.59,0.5,'kg'],['market-d-main','Pomodori',1.69,0.5,'kg'],['market-d-main','Mele',1.59,1,'kg'],['market-d-main','Detersivo per piatti',1.99,1,'l'],
];
$offers=[];foreach($data as [$sid,$title,$price,$size,$unit]){$s=$db->query("SELECT * FROM stores WHERE id=".$db->quote($sid))->fetch();$o=validateOffer(['chain'=>$s['chain'],'store_id'=>$sid,'title'=>$title,'price'=>(string)$price,'unit'=>$unit,'pack_size'=>$size,'valid_from'=>$from,'valid_to'=>$to,'url'=>$s['source_url'],'conditions'=>'DEMO: prezzo fittizio, non è un’offerta reale.'],'assisted');$o['origin']='demo';$offers[]=$o;}persistOffers($offers);echo "Demo ready: var/demo.sqlite contains fictional prices.\n";
