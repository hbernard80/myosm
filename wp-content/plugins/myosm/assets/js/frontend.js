document.addEventListener('DOMContentLoaded', () => {
    const maps = document.querySelectorAll('.myosm-map');

    maps.forEach((element) => {
        const lat = parseFloat(element.dataset.lat);
        const lng = parseFloat(element.dataset.lng);
        const name = element.dataset.name || '';

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return;
        }

        const map = L.map(element).setView([lat, lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(map);

        L.marker([lat, lng]).addTo(map).bindPopup(name || undefined);
    });
});
