
<!DOCTYPE html>
<html lang="en">
<head>
	<base target="_top">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Geo Analytics</title>
	<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

	<style>
		html, body {
			height: 100%;
			margin: 0;
		}
		.leaflet-container {
			height: 400px;
			width: 600px;
			max-width: 100%;
			max-height: 100%;
		}
	</style>
</head>
<body>
    <div id="map" style="width: 100%; height: 100%;"></div>
    <script type="text/javascript" src="https://leafletjs.com/examples/choropleth/us-states.js"></script>
    <script>
        $(document).ready(function() {
            const map = L.map('map').setView([17.53906249999966, 1.0546279422733278], 2);
            axios.get("{{ route('statamic.cp.geojsonData',[$timerange]) }}")
                .then(function (response) {
                    if(response.data.geojson != null){
                        var geoJsonData = response.data.geojson;
                        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        }).addTo(map);

                        // control that shows state info on hover
                        const info = L.control();

                        info.onAdd = function (map) {
                            this._div = L.DomUtil.create('div', 'info');
                            this.update();
                            return this._div;
                        };

                        info.update = function (props) {
                            const sumValues = obj => Object.values(obj).reduce((a, b) => a + b, 0);
                            var codeStatus = "";
                            if(props){
                                for (const code in props.requests) {
                                    codeStatus += `<span style="font-size: 0.85rem"><b>Status Code (${code})</b>: ${props.requests[code]}</span><br/>`
                                }
                            }
                            const contents = props ?
                                `
                                    <div>
                                        <b style="font-size: 0.95rem; color: #515151">${props.country}</b><br/>
                                        <span style="font-size: 0.8rem"><b>Total</b>: ${sumValues(props.requests)}</span><br/>
                                        ${codeStatus}
                                    </div>
                                ` :
                                `
                                    <div style="font-size: 0.85rem; color: #515151">
                                        <b>Hover over a country</b>
                                    </div>
                                `;
                            this._div.style.backgroundColor = "#ffffffb0";
                            this._div.style.borderRadius = "0.75rem";
                            this._div.style.padding = "0.65rem";
                            this._div.style.margin = "0.75rem";
                            this._div.innerHTML = `
                                ${contents}
                            `;
                        };

                        info.addTo(map);

                        // get color depending on total visits value
                        function getColor(d) {
                            return d > response.data.breakpoints[6] ? '#800026' :
                                d > response.data.breakpoints[5]  ? '#BD0026' :
                                d > response.data.breakpoints[4]  ? '#E31A1C' :
                                d > response.data.breakpoints[3]  ? '#FC4E2A' :
                                d > response.data.breakpoints[2]   ? '#FD8D3C' :
                                d > response.data.breakpoints[1]   ? '#FEB24C' :
                                d > response.data.breakpoints[0]   ? '#FED976' : '#fed9762b';
                        }

                        function style(feature) {
                            return {
                                weight: 2,
                                opacity: 1,
                                color: 'white',
                                dashArray: '3',
                                fillOpacity: 0.7,
                                fillColor: getColor(feature.properties.requests_total)
                            };
                        }

                        function highlightFeature(e) {
                            const layer = e.target;

                            layer.setStyle({
                                weight: 5,
                                color: '#666',
                                dashArray: '',
                                fillOpacity: 0.7
                            });

                            layer.bringToFront();

                            info.update(layer.feature.properties);
                        }

                        /* global geoJsonData */
                        const geojson = L.geoJson(geoJsonData, {
                            style,
                            onEachFeature
                        }).addTo(map);

                        function resetHighlight(e) {
                            geojson.resetStyle(e.target);
                            info.update();
                        }

                        function onEachFeature(feature, layer) {
                            layer.on({
                                mouseover: highlightFeature,
                                mouseout: resetHighlight
                            });
                        }

                        map.attributionControl.addAttribution('Geo Analytics');

                        const legend = L.control({position: 'bottomright'});
                        legend.onAdd = function (map) {

                            const div = L.DomUtil.create('div', 'info legend');
                            const grades = [response.data.breakpoints[0], response.data.breakpoints[1], response.data.breakpoints[2], response.data.breakpoints[3], response.data.breakpoints[4], response.data.breakpoints[5], response.data.breakpoints[6], response.data.breakpoints[7]];
                            const labels = [];
                            let from, to;

                            for (let i = 0; i < grades.length; i++) {
                                from = grades[i];
                                to = grades[i + 1];

                                labels.push(`<div style="background-color:${getColor(from + 1)}; display:inline-block; width: 1rem; height:1rem"></div> ${from}${to ? `&ndash;${to}` : '+'}`);
                            }
                            div.style.backgroundColor = "#ffffffb0";
                            div.style.borderRadius = "0.75rem";
                            div.style.padding = "0.65rem";
                            div.style.margin = "0.75rem";
                            div.innerHTML = labels.join('<br>');
                            return div;
                        };

                        legend.addTo(map);
                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        });
    </script>
</body>
</html>
