// MapLibre GL loaded on demand from unpkg (pinned version) + OpenFreeMap tiles (free, no key).
export const MAPLIBRE_VERSION = '4.7.1';
export const STYLE_URL = 'https://tiles.openfreemap.org/styles/liberty';
const PRAGUE = [14.43, 50.08];

let loading;
export function loadMapLibre() {
    if (window.maplibregl) return Promise.resolve(window.maplibregl);
    if (loading) return loading;
    loading = new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = `https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.css`;
        document.head.appendChild(css);
        const s = document.createElement('script');
        s.src = `https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.js`;
        s.onload = () => resolve(window.maplibregl);
        s.onerror = reject;
        document.head.appendChild(s);
    });
    return loading;
}

export async function createMap(el, opts = {}) {
    const ml = await loadMapLibre();
    const map = new ml.Map({ container: el, style: STYLE_URL, center: opts.center || PRAGUE, zoom: opts.zoom || 11, attributionControl: { compact: true }, ...opts.extra });
    map.addControl(new ml.NavigationControl({ showCompass: false }), 'top-right');
    return map;
}

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** Search map: clustered price pins synced with the result list. */
export function searchMap(src) {
    return {
        map: null, markers: {}, drawing: false, poly: [], ready: false, error: false,
        async init() {
            try {
                this.map = await createMap(this.$refs.map);
            } catch (e) { this.error = true; return; }
            const data = await (await fetch(src, { headers: { Accept: 'application/json' } })).json();
            this.map.on('load', () => this.render(data));
            if (this.map.loaded()) this.render(data);
            window.addEventListener('card-hover', (e) => this.highlight(e.detail));
            this.map.on('click', (e) => { if (this.drawing) { this.poly.push([e.lngLat.lng, e.lngLat.lat]); this.drawPoly(); } });
        },
        render(data) {
            if (this.ready) return; this.ready = true;
            const map = this.map;
            map.addSource('l', { type: 'geojson', data, cluster: true, clusterRadius: 48, clusterMaxZoom: 14 });
            map.addLayer({ id: 'clusters', type: 'circle', source: 'l', filter: ['has', 'point_count'],
                paint: { 'circle-color': '#3C096C', 'circle-opacity': 0.9, 'circle-stroke-color': '#fff', 'circle-stroke-width': 3,
                    'circle-radius': ['step', ['get', 'point_count'], 18, 10, 24, 50, 32] } });
            map.addLayer({ id: 'cluster-count', type: 'symbol', source: 'l', filter: ['has', 'point_count'],
                layout: { 'text-field': '{point_count_abbreviated}', 'text-size': 13, 'text-font': ['Noto Sans Bold'] }, paint: { 'text-color': '#fff' } });
            map.on('click', 'clusters', async (e) => {
                const f = map.queryRenderedFeatures(e.point, { layers: ['clusters'] })[0];
                const z = await map.getSource('l').getClusterExpansionZoom(f.properties.cluster_id);
                map.easeTo({ center: f.geometry.coordinates, zoom: z });
            });
            const sync = () => this.syncMarkers();
            map.on('moveend', sync); map.on('sourcedata', (e) => { if (e.sourceId === 'l' && e.isSourceLoaded) sync(); });
            if (data.features.length) {
                const b = new maplibregl.LngLatBounds();
                data.features.forEach((f) => b.extend(f.geometry.coordinates));
                map.fitBounds(b, { padding: 60, maxZoom: 14, duration: 0 });
            }
        },
        syncMarkers() {
            const feats = this.map.querySourceFeatures('l').filter((f) => !f.properties.cluster);
            const seen = {};
            feats.forEach((f) => {
                const p = f.properties; seen[p.slug] = true;
                if (this.markers[p.slug]) return;
                const el = document.createElement('button');
                el.type = 'button'; el.className = 'price-pin'; el.textContent = p.short; el.dataset.slug = p.slug;
                el.addEventListener('mouseenter', () => window.dispatchEvent(new CustomEvent('pin-hover', { detail: p.slug })));
                el.addEventListener('mouseleave', () => window.dispatchEvent(new CustomEvent('pin-hover', { detail: null })));
                const pop = new maplibregl.Popup({ offset: 18, closeButton: false, maxWidth: '260px' }).setHTML(
                    `<a href="${esc(p.url)}" class="map-pop">${p.thumb ? `<img src="${esc(p.thumb)}" alt="" loading="lazy">` : ''}<strong>${esc(p.price)}</strong><span>${esc(p.title)}</span><small>${esc(p.place)}</small></a>`);
                this.markers[p.slug] = new maplibregl.Marker({ element: el }).setLngLat(f.geometry.coordinates).setPopup(pop).addTo(this.map);
            });
            Object.keys(this.markers).forEach((k) => { if (!seen[k]) { this.markers[k].remove(); delete this.markers[k]; } });
        },
        highlight(slug) {
            document.querySelectorAll('.price-pin.is-active').forEach((e) => e.classList.remove('is-active'));
            if (slug && this.markers[slug]) this.markers[slug].getElement().classList.add('is-active');
        },
        searchArea() {
            const b = this.map.getBounds();
            const u = new URL(location.href);
            u.searchParams.set('bbox', [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map((n) => n.toFixed(5)).join(','));
            u.searchParams.delete('poly'); u.searchParams.delete('page');
            location.href = u.toString();
        },
        toggleDraw() {
            this.drawing = !this.drawing; this.poly = []; this.drawPoly();
            this.map.getCanvas().style.cursor = this.drawing ? 'crosshair' : '';
        },
        applyPoly() {
            if (this.poly.length < 3) return;
            const u = new URL(location.href);
            u.searchParams.set('poly', this.poly.map((p) => p[0].toFixed(5) + ' ' + p[1].toFixed(5)).join(';'));
            u.searchParams.delete('bbox'); u.searchParams.delete('page');
            location.href = u.toString();
        },
        drawPoly() {
            const gj = { type: 'Feature', geometry: { type: 'Polygon', coordinates: [this.poly.length ? [...this.poly, this.poly[0]] : []] } };
            const s = this.map.getSource('draw');
            if (s) { s.setData(gj); return; }
            this.map.addSource('draw', { type: 'geojson', data: gj });
            this.map.addLayer({ id: 'draw-fill', type: 'fill', source: 'draw', paint: { 'fill-color': '#F72585', 'fill-opacity': 0.12 } });
            this.map.addLayer({ id: 'draw-line', type: 'line', source: 'draw', paint: { 'line-color': '#F72585', 'line-width': 2.5, 'line-dasharray': [2, 1] } });
        },
    };
}

/** Detail map with listing pin, 1 km radius and nearby amenities from Overpass. */
export function detailMap(lat, lng) {
    return {
        amenities: null, loadingPoi: false,
        async init() {
            const io = new IntersectionObserver(async (e) => {
                if (!e[0].isIntersecting) return; io.disconnect();
                const map = await createMap(this.$refs.map, { center: [lng, lat], zoom: 14.5, extra: { cooperativeGestures: true } });
                new maplibregl.Marker({ color: '#F72585' }).setLngLat([lng, lat]).addTo(map);
                const pois = await this.loadPoi();
                const add = () => {
                    map.addSource('poi', { type: 'geojson', data: { type: 'FeatureCollection', features: (pois || []).map((p) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [p.lng, p.lat] }, properties: { c: p.cat } })) } });
                    map.addLayer({ id: 'poi', type: 'circle', source: 'poi', paint: { 'circle-radius': 5, 'circle-stroke-width': 2, 'circle-stroke-color': '#fff',
                        'circle-color': ['match', ['get', 'c'], 'transit', '#3C096C', 'school', '#0EA5E9', 'shop', '#10B981', 'health', '#EF4444', '#F59E0B'] } });
                };
                map.loaded() ? add() : map.on('load', add);
            });
            io.observe(this.$refs.map);
        },
        async loadPoi() {
            this.loadingPoi = true;
            try { const r = await overpass(lat, lng, 1000); this.amenities = summarize(r, lat, lng); return r; }
            catch (e) { this.amenities = false; return []; }
            finally { this.loadingPoi = false; }
        },
    };
}

const CATS = {
    transit: { label: 'MHD zastávky', q: 'node["public_transport"="platform"]' },
    school: { label: 'Školy a školky', q: 'nwr["amenity"~"^(school|kindergarten)$"]' },
    shop: { label: 'Obchody', q: 'nwr["shop"~"^(supermarket|convenience|bakery)$"]' },
    health: { label: 'Lékaři a lékárny', q: 'nwr["amenity"~"^(doctors|pharmacy|clinic|hospital)$"]' },
    park: { label: 'Parky a hřiště', q: 'nwr["leisure"~"^(park|playground)$"]' },
};

/** Overpass API call from the client, cached in localStorage for 7 days. */
export async function overpass(lat, lng, radius) {
    const key = `ovp:${lat.toFixed(3)}:${lng.toFixed(3)}:${radius}`;
    try { const c = JSON.parse(localStorage.getItem(key)); if (c && c.t > Date.now() - 6048e5) return c.d; } catch (e) {}
    const parts = Object.entries(CATS).map(([, c]) => `${c.q}(around:${radius},${lat},${lng});`).join('');
    const body = `[out:json][timeout:20];(${parts});out center 400;`;
    const r = await fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: 'data=' + encodeURIComponent(body), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    if (!r.ok) throw new Error('overpass');
    const j = await r.json();
    const d = j.elements.map((e) => {
        const t = e.tags || {};
        const cat = t.public_transport ? 'transit' : /school|kindergarten/.test(t.amenity) ? 'school' : t.shop ? 'shop' : t.leisure ? 'park' : 'health';
        return { cat, name: t.name || '', lat: e.lat ?? e.center?.lat, lng: e.lon ?? e.center?.lon };
    }).filter((p) => p.lat);
    try { localStorage.setItem(key, JSON.stringify({ t: Date.now(), d })); } catch (e) {}
    return d;
}

function dist(a, b, c, d) {
    const R = 6371e3, r = Math.PI / 180;
    const x = (d - b) * r * Math.cos(((a + c) / 2) * r), y = (c - a) * r;
    return Math.sqrt(x * x + y * y) * R;
}

export function summarize(pois, lat, lng) {
    return Object.entries(CATS).map(([k, c]) => {
        const list = pois.filter((p) => p.cat === k).map((p) => ({ ...p, m: Math.round(dist(lat, lng, p.lat, p.lng)) })).sort((a, b) => a.m - b.m);
        return { key: k, label: c.label, count: list.length, nearest: list[0] || null, walk: list[0] ? Math.max(1, Math.round(list[0].m / 80)) : null };
    });
}

/** Locality heatmap / choropleth of price per m². */
export function heatMap(src) {
    return {
        offer: 'rent', map: null, error: false,
        async init() {
            try { this.map = await createMap(this.$refs.map, { zoom: 11 }); } catch (e) { this.error = true; return; }
            this.map.on('load', () => this.load());
        },
        async load() {
            const r = await (await fetch(src + '?offer=' + this.offer)).json();
            const pts = { type: 'FeatureCollection', features: r.points.map((p) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [p[0], p[1]] }, properties: { v: p[2] } })) };
            const max = Math.max(1, ...r.points.map((p) => p[2]));
            const dis = { type: 'FeatureCollection', features: r.districts.filter((d) => d.median).map((d) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [d.lng, d.lat] }, properties: { ...d, label: d.name + '\n' + d.median.toLocaleString('cs-CZ') + ' Kč/m²' } })) };
            const m = this.map;
            ['heat', 'dis', 'dis-l'].forEach((id) => m.getLayer(id) && m.removeLayer(id));
            ['pts', 'dis'].forEach((id) => m.getSource(id) && m.removeSource(id));
            m.addSource('pts', { type: 'geojson', data: pts });
            m.addSource('dis', { type: 'geojson', data: dis });
            m.addLayer({ id: 'heat', type: 'heatmap', source: 'pts', paint: {
                'heatmap-weight': ['interpolate', ['linear'], ['get', 'v'], 0, 0, max, 1], 'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 10, 25, 15, 60], 'heatmap-opacity': 0.75,
                'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(60,9,108,0)', 0.2, '#5A189A', 0.45, '#9D4EDD', 0.7, '#F72585', 1, '#FFD166'] } });
            m.addLayer({ id: 'dis', type: 'circle', source: 'dis', paint: { 'circle-radius': ['interpolate', ['linear'], ['get', 'count'], 1, 8, 50, 22], 'circle-color': '#12001E', 'circle-opacity': 0.8, 'circle-stroke-color': '#F72585', 'circle-stroke-width': 2 } });
            m.addLayer({ id: 'dis-l', type: 'symbol', source: 'dis', layout: { 'text-field': ['get', 'label'], 'text-size': 11, 'text-offset': [0, 2.2], 'text-font': ['Noto Sans Bold'] }, paint: { 'text-color': '#12001E', 'text-halo-color': '#fff', 'text-halo-width': 1.5 } });
            m.on('click', 'dis', (e) => { location.href = e.features[0].properties.url; });
            m.on('mouseenter', 'dis', () => (m.getCanvas().style.cursor = 'pointer'));
            m.on('mouseleave', 'dis', () => (m.getCanvas().style.cursor = ''));
            if (dis.features.length) { const b = new maplibregl.LngLatBounds(); dis.features.forEach((f) => b.extend(f.geometry.coordinates)); m.fitBounds(b, { padding: 60, maxZoom: 13, duration: 0 }); }
        },
        setOffer(o) { this.offer = o; this.load(); },
    };
}

/** Amenity summary for a point without a map (locality pages). */
export function amenities(lat, lng) {
    return { items: null, async init() { try { this.items = summarize(await overpass(lat, lng, 1000), lat, lng); } catch (e) { this.items = false; } } };
}
