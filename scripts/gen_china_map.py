# -*- coding: utf-8 -*-
"""生成 tools/map-china 的真实省界轮廓数据（MC_GEO）。

流程：
1. 省级 GeoJSON 源（阿里 DataV 100000_full，含十段线）：优先用 var/tmp/china-map/china.json
   本地缓存，缺失时联网下载（本脚本与 icons/prepare_source.py 一样是低频联网脚本）。
2. Albers 等积圆锥投影（中央经线 105°，标准纬线 27°/45°）→ 视口 1000 宽。
3. Douglas-Peucker 简化（容差 0.55 视口单位）+ 坐标一位小数，控制体积。
4. 注入 tools/map-china/index.html 的 MC_GEO:BEGIN / MC_GEO:END 标记之间。

用法：python scripts/gen_china_map.py [--check]
  --check 只校验工具内数据是否与生成结果一致（供 pre-commit 类门禁使用）。
"""
import json
import math
import os
import sys
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC_JSON = os.path.join(ROOT, 'var', 'tmp', 'china-map', 'china.json')
GEO_URL = 'https://geo.datav.aliyun.com/areas_v3/bound/100000_full.json'
TOOL_HTML = os.path.join(ROOT, 'tools', 'map-china', 'index.html')

EPS = 0.55          # Douglas-Peucker 容差（视口单位）
VIEW_W = 1000.0     # 视口宽
BEGIN = '/* MC_GEO:BEGIN 由 scripts/gen_china_map.py 生成，请勿手工编辑 */'
END = '/* MC_GEO:END */'

# ---- Albers 等积圆锥投影（中国常用：中央经线105，标准纬线27/45）----
LON0, PHI1, PHI2 = math.radians(105), math.radians(27), math.radians(45)
R = 6371.0

n = (math.sin(PHI1) + math.sin(PHI2)) / 2
C = math.cos(PHI1) ** 2 + 2 * n * math.sin(PHI1)
rho0 = math.sqrt(C) / n


def project(lon, lat):
    lon, lat = math.radians(lon), math.radians(lat)
    rho = math.sqrt(C - 2 * n * math.sin(lat)) / n
    theta = n * (lon - LON0)
    x = rho * math.sin(theta) * R
    y = (rho0 - rho * math.cos(theta)) * R  # 北为正，配合 to_view 北在上
    return x, y


def dp(points, eps):
    """Douglas-Peucker 简化（保留首尾点）"""
    if len(points) < 3:
        return points
    keep = [False] * len(points)
    keep[0] = keep[-1] = True
    stack = [(0, len(points) - 1)]
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
    return [p for p, k in zip(points, keep) if k]


def simplify_ring(pts, eps=EPS):
    """闭合环简化：先去掉所有重复闭合点（否则 DP 退化，起点=终点法向量为零）"""
    while len(pts) > 1 and pts[0] == pts[-1]:
        pts.pop()
    pts = dp(pts, eps)
    if len(pts) > 1 and pts[0] != pts[-1]:
        pts.append(pts[0])
    return pts


def ring_area_centroid(pts):
    """鞋带公式：面积与形心"""
    a = cx = cy = 0.0
    m = len(pts)
    for i in range(m):
        x1, y1 = pts[i]
        x2, y2 = pts[(i + 1) % m]
        cross = x1 * y2 - x2 * y1
        a += cross
        cx += (x1 + x2) * cross
        cy += (y1 + y2) * cross
    a *= 0.5
    if abs(a) < 1e-9:
        xs = [p[0] for p in pts]
        ys = [p[1] for p in pts]
        return 0.0, sum(xs) / m, sum(ys) / m
    return abs(a) / 2, cx / (6 * a), cy / (6 * a)


def fmt(v):
    s = ('%.1f' % v)
    if s.endswith('.0'):
        s = s[:-2]
    return s


def ring_path(pts):
    return 'M' + 'L'.join(fmt(x) + ' ' + fmt(y) for x, y in pts) + 'Z'


def load_geojson():
    if os.path.exists(SRC_JSON):
        return json.load(open(SRC_JSON, encoding='utf-8'))
    os.makedirs(os.path.dirname(SRC_JSON), exist_ok=True)
    print('本地缓存缺失，联网下载 GeoJSON：' + GEO_URL)
    urllib.request.urlretrieve(GEO_URL, SRC_JSON)
    return json.load(open(SRC_JSON, encoding='utf-8'))


def build_geo():
    data = load_geojson()

    all_xy = []
    feats = []
    for f in data['features']:
        geom = f.get('geometry')
        props = f.get('properties', {})
        if not geom or geom['type'] not in ('Polygon', 'MultiPolygon'):
            continue
        polys = [geom['coordinates']] if geom['type'] == 'Polygon' else geom['coordinates']
        feats.append((props.get('name', ''), str(props.get('adcode', '')), polys))
        for poly in polys:
            for ring in poly:
                for lon, lat in ring:
                    all_xy.append(project(lon, lat))

    xs = [p[0] for p in all_xy]
    ys = [p[1] for p in all_xy]
    minx, maxx, miny, maxy = min(xs), max(xs), min(ys), max(ys)
    scale = VIEW_W / (maxx - minx)

    def to_view(p):
        return ((p[0] - minx) * scale, (maxy - p[1]) * scale)

    provs = []
    jd_parts = []
    for name, adcode, polys in feats:
        if adcode == '100000_JD' or name == '':
            # 十段线：只留线框，虚线描边
            for poly in polys:
                for ring in poly:
                    pts = simplify_ring([to_view(project(lon, lat)) for lon, lat in ring])
                    if len(pts) >= 3:
                        jd_parts.append(ring_path(pts))
            continue
        best_area, best_cx, best_cy = 0.0, 0.0, 0.0
        parts = []
        for poly in polys:
            for ring in poly:
                pts = simplify_ring([to_view(project(lon, lat)) for lon, lat in ring])
                if len(pts) < 4:
                    continue
                area, cx, cy = ring_area_centroid(pts)
                if area > best_area:
                    best_area, best_cx, best_cy = area, cx, cy
                parts.append(ring_path(pts))
        if parts:
            provs.append({
                'n': name,
                'd': ''.join(parts),
                'cx': round(best_cx, 1),
                'cy': round(best_cy, 1),
                'a': round(best_area, 1),
            })

    out = {'w': 1000, 'h': round((maxy - miny) * scale, 1), 'p': provs, 'jd': ''.join(jd_parts)}
    return 'var MC_GEO=' + json.dumps(out, ensure_ascii=False, separators=(',', ':')) + ';'


def inject(js):
    html = open(TOOL_HTML, encoding='utf-8').read()
    bi = html.find(BEGIN)
    ei = html.find(END)
    if bi < 0 or ei < 0 or ei < bi:
        print('[ERR] 未找到 %s ... %s 标记' % (BEGIN, END))
        return False
    new = html[:bi + len(BEGIN)] + '\n' + js + '\n' + html[ei:]
    if new == html:
        print('[OK] 工具内省界数据已是最新')
        return True
    with open(TOOL_HTML, 'w', encoding='utf-8', newline='') as fh:
        fh.write(new)
    print('[OK] 已注入 tools/map-china/index.html（%.1f KB）' % (len(js.encode('utf-8')) / 1024))
    return True


def main():
    js = build_geo()
    if '--check' in sys.argv:
        html = open(TOOL_HTML, encoding='utf-8').read()
        bi = html.find(BEGIN)
        ei = html.find(END)
        cur = html[bi + len(BEGIN):ei].strip() if bi >= 0 and ei > bi else None
        if cur == js.strip():
            print('[OK] 省界数据一致')
            sys.exit(0)
        print('[ERR] 工具内省界数据与生成结果不一致，请运行 python scripts/gen_china_map.py')
        sys.exit(1)
    sys.exit(0 if inject(js) else 1)


if __name__ == '__main__':
    main()
