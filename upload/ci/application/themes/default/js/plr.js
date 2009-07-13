$(function(){

	// override all pager <a> tags with a special ajax call that will
	// update its related table w/o reloading the page.
	$('span.pager a').live('click', click_pager);	
	
	$('div.tab-group').tabs({ cookie: { expires: 30 } });
	
});

function click_pager(e) {
	var me = $(this);
	var url = me.attr('href');
	var div = me.parents('div:eq(0)');

	if (!div.length || !div.attr('id')) {
		// short-circuit fail; the parent div was not found so
		// we fallback and let the <a> link work normally.
		return true;
	}

	// the id tells us what table we want to request.
	var id = div.attr('id');
	var table = div.children('table:eq(0)');
	var pager = div.children('.pager');

	$.ajax({
		url: url.replace(/\#.+/, ''),	// (remove trailing anchor from url)
		data: { js: id },
		dataType: 'json',
		beforeSend: null,
		complete: null,
		success: function(o){
			// process result; update the table and pager.
			// todo: make this a 'cool' effect.
			if (o && o.status) {
				table.replaceWith(o.table);
				pager.replaceWith(o.pager);
			} else {
				// reload the url if the ajax call fails
				window.location.href = url;
			}
		}
	});

	e.preventDefault();
	return false;
}