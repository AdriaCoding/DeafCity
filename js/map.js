$(function() {

    var lmap = L.map('map-map').setView([20, -30], 3); 

    var branchesdef = {
        982: "Deaf Sign Language",
        981: "Family Sign Language",
        979: " Pidgin Sign Language",
        980: "Rural Sign Language",
        978: "Auxiliary Sign Systems"
    };
    
    var layers = {deafcity: []};

    L.tileLayer('https://stamen-tiles.a.ssl.fastly.net/toner/{z}/{x}/{y}.png', {
        attribution: ''
    }).addTo(lmap);
    
    L.HtmlIcon = L.Icon.extend({
        options: {
            /*
            html: (String) (required)
            iconAnchor: (Point)
            popupAnchor: (Point)
            */
        },

        initialize: function (options) {
            L.Util.setOptions(this, options);
        },

        createIcon: function () {
            var div = $('<div class="leaflet-marker-icon">')[0];
            div.innerHTML = this.options.html;
            return div;
        },

        createShadow: function () {
            return null;
        }
    });

    const HTMLIcon = L.HtmlIcon.extend({
        options : {
            html : "<div class=\"deafcity-marker\"></div>",
        }
    });
    
    $("body").on("change", ".map-filter-checkbox", function() {
        var branch = $(this).closest(".map-filter-item").data("branch");
        var checked = $(this).prop("checked");
        layers[branch].forEach(function(layer) {
            if (checked) layer.setOpacity(1);
            else layer.setOpacity(0);
        });
    });
    
    $("body").on("click", ".map-filter-header", function() {
        $(this).closest(".map-filter").toggleClass("expanded");
    });
    $("body").on("click", function(e) {
        if ($(e.target).closest(".map-filter").length==0) {
            $(".map-filter").removeClass("expanded");
        }
    });
    
    L.Control.LanguageFilter = L.Control.extend({
        onAdd: function(map) {
            return $("#map-filter")[0];
        },

        onRemove: function(map) {
            // Nothing to do here
        }
    });

    L.control.languageFilter= function(opts) {
        return new L.Control.LanguageFilter(opts);
    }

    

    $.ajax({
        url: 'data/languages.json?v=4',
        dataType: 'json',
        success: function(data) {
            
            data.features.forEach(function(item, index) {
                if (item.type!="Feature") return;
                
                
                var branch = item.properties.branch;
                if (branch === undefined) return;
                                
                var $filter = $("#map-filter-"+branch);
                if ($filter.length==0) {
                    $filter = $("#map-filter-dummy").clone();
                    $filter.find(".map-filter-icon").append('<img src="' + item.properties.icon + '">');
                    $filter.attr("id", "map-filter-"+branch);
                    $filter.attr("data-branch", branch);
                    var label = branchesdef[branch];
                    if (!label) label = "Unknown";
                    $filter.find(".map-filter-text").html(label);
                    $("#map-filter ul").append($filter);
                    layers[branch] = [];
                }
                
                if (item.geometry.coordinates[0]>180) {
                    data.features[index].geometry.coordinates[0] -= 360;
                }
            });
            
            $("#map-filter").addClass("ready");
            $("#map-filter-dummy").remove();
            
            L.geoJSON(data, {
                onEachFeature: function(feature, layer) {
                    
                    if (layer.setIcon !== undefined) {
                        var map = lmap, size = map.options.icon_size;
                        if (feature.properties.icon_size) {
                            size = feature.properties.icon_size;
                        } else size = 20;
                        layer.setIcon(L.icon({
                            iconUrl: feature.properties.icon,
                            iconSize: [size, size],
                            iconAnchor: [Math.floor(size/2), Math.floor(size/2)],
                            popupAnchor: [0, 0],
                        }));
                        if (feature.properties.zindex) {
                            layer.setZIndexOffset(feature.properties.zindex);
                        }
                        layer.bindTooltip(feature.properties.label == undefined ? feature.properties.language.name : feature.properties.label);
                        layers[feature.properties.branch].push(layer);
                        
                        
                        //map.oms.addMarker(layer);
                        //map.marker_map[feature.properties.language.id] = layer;
                        //layer.bindTooltip(feature.properties.label == undefined ? feature.properties.language.name : feature.properties.label);
                    }
                }
            }).addTo(lmap);
            
            loadDeafCityPoints();
            
            L.control.languageFilter({ position: 'bottomleft' }).addTo(lmap);
        }
    });
    
    var loadDeafCityPoints = function() {
        $.ajax({
            url: 'data/deafcity.json?v='+files_md5.deafcity_json,
            dataType: 'json',
            success: function(data) {
                console.log(data);
                data.forEach(function(item, index) {
                    var marker = L.marker(new L.LatLng( item.coordinates[1], item.coordinates[0]), {icon: new HTMLIcon(), zIndexOffset: 10000});
                    //marker.setZIndexOffset(10000);
                    marker.addTo(lmap);
                    marker.bindTooltip(item.label);
                    layers.deafcity.push(marker);
                });
            }
        });
    };
});
