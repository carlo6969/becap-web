import json,re
P=json.load(open('raw.json'))
best=["don-t-trip-mac-miller","matanga-mx","monsters-university-azul-rey","monsters-university-panel-rigido","sin-titulo-27ago_14-46-26","gorra-mago-total-black-31-hats","monster-university","boston-flex-fitted"]
def cat(p):
    t=p['t'].upper(); ty=p['ty'].upper(); tg=[x.upper() for x in p['tg']]
    if any(k in t for k in ['CALCET','GAFA','BANDANA','KIT DE LIMPIEZA']): return 'complementos'
    if any(k in t for k in ['HOODIE','SUDADERA','SUÉTER','PLAYERA','SHORT','ROMPEVIENTOS']): return 'ropa'
    if 'BUCKET' in t or 'BRIMLESS' in t: return 'bucket'
    if 'KIBLO CAPS' in tg or 'BARBAS HATS' in tg or 'Tipo dandy'.upper()==ty or 'BARBAS' in t or '31 HATS' in t or 'INNEDIT' in t: return 'disenador'
    if 'NEW ERA' in t or 'NEW ERA' in tg or '9FORTY' in tg or '9FIFTY' in tg or 'FERRARI' in t or "'47" in t or '‘47' in t or 'DETROIT' in t: return 'originales'
    return 'vintage'
def brand(p):
    t=p['t'].upper(); tg=[x.upper() for x in p['tg']]
    for k,v in [('KIBLO','Kiblo Caps'),('BARBAS','Barbas Hats'),('31 HATS','31 Hats'),('INNEDIT','Innedit'),('FERRARI','Puma · Ferrari'),('47','’47 Brand'),('NORTH FACE','The North Face'),('CARHATT','Carhartt'),('VANS','Vans'),('ESSENTIALS','Essentials'),('NIKE','Nike'),('ADIDAS','Adidas'),('ALO','Alo')]:
        if k in t: return v
    if 'NEW ERA' in t or 'NEW ERA' in tg or '9FORTY' in tg or '9FIFTY' in tg or 'DETROIT' in t or 'OHTANI' in t: return 'New Era'
    if 'KIBLO CAPS' in tg: return 'Kiblo Caps'
    return 'Be Cap Selección'
def fit(p):
    tg=[x.upper() for x in p['tg']]; t=p['t'].upper()
    if 'FLEX' in t or 'FITTED' in t or 'AJUSTE CERRADO' in tg or 'FITTED' in tg or '59FIFTY' in t or 'CERRADA' in t: return 'Flex / cerrada'
    if 'SNAPBACK' in tg or '9FORTY' in tg or 'A-FRAME' in tg: return 'Snapback'
    if 'STRAPBACK' in tg: return 'Strapback'
    if 'AJUSTABLE' in tg: return 'Ajustable'
    return ''
def clean(t):
    t=re.sub(r'\s+',' ',t).strip().strip('.').replace(' .','.')
    t=t.replace('PANEL RIGIDO','PANEL RÍGIDO').replace('ALGODON','ALGODÓN').replace('CARHATT','CARHARTT').replace('Carhatt','Carhartt')
    t=re.sub(r'\.\s*(?=[A-Z])','. ',t)
    t=t.upper().replace('L. A LAKERS','L.A. LAKERS').replace('BRIMLESSS','BRIMLESS')
    return t
out=[]
for i,p in enumerate(P):
    c=cat(p)
    if c not in ('ropa','complementos','bucket') and 'GORRA' not in p['t'].upper(): pass
    vs=p['vs']; opts=p['o']
    prices=[v[1] for v in vs]; avail=[v for v in vs if v[3]]
    cmp_=max([v[2] for v in vs]+[0])
    d=p['d']
    d=re.sub(r'\s+([,.;:!?])',r'\1',d); d=d.replace('C ombinan','Combinan')
    out.append(dict(id=i,h=p['h'],t=clean(p['t']),c=cat(p),b=brand(p),f=fit(p),
      p=min(prices),cp=cmp_ if cmp_>min(prices) else 0,
      o=[] if opts==['Title'] else opts,
      v=[] if opts==['Title'] else [[v[0],v[1],v[3]] for v in vs],
      a=1 if avail else 0, n=len(p['im']), d=d, bs=1 if p['h'] in best else 0,
      new=1 if i<6 else 0))
json.dump(out,open('products.json','w'),ensure_ascii=False,separators=(',',':'))
imgs=[[i,k,src] for i,p in enumerate(P) for k,src in enumerate(p['im'])]
json.dump(imgs,open('imgs.json','w'))
from collections import Counter
print(Counter(x['c'] for x in out)); print(Counter(x['b'] for x in out)); print(Counter(x['f'] for x in out)); print(len(imgs))
for x in out: print(x['id'],x['c'],x['b'],x['f'],x['t'])
