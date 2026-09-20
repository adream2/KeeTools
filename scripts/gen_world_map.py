# -*- coding: utf-8 -*-
"""生成 tools/map-world 的真实海陆轮廓数据（MC_WORLD）。

流程：
1. 数据源：world-atlas countries-110m（TopoJSON，缓存 var/tmp/world-map/countries-110m.json，
   缺失时联网下载——本脚本与 gen_china_map.py 一样是低频联网脚本）。
2. 解码 TopoJSON（arcs 差分量化）→ 177 个国家的多边形环。
3. 按国家英文名归入七大洲（CONTINENT_OF 映射，未映射即报错退出）。
4. 南极洲闭合到 -90°（南极点封顶，平面图与地球仪都完整）。
5. 等距圆柱投影（与工具内 X/Y 一致）→ Douglas-Peucker 简化。
6. 输出紧凑 JSON（经纬度 0.1° 精度整数），注入 tools/map-world/index.html 的
   MC_WORLD:BEGIN / MC_WORLD:END 标记之间。

用法：python scripts/gen_world_map.py [--check]
"""
import json
import math
import os
import sys
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC_JSON = os.path.join(ROOT, 'var', 'tmp', 'world-map', 'countries-110m.json')
GEO_URL = 'https://cdn.jsdelivr.net/npm/world-atlas@2/countries-110m.json'
TOOL_HTML = os.path.join(ROOT, 'tools', 'map-world', 'index.html')

BEGIN = '/* MC_WORLD:BEGIN 由 scripts/gen_world_map.py 生成，请勿手工编辑 */'
END = '/* MC_WORLD:END */'

VW, VH = 1000.0, 500.0
EPS = 0.5          # Douglas-Peucker 容差（视口单位）

# ---- 国家 → 大洲映射（world-atlas 110m 的 name 属性，共 177 个，必须全覆盖）----
CONTINENT_OF = {
    # 亚洲
    'Afghanistan': 'asia', 'Armenia': 'asia', 'Azerbaijan': 'asia', 'Bangladesh': 'asia',
    'Bhutan': 'asia', 'Brunei': 'asia', 'Cambodia': 'asia', 'China': 'asia',
    'Cyprus': 'asia', 'Georgia': 'asia', 'India': 'asia', 'Indonesia': 'asia',
    'Iran': 'asia', 'Iraq': 'asia', 'Israel': 'asia', 'Japan': 'asia',
    'Jordan': 'asia', 'Kazakhstan': 'asia', 'Kuwait': 'asia', 'Kyrgyzstan': 'asia',
    'Laos': 'asia', 'Lebanon': 'asia', 'Malaysia': 'asia', 'Mongolia': 'asia',
    'Myanmar': 'asia', 'N. Cyprus': 'asia', 'Nepal': 'asia', 'North Korea': 'asia',
    'Oman': 'asia', 'Pakistan': 'asia', 'Palestine': 'asia', 'Philippines': 'asia',
    'Qatar': 'asia', 'Saudi Arabia': 'asia', 'South Korea': 'asia', 'Sri Lanka': 'asia',
    'Syria': 'asia', 'Taiwan': 'asia', 'Tajikistan': 'asia', 'Thailand': 'asia',
    'Timor-Leste': 'asia', 'Turkey': 'asia', 'Turkmenistan': 'asia',
    'United Arab Emirates': 'asia', 'Uzbekistan': 'asia', 'Vietnam': 'asia', 'Yemen': 'asia',
    # 欧洲
    'Albania': 'europe', 'Austria': 'europe', 'Belarus': 'europe', 'Belgium': 'europe',
    'Bosnia and Herz.': 'europe', 'Bulgaria': 'europe', 'Croatia': 'europe',
    'Czechia': 'europe', 'Denmark': 'europe', 'Estonia': 'europe', 'Finland': 'europe',
    'France': 'europe', 'Germany': 'europe', 'Greece': 'europe', 'Hungary': 'europe',
    'Iceland': 'europe', 'Ireland': 'europe', 'Italy': 'europe', 'Kosovo': 'europe',
    'Latvia': 'europe', 'Lithuania': 'europe', 'Luxembourg': 'europe', 'Macedonia': 'europe',
    'Moldova': 'europe', 'Montenegro': 'europe', 'Netherlands': 'europe', 'Norway': 'europe',
    'Poland': 'europe', 'Portugal': 'europe', 'Romania': 'europe', 'Russia': 'europe',
    'Serbia': 'europe', 'Slovakia': 'europe', 'Slovenia': 'europe', 'Spain': 'europe',
    'Sweden': 'europe', 'Switzerland': 'europe', 'Ukraine': 'europe', 'United Kingdom': 'europe',
    # 非洲
    'Algeria': 'africa', 'Angola': 'africa', 'Benin': 'africa', 'Botswana': 'africa',
    'Burkina Faso': 'africa', 'Burundi': 'africa', 'Cameroon': 'africa',
    'Central African Rep.': 'africa', 'Chad': 'africa', 'Congo': 'africa',
    "Côte d'Ivoire": 'africa', 'Dem. Rep. Congo': 'africa', 'Djibouti': 'africa',
    'Egypt': 'africa', 'Eq. Guinea': 'africa', 'Eritrea': 'africa', 'Ethiopia': 'africa',
    'Gabon': 'africa', 'Gambia': 'africa', 'Ghana': 'africa', 'Guinea': 'africa',
    'Guinea-Bissau': 'africa', 'Kenya': 'africa', 'Lesotho': 'africa', 'Liberia': 'africa',
    'Libya': 'africa', 'Madagascar': 'africa', 'Malawi': 'africa', 'Mali': 'africa',
    'Mauritania': 'africa', 'Morocco': 'africa', 'Mozambique': 'africa', 'Namibia': 'africa',
    'Niger': 'africa', 'Nigeria': 'africa', 'Rwanda': 'africa', 'S. Sudan': 'africa',
    'Senegal': 'africa', 'Sierra Leone': 'africa', 'Somalia': 'africa',
    'Somaliland': 'africa', 'South Africa': 'africa', 'Sudan': 'africa',
    'Tanzania': 'africa', 'Togo': 'africa', 'Tunisia': 'africa', 'Uganda': 'africa',
    'W. Sahara': 'africa', 'Zambia': 'africa', 'Zimbabwe': 'africa', 'eSwatini': 'africa',
    # 北美洲
    'Bahamas': 'namerica', 'Belize': 'namerica', 'Canada': 'namerica',
    'Costa Rica': 'namerica', 'Cuba': 'namerica', 'Dominican Rep.': 'namerica',
    'El Salvador': 'namerica', 'Greenland': 'namerica', 'Guatemala': 'namerica',
    'Haiti': 'namerica', 'Honduras': 'namerica', 'Jamaica': 'namerica',
    'Mexico': 'namerica', 'Nicaragua': 'namerica', 'Panama': 'namerica',
    'Puerto Rico': 'namerica', 'Trinidad and Tobago': 'namerica',
    'United States of America': 'namerica',
    # 南美洲
    'Argentina': 'samerica', 'Bolivia': 'samerica', 'Brazil': 'samerica',
    'Chile': 'samerica', 'Colombia': 'samerica', 'Ecuador': 'samerica',
    'Falkland Is.': 'samerica', 'Guyana': 'samerica', 'Paraguay': 'samerica',
    'Peru': 'samerica', 'Suriname': 'samerica', 'Uruguay': 'samerica', 'Venezuela': 'samerica',
    # 大洋洲
    'Australia': 'oceania', 'Fiji': 'oceania', 'New Caledonia': 'oceania',
    'New Zealand': 'oceania', 'Papua New Guinea': 'oceania', 'Solomon Is.': 'oceania',
    'Vanuatu': 'oceania',
    # 南极洲
    'Antarctica': 'antarctica', 'Fr. S. Antarctic Lands': 'antarctica',
}


def load_topo():
    if os.path.exists(SRC_JSON):
        return json.load(open(SRC_JSON, encoding='utf-8'))
    os.makedirs(os.path.dirname(SRC_JSON), exist_ok=True)
    print('本地缓存缺失，联网下载 TopoJSON：' + GEO_URL)
    urllib.request.urlretrieve(GEO_URL, SRC_JSON)
    return json.load(open(SRC_JSON, encoding='utf-8'))


def decode_arcs(topo):
    """TopoJSON arcs → 绝对经纬度弧段列表"""
    tr = topo['transform']
    sx, sy = tr['scale']
    tx, ty = tr['translate']
    out = []
    for arc in topo['arcs']:
        x = y = 0
        pts = []
        for dx, dy in arc:
            x += dx
            y += dy
            pts.append((x * sx + tx, y * sy + ty))
        out.append(pts)
    return out


def ring_from_arc_indexes(arc_list, arcs):
    """按 TopoJSON 规则拼环：负索引取反弧；相邻弧去掉重复接点"""
    pts = []
    for idx in arc_list:
        seg = arcs[idx] if idx >= 0 else list(reversed(arcs[~idx]))
        if pts:
            pts.extend(seg[1:])
        else:
            pts.extend(seg)
    return pts


def dp_mask(points, eps):
    """Douglas-Peucker，返回保留点的下标列表"""
    n = len(points)
    if n < 3:
        return list(range(n))
    keep = [False] * n
    keep[0] = keep[-1] = True
    stack = [(0, n - 1)]
    while stack:
        a, b = stack.pop()
        if b <= a + 1:
            continue
        ax, ay = points[a]
        bx, by = points[b]
        dx, dy = bx - ax, by - ay
        norm = math.hypot(dx, dy) or 1e-12
        best, idx = -1.0, -1
        for i in range(a + 1, b):
            px, py = points[i]
            d = abs(dx * (ay - py) - dy * (ax - px)) / norm
            if d > best:
                best, idx = d, i
        if best > eps:
            keep[idx] = True
            stack.append((a, idx))
            stack.append((idx, b))
    return [i for i in range(n) if keep[i]]


def simplify_ring(pts, eps=EPS):
    """输入原始 (lon,lat) 环；DP 在投影空间做，返回保留下来的原始经纬度点"""
    v = [((lon + 180) / 360 * VW, (90 - lat) / 180 * VH) for lon, lat in pts]
    while len(v) > 1 and v[0] == v[-1]:
        v.pop()
        pts.pop()
    idx = dp_mask(v, eps)
    sel = [pts[i] for i in idx]
    if len(sel) > 1 and sel[0] != sel[-1]:
        sel.append(sel[0])
    return sel


def main():
    topo = load_topo()
    arcs = decode_arcs(topo)
    geoms = topo['objects']['countries']['geometries']

    countries = []
    unmapped = []
    for g in geoms:
        name = g.get('properties', {}).get('name', '')
        ct = CONTINENT_OF.get(name)
        if ct is None:
            unmapped.append(name)
            continue
        if g['type'] == 'Polygon':
            polys = [g['arcs']]
        elif g['type'] == 'MultiPolygon':
            polys = g['arcs']
        else:
            continue
        rings = []
        for poly in polys:
            for arc_list in poly:
                raw = ring_from_arc_indexes(arc_list, arcs)
                sv = simplify_ring(raw)
                if len(sv) >= 4:
                    rings.append(sv)
        if not rings:
            continue

        # 南极洲：闭合到 -90°，平面图封到底边、地球仪封到极点
        if name == 'Antarctica':
            ext = []
            for r in rings:
                lons = [p[0] for p in r]
                if min(lons) <= -179.9 and max(lons) >= 179.9 and min(p[1] for p in r) < -60:
                    ext.append(r + [(180.0, -90.0), (-180.0, -90.0)])
                else:
                    ext.append(r)
            rings = ext

        pack = []
        for r in rings:
            pack.append(' '.join('%d,%d' % (round(lon * 10), round(lat * 10)) for lon, lat in r))
        countries.append([ct, name, '|'.join(pack)])

    if unmapped:
        print('[ERR] 未映射大洲的国家：%s' % ', '.join(unmapped))
        return 1

    order = {'asia': 0, 'europe': 1, 'africa': 2, 'namerica': 3, 'samerica': 4, 'oceania': 5, 'antarctica': 6}
    countries.sort(key=lambda c: order[c[0]])
    js = 'var MC_WORLD=%s;' % json.dumps(
        {'w': 1000, 'h': 500, 'l': countries}, ensure_ascii=False, separators=(',', ':'))

    html = open(TOOL_HTML, encoding='utf-8').read()
    bi = html.find(BEGIN)
    ei = html.find(END)
    if bi < 0 or ei < 0 or ei < bi:
        print('[ERR] 未找到 MC_WORLD 标记块')
        return 1
    cur = html[bi + len(BEGIN):ei].strip()
    if '--check' in sys.argv:
        if cur == js.strip():
            print('[OK] 海陆轮廓数据一致（%d 国）' % len(countries))
            return 0
        print('[ERR] 工具内海陆数据与生成结果不一致，请运行 python scripts/gen_world_map.py')
        return 1

    if cur == js.strip():
        print('[OK] 数据已是最新（%d 国）' % len(countries))
        return 0
    new = html[:bi + len(BEGIN)] + '\n' + js + '\n' + html[ei:]
    with open(TOOL_HTML, 'w', encoding='utf-8', newline='') as fh:
        fh.write(new)
    print('[OK] 已注入 tools/map-world/index.html（%d 国，%.1f KB）'
          % (len(countries), len(js.encode('utf-8')) / 1024))
    return 0


if __name__ == '__main__':
    sys.exit(main())
