import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const container = document.getElementById('shipment-map');

if (container) {
    const data = JSON.parse(container.dataset.map);
    const map = L.map(container, { scrollWheelZoom: false });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Popups are built with DOM nodes + textContent so user-supplied text can never inject HTML.
    const popup = (title, lines) => {
        const el = document.createElement('div');
        const strong = document.createElement('strong');
        strong.textContent = title;
        el.appendChild(strong);
        lines.filter(Boolean).forEach((line) => {
            const row = document.createElement('div');
            row.textContent = line;
            el.appendChild(row);
        });
        return el;
    };

    const dot = (latlng, color, radius, content) =>
        L.circleMarker(latlng, {
            radius,
            color: '#ffffff',
            weight: 2,
            fillColor: color,
            fillOpacity: 1,
        })
            .bindPopup(content)
            .addTo(map);

    const origin = [data.origin.lat, data.origin.lng];
    const destination = [data.destination.lat, data.destination.lng];
    const bounds = L.latLngBounds([origin, destination]);

    // Travelled path: origin -> recorded positions (chronological).
    const path = [origin, ...data.points.map((p) => [p.lat, p.lng])];
    if (path.length > 1) {
        L.polyline(path, { color: '#0284c7', weight: 3 }).addTo(map);
    }

    // Remaining route (not drawn once delivered).
    if (!data.delivered) {
        L.polyline([path[path.length - 1], destination], {
            color: '#94a3b8',
            weight: 2,
            dashArray: '6 8',
        }).addTo(map);
    }

    // Tracking points
    data.points.forEach((p) => {
        const latlng = [p.lat, p.lng];
        bounds.extend(latlng);
        dot(latlng, p.exception ? '#dc2626' : '#38bdf8', 5, popup(p.title, [p.description, p.location, p.time]));
    });

    // Origin / destination / current location on top
    dot(origin, '#16a34a', 8, popup('Origin', [data.origin.name]));
    dot(destination, '#dc2626', 8, popup('Destination', [data.destination.name]));

    if (data.current) {
        dot([data.current.lat, data.current.lng], '#0284c7', 10, popup('Current location', [data.current.time]));
    }

    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 10 });
}
