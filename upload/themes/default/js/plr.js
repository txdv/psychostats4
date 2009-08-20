$(function(){

	// override all table header sorts and pager <a> tags with a special
	// ajax call that will update its related table w/o reloading the page.
	$('span.pager a').live('click', click_table);	
	$('table.neat tr.hdr th a').live('click', click_table);
	
	// enable cookie support for tabs to make them persistent.
	$('div.tabs').simpleTabs({ cookie: { expires: 30 } });
	
});

function click_table(e) {
	var me = $(this);
	var url = me.attr('href');
	var div = me.parents('div:eq(0)');

	if (!div.length || !div.attr('id') || !url) {
		// short-circuit fail; the parent div was not found so
		// we fallback and let the <a> link work normally.
		return true;
	}

	// the id tells us what table we want to request.
	var id = div.attr('id');
	var table = div.children('table:eq(0)');
	var pager = div.children('.pager');

	// If the blockUI plugin is available block the table which produces
	// a nice little affect while the ajax call is pending.
	var blockui_opts = {};
	if ($.blockUI) {
		blockui_opts.message = 'Loading...',
		blockui_opts.fadeIn = 0;
		blockui_opts.fadeOut = 0;
		blockui_opts.overlayCSS = {
			backgroundColor: '#FFF',
			opacity: 0.6 
		};
	}

	$.ajax({
		url: url.replace(/\#.+/, ''),	// remove trailing anchor from url
		data: { js: id },
		dataType: 'json',
		beforeSend: function(){ if ($.blockUI) div.block(blockui_opts); },
		complete:   function(){ if ($.blockUI) div.unblock(blockui_opts); },
		error: function(o){ window.location.href = url }, // reload on failure
		success: function(o){
			// process result; update the table and pager.
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