#!/usr/bin/env python3
import json, urllib.parse, urllib.request

UA = 'DigitalRehber/1.0'

def wiki_thumb(filename, width=500):
    title = f'File:{filename}'
    q = urllib.parse.urlencode({
        'action': 'query', 'titles': title, 'prop': 'imageinfo',
        'iiprop': 'url', 'iiurlwidth': str(width), 'format': 'json'
    })
    req = urllib.request.Request('https://commons.wikimedia.org/w/api.php?' + q, headers={'User-Agent': UA})
    with urllib.request.urlopen(req, timeout=20) as r:
        d = json.load(r)
    p = list(d['query']['pages'].values())[0]
    if 'missing' in p:
        return None
    return p['imageinfo'][0].get('thumburl')

def check(url):
    if not url:
        return 'MISS'
    req = urllib.request.Request(url, headers={'User-Agent': UA})
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return str(r.status)
    except Exception as e:
        return f'ERR:{e}'

files = [
    'Turkish_breakfast.jpg', 'Menemen_in_a_sahan.jpg', 'Menemen_with_cheese.jpg',
    'Sujuk.jpg', 'Sigara_boregi.jpg', 'Gozleme.jpg', 'Lahmacun.jpg',
    'Turkish_coffee_in_Istanbul.jpg', 'Bottled_water.jpg', 'Orange_juice_1.jpg',
    'Cola.jpg', 'Iced_tea.jpg', 'Instant_coffee.jpg', 'Mineral_water.jpg',
    'Soft_drink_can.jpg', 'Fried_eggs.jpg', 'Scrambled_eggs.jpg',
    'Durum.jpg', 'Kebab.jpg', 'Turkish_coffee_cup.jpg', 'Coffee_cup.jpg',
    'Water_bottle.jpg', 'Glass_bottle.jpg', 'Can_of_soda.jpg', 'Soda_water.jpg',
    'Pepsi.jpg', 'Pepsi_bottle.jpg', 'Turkish_coffee_in_sand.jpg',
    'Gozleme_-_Turkish_flatbread.jpg', 'Sigara_boregi_2.jpg', 'Sigara_boregi_(1).jpg',
]

for f in files:
    u = wiki_thumb(f)
    print(f'{check(u)} | {f} | {u or "MISSING"}')

unsplash = [
    'https://images.unsplash.com/photo-1746203170430-1950daf6e234?w=500&q=80',
    'https://images.unsplash.com/photo-1769706722493-36e1f9f6dc10?w=500&q=80',
    'https://images.unsplash.com/photo-1525351484163-7529414344d8?w=500&q=80',
    'https://images.unsplash.com/photo-1603360946369-dc9bb6258143?w=500&q=80',
]
for u in unsplash:
    print(f'{check(u)} | unsplash | {u}')
