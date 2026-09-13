(function () {
    var canvas = document.getElementById('sign-language-map-canvas');
    if (!canvas || typeof L === 'undefined') return;

    var PIDGIN_BRANCH = 979;
    var languagesUrl = canvas.getAttribute('data-languages-url') || '/data/languages.json';
    var deafcityUrl = canvas.getAttribute('data-deafcity-url') || '/data/deafcity.json';
    var markerBounds = L.latLngBounds([]);

    var lmap = L.map(canvas, {
        worldCopyJump: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false
    }).setView([20, 10], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
        noWrap: true
    }).addTo(lmap);

    var languageIcon = L.divIcon({
        className: 'sign-language-marker-wrap',
        html: '<div class="sign-language-marker"></div>',
        iconSize: [9, 9],
        iconAnchor: [5, 5]
    });

    var deafcityIcon = L.divIcon({
        className: 'deafcity-marker-wrap',
        html: '<div class="deafcity-marker"></div>',
        iconSize: [15, 15],
        iconAnchor: [8, 8]
    });

    function languageTooltip(feature) {
        var props = feature.properties || {};
        return props.label || props.name || (props.language && props.language.name) || '';
    }

    function deafcityTooltip(item) {
        if (item.label) return item.label;
        var city = item.city || '';
        var code = item.sign_language_code || '';
        if (city && code) return 'DEAF.city ' + String(city).toUpperCase() + ' ' + code;
        return '';
    }

    function fitToVisibleMarkers() {
        if (!markerBounds.isValid()) return;
        lmap.fitBounds(markerBounds, { padding: [16, 16], maxZoom: 4 });
        lmap.setMaxBounds(markerBounds.pad(0.18));
    }

    fetch(languagesUrl)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var kept = {
                type: 'FeatureCollection',
                features: (data.features || []).filter(function (item) {
                    if (item.type !== 'Feature') return false;
                    var branch = item.properties && item.properties.branch;
                    if (branch === PIDGIN_BRANCH) return false;
                    if (item.geometry && item.geometry.coordinates && item.geometry.coordinates[0] > 180) {
                        item.geometry.coordinates[0] -= 360;
                    }
                    return true;
                })
            };

            L.geoJSON(kept, {
                pointToLayer: function (feature, latlng) {
                    return L.marker(latlng, {
                        icon: languageIcon,
                        interactive: true
                    });
                },
                onEachFeature: function (feature, layer) {
                    var tip = languageTooltip(feature);
                    if (tip) layer.bindTooltip(tip);
                    if (layer.getLatLng) markerBounds.extend(layer.getLatLng());
                }
            }).addTo(lmap);

            return fetch(deafcityUrl).then(function (r) { return r.json(); });
        })
        .then(function (cities) {
            if (!cities || !cities.length) return;
            cities.forEach(function (item) {
                if (!item.coordinates) return;
                var marker = L.marker([item.coordinates[1], item.coordinates[0]], {
                    icon: deafcityIcon,
                    zIndexOffset: 10000,
                    interactive: true
                });
                marker.addTo(lmap);
                var tip = deafcityTooltip(item);
                if (tip) marker.bindTooltip(tip);
                markerBounds.extend(marker.getLatLng());
            });
        })
        .then(function () {
            lmap.invalidateSize();
            fitToVisibleMarkers();
            setTimeout(function () {
                lmap.invalidateSize();
                fitToVisibleMarkers();
            }, 50);
            setTimeout(function () {
                lmap.invalidateSize();
                fitToVisibleMarkers();
            }, 300);
        })
        .catch(function (err) {
            console.error('sign language map failed', err);
        });
}());
