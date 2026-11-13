import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const BADGE = 'inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ';

const STATUS_CLASSES = {
    CREATED: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    BOOKED: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    PICKED_UP: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    IN_TRANSIT: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    DELIVERED: 'bg-green-50 text-green-700 ring-green-600/20',
    CANCELLED: 'bg-gray-100 text-gray-600 ring-gray-500/20',
};

const DELAY_CLASSES = {
    HIGH: 'bg-red-50 text-red-700 ring-red-600/20',
    MEDIUM: 'bg-orange-50 text-orange-700 ring-orange-600/20',
    LOW: 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
};

const STATUS_COLORS = {
    CREATED: '#64748b',
    BOOKED: '#3b82f6',
    PICKED_UP: '#6366f1',
    IN_TRANSIT: '#0284c7',
    DELIVERED: '#16a34a',
    CANCELLED: '#9ca3af',
};

const DELAY_COLORS = { HIGH: '#dc2626', MEDIUM: '#f97316', LOW: '#eab308' };

const FINISHED = ['DELIVERED', 'CANCELLED'];
const REFRESH_MS = 60000;

// All user-supplied text is inserted with textContent, never innerHTML.
function el(tag, className = '', text = null) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null) node.textContent = text;
    return node;
}

const colorFor = (s) => (s.is_delayed && DELAY_COLORS[s.delay_severity]) || STATUS_COLORS[s.status] || '#64748b';

const statusBadge = (s) => el('span', BADGE + (STATUS_CLASSES[s.status] ?? STATUS_CLASSES.CREATED), s.status.replace(/_/g, ' '));

function delayBadge(s) {
    if (s.status === 'CANCELLED') return el('span', 'text-xs text-slate-400', 'N/A');
    if (s.is_delayed) return el('span', BADGE + (DELAY_CLASSES[s.delay_severity] ?? ''), `Delayed \u00b7 ${s.delay_severity}`);
    return el('span', BADGE + 'bg-green-50 text-green-700 ring-green-600/20', 'On time');
}

const app = document.getElementById('tracking-app');

if (app) {
    init(app);
}

function init(app) {
    const $ = (id) => document.getElementById(id);

    const dataUrl = app.dataset.url;
    const listEl = $('track-list');
    const searchEl = $('track-search');
    const statusEl = $('track-status');
    const countEl = $('track-count');
    const updatedEl = $('track-updated');
    const errorEl = $('track-error');
    const detailEl = $('track-detail');
    const replayWrap = $('replay');
    const slider = $('replay-slider');
    const playBtn = $('replay-play');
    const replayInfo = $('replay-info');

    const map = L.map($('tracking-map'), { worldCopyJump: true }).setView([0, 110], 3);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const fleetLayer = L.layerGroup().addTo(map);
    const routeLayer = L.layerGroup().addTo(map);

    let shipments = [];
    let selectedId = null;
    let replayMarker = null;
    let timer = null;

    const selected = () => shipments.find((s) => s.id === selectedId) ?? null;

    // ---------------------------------------------------------------- filtering
    function matches(s) {
        const q = searchEl.value.trim().toLowerCase();

        if (q) {
            const haystack = [s.number, s.customer, s.carrier, s.origin.city, s.destination.city]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            if (!haystack.includes(q)) return false;
        }

        const f = statusEl.value;
        if (f === 'ACTIVE') return !FINISHED.includes(s.status);
        if (f === 'DELAYED') return s.is_delayed;
        if (f) return s.status === f;
        return true;
    }

    // ---------------------------------------------------------------- rendering
    function render(fit) {
        const visible = shipments.filter(matches);
        countEl.textContent = `${visible.length} of ${shipments.length} shipments`;
        renderList(visible);
        renderMarkers(visible);
        if (fit && selectedId === null) fitToMarkers(visible);
    }

    function renderList(visible) {
        listEl.replaceChildren();

        if (visible.length === 0) {
            listEl.appendChild(el('li', 'px-3 py-8 text-center text-sm text-slate-500', 'No shipments match your filters.'));
            return;
        }

        visible.forEach((s) => {
            const li = el('li');
            const button = el('button', 'block w-full px-3 py-3 text-left hover:bg-slate-50' + (s.id === selectedId ? ' bg-sky-50' : ''));
            button.type = 'button';

            const top = el('div', 'flex items-center justify-between gap-2');
            top.appendChild(el('span', 'font-medium text-sky-700', s.number));
            top.appendChild(statusBadge(s));

            const route = el('div', 'mt-1 text-xs text-slate-500', `${s.origin.city} \u2192 ${s.destination.city}`);

            const bottom = el('div', 'mt-2 flex items-center justify-between gap-2');
            bottom.appendChild(delayBadge(s));
            bottom.appendChild(el('span', 'text-xs text-slate-400', s.current ? `Last: ${s.current.time}` : 'No position yet'));

            button.append(top, route, bottom);
            button.addEventListener('click', () => select(s.id, true));
            li.appendChild(button);
            listEl.appendChild(li);
        });
    }

    function renderMarkers(visible) {
        fleetLayer.clearLayers();

        visible.forEach((s) => {
            if (!s.current) return;

            L.circleMarker([s.current.lat, s.current.lng], {
                radius: s.id === selectedId ? 12 : 8,
                color: '#ffffff',
                weight: 2,
                fillColor: colorFor(s),
                fillOpacity: 1,
            })
                .bindTooltip(el('span', '', `${s.number} \u00b7 ${s.status.replace(/_/g, ' ')}`))
                .on('click', () => select(s.id, true))
                .addTo(fleetLayer);
        });
    }

    function fitToMarkers(visible) {
        const points = visible.filter((s) => s.current).map((s) => [s.current.lat, s.current.lng]);
        if (points.length === 0) return;
        if (points.length === 1) {
            map.setView(points[0], 5);
            return;
        }
        map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 6 });
    }

    // ---------------------------------------------------------------- selection
    function select(id, fit = true) {
        selectedId = id;
        const s = selected();
        const visible = shipments.filter(matches);

        renderList(visible);
        renderMarkers(visible);

        if (!s) {
            clearSelection();
            return;
        }

        drawRoute(s, fit);
        showDetail(s);
    }

    function clearSelection() {
        selectedId = null;
        stopReplay();
        routeLayer.clearLayers();
        replayMarker = null;
        detailEl.classList.add('hidden');
    }

    function dot(latlng, color, radius, tip) {
        return L.circleMarker(latlng, { radius, color: '#ffffff', weight: 2, fillColor: color, fillOpacity: 1 })
            .bindTooltip(el('span', '', tip));
    }

    function drawRoute(s, fit) {
        routeLayer.clearLayers();
        replayMarker = null;

        const origin = [s.origin.lat, s.origin.lng];
        const destination = [s.destination.lat, s.destination.lng];
        const positions = s.points.map((p) => [p.lat, p.lng]);
        const path = [origin, ...positions];
        const bounds = L.latLngBounds([origin, destination]);
        positions.forEach((p) => bounds.extend(p));

        if (path.length > 1) {
            L.polyline(path, { color: '#0284c7', weight: 3 }).addTo(routeLayer);
        }

        if (!FINISHED.includes(s.status)) {
            L.polyline([path[path.length - 1], destination], { color: '#94a3b8', weight: 2, dashArray: '6 8' }).addTo(routeLayer);
        }

        s.points.forEach((p) => {
            dot([p.lat, p.lng], p.exception ? '#dc2626' : '#38bdf8', 4, `${p.title} \u00b7 ${p.time}`).addTo(routeLayer);
        });

        dot(origin, '#16a34a', 7, `Origin: ${s.origin.name}`).addTo(routeLayer);
        dot(destination, '#dc2626', 7, `Destination: ${s.destination.name}`).addTo(routeLayer);

        if (s.current) {
            dot([s.current.lat, s.current.lng], colorFor(s), 11, `${s.number} \u00b7 current position`).addTo(routeLayer);
        }

        if (fit) map.fitBounds(bounds, { padding: [50, 50], maxZoom: 8 });
    }

    function showDetail(s) {
        detailEl.classList.remove('hidden');

        const number = $('d-number');
        number.textContent = s.number;
        number.href = s.url;
        $('d-link').href = s.url;

        $('d-badges').replaceChildren(statusBadge(s), delayBadge(s));

        const delay = $('d-delay');
        if (s.is_delayed && s.delay_reason) {
            delay.textContent = s.delay_reason;
            delay.classList.remove('hidden');
        } else {
            delay.classList.add('hidden');
        }

        $('d-route').textContent = `${s.origin.name} \u2192 ${s.destination.name}`;
        $('d-carrier').textContent = s.carrier;
        $('d-cargo').textContent = s.cargo;
        $('d-eta').textContent = s.eta === s.expected ? s.eta : `${s.eta} (expected ${s.expected})`;
        $('d-last').textContent = s.current ? s.current.time : '-';

        const customerRow = $('d-customer-row');
        if (s.customer) {
            $('d-customer').textContent = s.customer;
            customerRow.classList.remove('hidden');
        } else {
            customerRow.classList.add('hidden');
        }

        setupReplay(s);
    }

    // ---------------------------------------------------------------- replay
    function setupReplay(s) {
        stopReplay();

        if (s.points.length === 0) {
            replayWrap.classList.add('hidden');
            return;
        }

        replayWrap.classList.remove('hidden');
        slider.min = 0;
        slider.max = s.points.length - 1;
        slider.value = s.points.length - 1;
        showReplayPoint(s, s.points.length - 1);
    }

    function showReplayPoint(s, index) {
        const p = s.points[index];
        if (!p) return;

        if (replayMarker) {
            replayMarker.setLatLng([p.lat, p.lng]);
        } else {
            replayMarker = L.circleMarker([p.lat, p.lng], {
                radius: 13,
                color: '#0f172a',
                weight: 3,
                fillColor: '#ffffff',
                fillOpacity: 0.9,
            }).addTo(routeLayer);
        }

        const parts = [`${index + 1}/${s.points.length}`, p.time, p.title];
        let text = parts.join(' \u00b7 ');
        if (p.description) text += `: ${p.description}`;
        if (p.location) text += ` (${p.location})`;
        replayInfo.textContent = text;
    }

    function stopReplay() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        playBtn.textContent = 'Play';
    }

    slider.addEventListener('input', () => {
        const s = selected();
        if (!s) return;
        stopReplay();
        showReplayPoint(s, Number(slider.value));
    });

    playBtn.addEventListener('click', () => {
        if (timer) {
            stopReplay();
            return;
        }

        const s = selected();
        if (!s || s.points.length === 0) return;

        let index = Number(slider.value);
        if (index >= s.points.length - 1) index = -1;

        playBtn.textContent = 'Pause';
        timer = setInterval(() => {
            index += 1;
            if (index > s.points.length - 1) {
                stopReplay();
                return;
            }
            slider.value = index;
            showReplayPoint(s, index);
        }, 700);
    });

    // ---------------------------------------------------------------- data loading
    async function load({ fit = false, reselect = false } = {}) {
        try {
            const response = await fetch(dataUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const payload = await response.json();
            shipments = payload.data;

            errorEl.classList.add('hidden');
            updatedEl.textContent = `Updated ${new Date().toLocaleTimeString()}`;
            render(fit);

            if (selectedId !== null) {
                if (!selected()) {
                    clearSelection();
                } else if (reselect && !timer) {
                    select(selectedId, false);
                }
            }
        } catch (error) {
            errorEl.textContent = 'Could not load tracking data. Check your connection; the map will retry automatically.';
            errorEl.classList.remove('hidden');
        }
    }

    searchEl.addEventListener('input', () => render(true));
    statusEl.addEventListener('change', () => render(true));
    $('track-refresh').addEventListener('click', () => load({ reselect: true }));

    load({ fit: true });
    setInterval(() => load({ reselect: true }), REFRESH_MS);
}
