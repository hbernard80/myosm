(function () {
    function initMap() {
        const container = document.getElementById('myosm-admin-map');
        if (!container || typeof L === 'undefined') {
            return;
        }

        const latField = document.getElementById('myosm-latitude');
        const lngField = document.getElementById('myosm-longitude');

        let lat = parseFloat(container.dataset.lat);
        let lng = parseFloat(container.dataset.lng);
        const name = container.dataset.name || '';

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            lat = 48.8584;
            lng = 2.2945;
        }

        const map = L.map(container).setView([lat, lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(map);

        const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        if (name) {
            marker.bindPopup(name).openPopup();
        }

        marker.on('dragend', function (event) {
            const position = event.target.getLatLng();
            latField.value = position.lat.toFixed(6);
            lngField.value = position.lng.toFixed(6);
        });

        function updateFromInputs() {
            const newLat = parseFloat(latField.value);
            const newLng = parseFloat(lngField.value);

            if (Number.isNaN(newLat) || Number.isNaN(newLng)) {
                return;
            }

            marker.setLatLng([newLat, newLng]);
            map.setView([newLat, newLng]);
        }

        latField.addEventListener('change', updateFromInputs);
        lngField.addEventListener('change', updateFromInputs);
    }

    document.addEventListener('DOMContentLoaded', initMap);
})();
