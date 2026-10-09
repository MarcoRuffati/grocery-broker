#!/usr/bin/env python3
"""Integration checks against the isolated demo server, never the real database."""
import os, json, urllib.request, urllib.error, http.cookiejar
BASE=os.environ.get('GROCERY_TEST_URL','http://127.0.0.1:8788')
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path, body=None, token=None, headers=None):
    data=None if body is None else json.dumps(body).encode()
    h={'Content-Type':'application/json'}
    if token:h['X-CSRF-Token']=token
    h.update(headers or {})
    try:
        with client.open(urllib.request.Request(BASE+path,data=data,headers=h),timeout=15) as r:
            content=r.read();return r.status,json.loads(content) if 'application/json' in r.headers.get('Content-Type','') else content.decode()
    except urllib.error.HTTPError as e:return e.code,e.read().decode()
status,s=call('/api/state');assert status==200 and s['demo'], 'Run only against the demo database'
token=s['csrf']
status,c=call('/api/compare');assert status==200 and c['total']==1415 and c['covered']==4
assert {t['store']['id']:(t['total'],t['covered']) for t in c['totals']}=={'market-b-main':(1523,4),'market-d-main':(1614,4),'market-c-main':(1436,3),'market-a-main':(2091,4)}
assert call('/api/list',{'items':[]})[0]==403
assert call('/api/state',headers={'Host':'example.com'})[0]==403
assert call('/var/demo.sqlite')[0]==404
assert call('/app/bootstrap.php')[0]==404
assert call('/api/import',{'reviewed':False,'rows':[]},token)[0]==422
status,parsed=call('/api/list/parse',{'text':'mele 500 g\ndetersivo per piatti 750 ml\nuova 6 pezzi'},token)
assert status==200 and parsed['items'][0]['quantity']==.5 and parsed['items'][1]['quantity']==.75
status,preview=call('/api/import/preview',{'text':'title;price;pack_size;unit;valid_from;valid_to;url\nProdotto QA;2,49;750;ml;2026-09-28;2026-10-04;https://example.com/offer'},token)
assert status==200 and len(preview['rows'])==1
row={**preview['rows'][0],'store_id':'market-a-main','chain':'market-a'}
status,result=call('/api/import',{'reviewed':True,'rows':[row]},token);assert status==200 and result['count']==1
status,result=call('/api/offers');qa=[o for o in result['offers'] if o['title']=='Prodotto QA'];assert len(qa)==1 and float(qa[0]['pack_size'])==.75
call('/api/import',{'reviewed':True,'rows':[row]},token)
status,result=call('/api/offers');assert sum(o['title']=='Prodotto QA' for o in result['offers'])==1
assert call('/api/offers/delete',{'id':qa[0]['id']},token)[0]==200
status,result=call('/api/offers');assert not any(o['title']=='Prodotto QA' for o in result['offers'])
assert call('/api/sync',{},token)[0]==422
print('16 scenari HTTP verificati: totali, normalizzazione, importazione, idempotenza, rimozione, CSRF, Host, file privati e isolamento demo.')
