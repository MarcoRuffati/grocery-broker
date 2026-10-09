<?php
/** A connector must return a complete, validated snapshot for its source or throw. */
interface Connector {
    /** @return array Offers produced by validateOffer($row, 'automatic'). */
    public function collect(): array;
}

/** Register authorized source implementations here, keyed by sources.id. No network access by default. */
function configuredConnectors(): array { return []; }

/**
 * Collect complete snapshots, retaining previous offers on failure.
 * The optional registry allows isolated tests without network requests.
 */
function syncSources(bool $force=false, ?array $registry=null): array {
    db();
    requireValue(!setting('demo', false), 'Demo data cannot be refreshed from live sources.');
    $registry=$registry ?? configuredConnectors();
    if (!$registry) return ['unconfigured'=>true, 'message'=>'No live collectors configured. Use assisted imports.'];
    $lock=fopen(dataDir().'/sync.lock','c');
    if (!flock($lock,LOCK_EX|LOCK_NB)) {fclose($lock);return ['busy'=>true];}
    try {
        $due=(new DateTimeImmutable('monday this week'))->setTime(7,0);
        if ($due>new DateTimeImmutable()) $due=$due->modify('-7 days');
        if (!$force && ((int)setting('last_sync_success',0)>=$due->getTimestamp() || (int)setting('last_sync_attempt',0)>time()-3600)) return ['skipped'=>true];
        setSetting('last_sync_attempt',time());$result=[];$allSucceeded=true;
        foreach ($registry as $chain=>$connector) {
            $source=db()->prepare('SELECT id FROM sources WHERE id=?');$source->execute([$chain]);
            requireValue((bool)$source->fetchColumn() && $connector instanceof Connector, 'Invalid connector registration.');
            $start=now();$count=0;
            try {
                $offers=$connector->collect();
                requireValue(count($offers)>0,'Empty collection: previous snapshot retained.');
                foreach ($offers as $offer) requireValue(!empty($offer['store_id']) || !empty($offer['scope']), 'Explicit offer scope required.');
                $count=persistOffers($offers,$chain);$status='success';$message="$count offers collected.";
                db()->prepare('UPDATE sources SET status=?,note=?,last_success=?,last_attempt=? WHERE id=?')->execute(['automatic',$message,now(),$start,$chain]);
            } catch (Throwable $e) {
                $allSucceeded=false;$status='error';$message=$e->getMessage();
                db()->prepare('UPDATE sources SET status=?,note=?,last_attempt=? WHERE id=?')->execute(['assisted',$message,$start,$chain]);
            }
            db()->prepare('INSERT INTO runs(chain,started_at,finished_at,status,count,message) VALUES(?,?,?,?,?,?)')->execute([$chain,$start,now(),$status,$count,$message]);
            $result[]=['chain'=>$chain,'status'=>$status,'count'=>$count,'message'=>$message];
        }
        if ($allSucceeded) setSetting('last_sync_success',time());
        return $result;
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
