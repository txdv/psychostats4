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
		// ...
	});

	var ll = mapconf.center ? mapconf.center.split(',') : [ 40.317232,-95.339355 ]; 	// Default is US
	map.setCenter(new GLatLng(ll[0],ll[1]), mapconf.zoom ? mapconf.zoom : 4);		// 48.57479,11.425781 - Eurpoe

	// initialize map
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
		var icon = new GIcon();
		icon.image = themeurl + '/img/icons/man32.png';
		icon.shadow = themeurl + '/img/icons/man32.shadow.png';
		icon.iconSize = new GSize(32,32);
		icon.shadowSize = new GSize(59,32);
		icon.iconAnchor = new GPoint(16,32);
		icon.infoWindowAnchor = new GPoint(16,16);

		// add each ip marker to the map
		$('marker', xml).each(function(i){
			var t = $(this);
			var lat = t.attr('lat');
			var lng = t.attr('lng');
			var latlng = lat+','+lng;
			if (markers[latlng]) return;	// don't add the same marker more than once
			markers[latlng] = true;

			// auto center on the first marker, chances are most markers will be surrounding the same area
//			if (i == 0) map.setCenter(new GLatLng(lat, lng), 4);

			// define the point, create the marker and add the icon and event listener for it...
			var point = new GLatLng(t.attr('lat'), t.attr('lng'));
			var marker = new GMarker(point, {icon: icon});
			marker.psinfo = null;
			GEvent.addListener(marker, "click", function() {
				if (marker.psinfo == null) marker.psinfo = makeInfo(t);
				marker.openInfoWindowHtml(marker.psinfo);
			});

			// add the marker to the map
			map.addOverlay(marker);
		});
	});
}

function makeInfo(o) {
	var dom = $('#infowin').clone();
	var plrname = $('.name', dom);
	dom.removeAttr('id').addClass('gmapinfo');
	plrname.text(o.attr('name'));
	plrname.attr('href', plrname.attr('href').replace('id=x', 'id=' + encodeURIComponent( o.attr('plrid') ) ) );
	if (o.attr('icon')) {
		plrname.prepend("<img src='" + iconsurl + '/' + encodeURIComponent( o.attr('icon') ) + "' alt=''/> ");
	}

	$('.rank', dom).html(o.attr('rank') + ' <em>(Skill: ' + o.attr('skill') + ')</em>');
	$('.kills', dom).html(o.attr('kills') + ' <em>(Headshots: ' + o.attr('headshotkills') + ')</em>');
	$('.onlinetime', dom).html(o.attr('onlinetime'));
//	$('.activity', dom).html(o.attr('activity') + '%');
	var bar = $('.pct-bar', dom);
	var pct = o.attr('activity');
	bar.attr('title', "Activity " + pct + "%");
	$('span', bar).css({ width: pct + '%', backgroundColor: '#' + $('#color-' + pct).text() });
	return dom.html();
}

// we simply disable the mousewheel by preventing the default
// the google map code will still get the mousewheel movement event.
function wheel(event){
	if (!event) event = window.event;

	if (event.preventDefault) event.preventDefault();
	event.returnValue = false;
}
