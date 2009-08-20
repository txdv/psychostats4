// jquery.cookie.js
jQuery.cookie=function(name,value,options){if(typeof value!='undefined'){options=options||{};if(value===null){value='';options.expires=-1}var expires='';if(options.expires&&(typeof options.expires=='number'||options.expires.toUTCString)){var date;if(typeof options.expires=='number'){date=new Date();date.setTime(date.getTime()+(options.expires*24*60*60*1000))}else{date=options.expires}expires='; expires='+date.toUTCString()}var path=options.path?'; path='+(options.path):'';var domain=options.domain?'; domain='+(options.domain):'';var secure=options.secure?'; secure':'';document.cookie=[name,'=',encodeURIComponent(value),expires,path,domain,secure].join('')}else{var cookieValue=null;if(document.cookie&&document.cookie!=''){var cookies=document.cookie.split(';');for(var i=0;i<cookies.length;i++){var cookie=jQuery.trim(cookies[i]);if(cookie.substring(0,name.length+1)==(name+'=')){cookieValue=decodeURIComponent(cookie.substring(name.length+1));break}}}return cookieValue}return false};

/**
 *
 * @author 	Jason Morriss <lifo101@gmail.com>
 * @version 	1.0 (2009-07-29)
 * @requires 	jQuery v1.3.2 or later
 * @license	http://www.gnu.org/licenses/gpl.html
 * @see 	simpletabs.css for tabs styles.
 * 
 * jQuery simpleTabs plugin.
 * Provides very basic tabs support.
 * Does not automatically apply styles or alter the DOM in any way, the HTML
 * markup must already be present for the tabs and content areas. If JS is
 * disabled only the first tab will be visible, which is reasonable.
 *
 * Example:
 * 
 * $('div.tabs').simpleTabs();
 * 
 * <div class="tabs">
 *	<ul class="tabs-nav">
 *		<li class="tabs-selected"><a ref="#tab1">Tab1</a></li>
 *		<li><a ref="#tab2">Tab2</a></li>
 *	</ul>
 *	<div id="tab1" class="tabs-content">
 *		Tab content here.
 *	</div>
 *	<div id="tab2" class="tabs-content tabs-hide">
 *		Tab content here.
 *	</div>
 * </div>
 *
 */
(function($) {
	$.fn.simpleTabs = function(options) {
		var opt = $.extend({}, $.fn.simpleTabs.defaults, options);
		this.each(function(){
			return init_tabs.apply(this, [ opt ]);
		});

	};

	$.fn.simpleTabs.defaults = {
		navClass: 	'tabs-nav',	// <ul> class for nav links
		navOverClass:	'tabs-hover',	// <li> hover class
		selectedClass: 	'tabs-selected',// <li> selected class
		contentClass: 	'tabs-content',	// <div> content class
		hideClass:	'tabs-hide',	// class to use for hiding 
		onOver: 	null,		// hover over tab label
		onOut: 		null,		// hover off tab label
		onClick: 	null		// Tab is clicked/selected
	};

	function init_tabs(o) {
		var dom = $(this);
		
		// make sure at least one tab is already selected
		// hmmmm, maybe not?
		
		// TODO: Enable $.cookie() support.
		
		// hover states for <li> tabs
		$('ul.' + o.navClass + ' li', dom).hover(
			function(){
				if ($.isFunction(o.onOver)) {
					if (!o.onOver.apply(this, [o])) return;
				}
				$(this).addClass( o.navOverClass );
			},
			function(){
				if ($.isFunction(o.onOut)) {
					if (!o.onOut.apply(this, [o])) return;
				}
				$(this).removeClass( o.navOverClass );
			}
		);

		// live click handler for tabs. More tabs can be added
		// dynamically thanks to the live() handler.
		$('ul.' + o.navClass + ' li a', dom).live('click', function(e){
			if ($.isFunction(o.onClick)) {
				if (!o.onClick.apply(this, [o])) return false;
			}

			var me = $(this);
			var div = $( me.attr('href') );
			var li = me.parents('li:eq(0)');
			
			// do nothing if the tab is selected already
			if (li.hasClass(o.selectedClass)) {
				return false;
			}

			// hide the content of all other content blocks
			div.siblings('.' + o.contentClass).addClass(o.hideClass);

			// show the newly selected tab
			div.removeClass(o.hideClass);
			
			// select the current tab and de-select the rest
			li	.siblings('li')
				.removeClass(o.selectedClass)
				.end()
				.addClass(o.selectedClass);
			
			// remove link outline
			me.blur();
			
			e.preventDefault();
			return false;
		});
		
		
		return this;
	}
	
})(jQuery);