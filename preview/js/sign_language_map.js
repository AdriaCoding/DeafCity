(function () {
    var canvas = document.getElementById('sign-language-map-canvas');
    var filterRoot = document.getElementById('sign-language-map-filter');
    if (!canvas || typeof L === 'undefined') return;

    var languagesUrl = canvas.getAttribute('data-languages-url') || '/data/languages.json';
    var deafcityUrl = canvas.getAttribute('data-deafcity-url') || '/data/deafcity.json';
    var defaultOffBranches = { 978: true };
    var branchesdef = {
        982: 'Deaf Sign Language',
        980: 'Rural Sign Language',
        978: 'Auxiliary Sign Systems',
        979: 'Pidgin Sign Language',
        981: 'Family Sign Language'
    };
    var layers = { deafcity: [] };
    var worldBounds = L.latLngBounds(L.latLng(-85, -180), L.latLng(85, 180));
    var lmap = L.map(canvas, {
        maxBounds: worldBounds,
        maxBoundsViscosity: 1.0,
        worldCopyJump: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false
    }).setView([20, -30], 3);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
        noWrap: true
    }).addTo(lmap);

    var deafcityIcon = L.divIcon({
        className: 'deafcity-marker-wrap',
        html: '<div class="deafcity-marker"></div>',
        iconSize: [15, 15],
        iconAnchor: [8, 8]
    });

    function dummyTemplate() {
        return document.getElementById('map-filter-dummy');
    }

    function ensureBranchFilter(branch, iconUrl) {
        if (!filterRoot) return;
        var existing = filterRoot.querySelector('[data-branch="' + branch + '"]');
        if (existing) return;
        var dummy = dummyTemplate();
        if (!dummy) return;
        var item = dummy.cloneNode(true);
        item.id = 'map-filter-' + branch;
        item.setAttribute('data-branch', String(branch));
        item.removeAttribute('hidden');
        var iconSlot = item.querySelector('.sign-language-map-filter-icon');
        if (iconSlot && iconUrl) {
            iconSlot.innerHTML = '';
            var img = document.createElement('img');
            img.src = iconUrl;
            img.alt = '';
            iconSlot.appendChild(img);
        }
        var label = branchesdef[branch] || 'Unknown';
        var textSlot = item.querySelector('.sign-language-map-filter-text');
        if (textSlot) textSlot.textContent = label;
        var checkbox = item.querySelector('.map-filter-checkbox');
        if (checkbox) checkbox.checked = !defaultOffBranches[branch];
        var list = filterRoot.querySelector('.sign-language-map-filter-list');
        (list || filterRoot).appendChild(item);
        layers[branch] = [];
    }

    if (filterRoot) {
        filterRoot.addEventListener('change', function (e) {
            var box = e.target;
            if (!box || !box.classList.contains('map-filter-checkbox')) return;
            var item = box.closest('[data-branch]');
            if (!item) return;
            var branch = item.getAttribute('data-branch');
            var checked = box.checked;
            (layers[branch] || []).forEach(function (layer) {
                if (layer.setOpacity) layer.setOpacity(checked ? 1 : 0);
                else if (layer.setStyle) layer.setStyle({ opacity: checked ? 1 : 0 });
            });
        });
    }

    fetch(languagesUrl)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            (data.features || []).forEach(function (item, index) {
                if (item.type !== 'Feature') return;
                var branch = item.properties && item.properties.branch;
                if (branch === undefined) return;
                ensureBranchFilter(branch, item.properties.icon);
                if (item.geometry && item.geometry.coordinates && item.geometry.coordinates[0] > 180) {
                    data.features[index].geometry.coordinates[0] -= 360;
                }
            });
            var dummy = dummyTemplate();
            if (dummy) dummy.remove();

            L.geoJSON(data, {
                onEachFeature: function (feature, layer) {
                    if (typeof layer.setIcon !== 'function') return;
                    var branch = feature.properties.branch;
                    var size = feature.properties.icon_size || 20;
                    layer.setIcon(L.icon({
                        iconUrl: feature.properties.icon,
                        iconSize: [size, size],
                        iconAnchor: [Math.floor(size / 2), Math.floor(size / 2)],
                        popupAnchor: [0, 0]
                    }));
                    if (feature.properties.zindex) {
                        layer.setZIndexOffset(feature.properties.zindex);
                    }
                    var tip = feature.properties.label || (feature.properties.language && feature.properties.language.name) || '';
                    if (tip) layer.bindTooltip(tip);
                    if (!layers[branch]) layers[branch] = [];
                    layers[branch].push(layer);
                    if (defaultOffBranches[branch]) layer.setOpacity(0);
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
                    zIndexOffset: 10000
                });
                marker.addTo(lmap);
                if (item.label) marker.bindTooltip(item.label);
                layers.deafcity.push(marker);
            });
        })
        .then(function () {
            setTimeout(function () { lmap.invalidateSize(); }, 50);
            setTimeout(function () { lmap.invalidateSize(); }, 300);
        })
        .catch(function (err) {
            console.error('sign language map failed', err);
        });
}());
