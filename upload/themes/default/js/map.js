var map;
$(function(){
	$('body').unload(function(){ if (window.GUnload) GUnload(); });
	if (GBrowserIsCompatible()) init_google();
});

function enable_wheel() {
	if (window.addEventListener) window.removeEventListener('DOMMouseScroll', wheel, false);
	window.onmousewheel = document.onmousewheel = undefined;
}
function disable_wheel() {
	if (window.addEventListener) window.addEventListener('DOMMouseScroll', wheel, false);
	window.onmousewheel = document.onmousewheel = wheel;
}

function init_google() {
	// the window will scroll if we don't disable the wheel while hovering over the map
	$('#map').hover(disable_wheel, enable_wheel);

	map = new GMap2(document.getElementById("map"), {
	});
	map.setCenter(new GLatLng(40.317232,-95.339355), 4);	// US
//	map.setCenter(new GLatLng(48.57479,11.425781), 4);	// Europe

 	var mapControl = new GMapTypeControl();
	map.setMapType(G_SATELLITE_MAP);
	map.addMapType(G_PHYSICAL_MAP);
	map.addControl(mapControl);
	map.addControl(new GLargeMapControl());

	map.enableContinuousZoom();
	map.enableScrollWheelZoom();

	// start adding markers to the map
	var markers = {};
	$.get('overview.php', { ip: 100 }, function(xml) {
		// add each ip marker to the map
		$('marker', xml).each(function(i){
			var t = $(this);
			var lat = t.attr('lat');
			var lng = t.attr('lng');
			var latlng = lat+','+lng;
			if (markers[latlng]) return;	// don't add the same marker more than once
			markers[latlng] = true;
			var m = new GLatLng(t.attr('lat'), t.attr('lng'));
			map.addOverlay(new GMarker(m));
			// auto center on the first marker, chances are most markers will be surrounding the same area
			if (i == 0) map.setCenter(new GLatLng(lat, lng), 4);
		});
	});
}

// we simply disable the mousewheel by preventing the default
// the google map code will still get the mousewheel movement event.
function wheel(event){
	if (!event) event = window.event;

	if (event.preventDefault) event.preventDefault();
	event.returnValue = false;
}
