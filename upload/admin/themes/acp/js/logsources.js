$(document).ready(function(){
	$('#protocol').change(change_proto).keyup(change_proto);
	$('#blank').click(change_blank);
	$('#ls-table a.up, #ls-table a.dn').click(move_row);

	change_proto();
	change_blank();
});

// swaps two table TR rows
/*
function move_row(e) {
	// don't do anything if the last request is still pending. 
	// repeated, fast clicks will cause the order to not actually update properly in the database.
	if (!ajax_stopped) return false;

	var a = $(this);
	var is_dn = a.hasClass('dn');
	var row1 = a.parent().parent();	// get the TR of the current row
	var row2 = is_dn ? row1.next() : row1.prev();	// get the row we're moving to
	var row3 = row1.clone(true);
	row1.remove();

	// insert the cloned row into its new position
	if (is_dn) {
		row3.insertAfter(row2);
	} else {
		row3.insertBefore(row2);
	}

	// update the arrows of the rows that were switched
	var tmp = row3.children(':first').html();
	row3.children(':first').html(row2.children(':first').html());
	row2.children(':first').html(tmp);
	// reinstall handlers
	$('a.up, a.dn', row2).click(move_row);
	$('a.up, a.dn', row3).click(move_row);

	// update the zebra stripe
	row2.toggleClass('even');
	row3.toggleClass('even');

	// highlight the moved row
// doesn't work very well
//	var c = row3.css('backgroundColor');
//	row3.animate({ 'backgroundColor': 'lightyellow' }, 'fast').animate({ 'backgroundColor': c }, 'fast');

	// send AJAX request to update database
	var href = a.attr('href');
	var params = href.substring(href.indexOf('?')+1) + '&ajax=1';
	$.get(null, params);

	return false;
}
*/

function change_blank(e) {
	var div = $('#ls-password');
	var blank = $('#blank')[0];
	if (!blank) return;

	if (blank.checked) {
		div.hide();
	} else {
		div.show();
	}
}

function change_proto(e) {
	var proto = $('#protocol')[0];
	if (!proto) return;
	var value = proto.options[ proto.selectedIndex ].value;

	$('div[@id^=ls-]', this.form).show();
	if (proto.selectedIndex < 1 || value == '' || value == 'file') {
		$('#ls-stream,#ls-host,#ls-port,#ls-passive,#ls-username,#ls-blank,#ls-password').hide();
	} else if (value == 'ftp' || value == 'sftp') {
		if (value == 'sftp') $('#ls-passive').hide();
		$('#ls-stream,#ls-recursive').hide();
	} else if (value == 'stream') {
		$('#ls-path,#ls-host,#ls-passive,#ls-username,#ls-blank,#ls-password,#ls-recursive,#ls-skiplast').hide();
	}
}

function confirm_del(e) {
	return window.confirm("Are you sure you want to delete this log source?");
}
