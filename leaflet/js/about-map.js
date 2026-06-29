(function () {
    var data = window.DEAF_CITY_MAP_DATA;
    if (!data || !data.length) return;

    // Split by the Atlantic: lon > -30° is Europe/Africa, lon <= -30° is Americas
    var euroData = data.filter(function (d) { return d.lon > -30; });
    var ameData  = data.filter(function (d) { return d.lon <= -30; });

    var container = document.getElementById('city-map-canvas');

    // ── MAIN MAP: Western Mediterranean, Mercator ──────────────────────────

    var MAIN_W = 960, MAIN_H = 600;
    // Asymmetric: large left pad to include the Atlantic, tight right pad around Europe
    var PAD_LEFT = 230, PAD_RIGHT = 70, PAD_TOP = 55, PAD_BOTTOM = 55;

    var mainProj = d3.geoMercator()
        .scale(400)
        .center([4, 40])
        .translate([MAIN_W / 2, MAIN_H / 2]);

    var mainPath = d3.geoPath().projection(mainProj);

    var x0, x1, y0, y1;
    if (euroData.length) {
        var pts = euroData.map(function (d) { return mainProj([d.lon, d.lat]); });
        x0 = Math.max(0,      d3.min(pts, function (p) { return p[0]; }) - PAD_LEFT);
        x1 = Math.min(MAIN_W, d3.max(pts, function (p) { return p[0]; }) + PAD_RIGHT);
        y0 = Math.max(0,      d3.min(pts, function (p) { return p[1]; }) - PAD_TOP);
        y1 = Math.min(MAIN_H, d3.max(pts, function (p) { return p[1]; }) + PAD_BOTTOM);
    } else {
        x0 = 0; x1 = MAIN_W; y0 = 0; y1 = MAIN_H;
    }

    var vbW = Math.ceil(x1 - x0), vbH = Math.ceil(y1 - y0);
    var stackedMq = window.matchMedia('(max-width: 720px)');

    function applyMapLayout() {
        if (stackedMq.matches) {
            container.style.aspectRatio = '';
            container.classList.add('city-map-canvas--stacked');
            mainSvg.style('height', 'auto');
        } else {
            container.style.aspectRatio = vbW + ' / ' + vbH;
            container.classList.remove('city-map-canvas--stacked');
            mainSvg.style('height', '100%');
        }
    }

    var mainSvg = d3.select('#city-map-canvas')
        .append('svg')
        .attr('viewBox', Math.floor(x0) + ' ' + Math.floor(y0) + ' ' + vbW + ' ' + vbH)
        .attr('preserveAspectRatio', 'xMidYMid meet')
        .style('display', 'block')
        .style('width', '100%')
        .style('height', '100%');

    applyMapLayout();
    if (stackedMq.addEventListener) {
        stackedMq.addEventListener('change', applyMapLayout);
    } else if (stackedMq.addListener) {
        stackedMq.addListener(applyMapLayout);
    }

    // ── INSET MAP: Americas, Natural Earth ────────────────────────────────

    var INSET_W = 240, INSET_H = 200;
    var INSET_PAD_X = 50, INSET_PAD_Y = 40;

    var insetProj = d3.geoNaturalEarth1()
        .scale(115)
        .center([-73, -2])
        .translate([INSET_W / 2, INSET_H / 2]);

    var insetPath = d3.geoPath().projection(insetProj);

    var ix0, ix1, iy0, iy1;
    if (ameData.length) {
        var iPts = ameData.map(function (d) { return insetProj([d.lon, d.lat]); });
        ix0 = Math.max(0,       d3.min(iPts, function (p) { return p[0]; }) - INSET_PAD_X);
        ix1 = Math.min(INSET_W, d3.max(iPts, function (p) { return p[0]; }) + INSET_PAD_X);
        iy0 = Math.max(0,       d3.min(iPts, function (p) { return p[1]; }) - INSET_PAD_Y);
        iy1 = Math.min(INSET_H, d3.max(iPts, function (p) { return p[1]; }) + INSET_PAD_Y);
    } else {
        ix0 = 0; ix1 = INSET_W; iy0 = 0; iy1 = INSET_H;
    }

    var ivbW = Math.ceil(ix1 - ix0), ivbH = Math.ceil(iy1 - iy0);

    var insetSvg = d3.select('#city-map-canvas')
        .append('svg')
        .attr('id', 'city-map-inset')
        .attr('viewBox', Math.floor(ix0) + ' ' + Math.floor(iy0) + ' ' + ivbW + ' ' + ivbH)
        .attr('preserveAspectRatio', 'xMidYMid meet');

    // ── TOOLTIP ────────────────────────────────────────────────────────────

    var tooltip = d3.select('#city-map-canvas')
        .append('div')
        .attr('class', 'map-tooltip')
        .style('display', 'none');

    // ── DRAW ───────────────────────────────────────────────────────────────

    d3.json('/data/world-110m.json').then(function (world) {
        var land    = topojson.feature(world, world.objects.land);
        var borders = topojson.mesh(world, world.objects.countries, function (a, b) { return a !== b; });

        mainSvg.append('path').datum({type: 'Sphere'}).attr('d', mainPath).attr('fill', '#eef2f0');
        mainSvg.append('path').datum(land).attr('d', mainPath).attr('fill', '#d8ddd8');
        mainSvg.append('path').datum(borders).attr('d', mainPath)
            .attr('fill', 'none').attr('stroke', '#fff').attr('stroke-width', 0.5);
        renderMarkers(mainSvg, mainProj, euroData, false);

        insetSvg.append('rect')
            .attr('x', ix0).attr('y', iy0).attr('width', ivbW).attr('height', ivbH)
            .attr('fill', '#eef2f0');
        insetSvg.append('path').datum(land).attr('d', insetPath).attr('fill', '#d8ddd8');
        insetSvg.append('path').datum(borders).attr('d', insetPath)
            .attr('fill', 'none').attr('stroke', '#fff').attr('stroke-width', 0.3);
        renderMarkers(insetSvg, insetProj, ameData, true);
    });

    function renderMarkers(svg, proj, cities, small) {
        var r = small ? 4 : 6;
        cities.forEach(function (edition) {
            var xy = proj([edition.lon, edition.lat]);
            if (!xy) return;

            var g = svg.append('g').attr('class', 'city-pin').style('cursor', 'pointer');

            if (!small) {
                g.append('circle')
                    .attr('cx', xy[0]).attr('cy', xy[1]).attr('r', 10)
                    .attr('fill', 'rgba(0,120,0,0.18)').attr('stroke', 'none');
            }

            g.append('circle')
                .attr('cx', xy[0]).attr('cy', xy[1]).attr('r', r)
                .attr('fill', 'rgb(0,120,0)')
                .attr('stroke', '#fff')
                .attr('stroke-width', small ? 1 : 1.5);

            g.on('mouseover', function () {
                d3.select(this).select('circle:last-child').attr('r', r + 2);
                var count = edition.video_count;
                tooltip.style('display', 'block')
                    .html('<strong>' + edition.label + '</strong><br>' +
                          count + ' video' + (count !== 1 ? 's' : ''));
            })
            .on('mousemove', function (event) {
                var rect = container.getBoundingClientRect();
                tooltip
                    .style('left', (event.clientX - rect.left + 14) + 'px')
                    .style('top',  (event.clientY - rect.top  - 36) + 'px');
            })
            .on('mouseout', function () {
                d3.select(this).select('circle:last-child').attr('r', r);
                tooltip.style('display', 'none');
            });
        });
    }
}());
