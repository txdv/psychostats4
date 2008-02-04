$(document).ready(function(){
	$('#cc').keyup(function(event){
		if (this.value.length != 2) {
			$('#flag-img')[0].src = $('#blank-icon')[0].src;
		} else {
			var url = flags_url + '/' + this.value.toLowerCase() + '.png';
			// if the img exists then we set the img source to the url.
			// this prevents IE from causing a broken img appearing
			// when an unknown CC is entered.
			$.get(url, function(){
				$('#flag-img')[0].src = url;
			});
		}
	});

	$('#btn-delete').click(function(){
		return window.confirm(delete_message);
	});


});

var icons_loading = null; // true when the AJAX request is pending a response
var icons_loaded = null;  // holds the loaded icon data
function toggle_gallery() {
	if (icons_loading) return;
	icons_loading = true;
	var gallery = $('#icon-gallery');
	gallery.slideToggle('fast');
	if (!icons_loaded) {
		$.get('ajax/iconlist.php', { t: 'img' }, function(data){
			icons_loaded = data;
			gallery.html(click_icon_message + "<br/>" + data);
			$('img[@id^=icon]', gallery).click(change_icon);
			icons_loading = false;
		});
	} else {
		icons_loading = false;
	}
}

function change_icon(event, blank) {
	if (!blank) {
		$('#icon-img')[0].src = this.src;
		$('#icon-input').val(decodeURI(this.alt));
	} else {
		$('#icon-img')[0].src = $('#blank-icon')[0].src;
		$('#icon-input').val('');
	}
	$('#icon-gallery').slideToggle('fast');
}

