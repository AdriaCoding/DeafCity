(function () {
    var data = window.DEAF_CITY_MAP_DATA;
    if (!data || !data.length) return;

    var map = L.map('city-map-canvas', {
        scrollWheelZoom: false,
        zoomControl: true
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    var bounds = [];

    data.forEach(function (edition) {
        var latlng = [edition.lat, edition.lon];
        bounds.push(latlng);

        var marker = L.circleMarker(latlng, {
            radius: 9,
            fillColor: 'rgb(0, 120, 0)',
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map);

        var count = edition.video_count;
        marker.bindPopup(
            '<strong>' + edition.label + '</strong><br>' +
            count + ' video' + (count !== 1 ? 's' : '')
        );
    });

    map.fitBounds(bounds, { padding: [40, 40] });
}());
