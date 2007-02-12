// webcore object created by Jason Morriss <stormtrooper@psychostats.com>
// NOTE: these routines are somewhat old but they still work perfectly fine
// NOTE: when creating a new webcore object, it MUST be created within the document BODY tags, otherwise it won't fully work.

var mposx = mposy = 0;		// these mouse positions need to be global (outside of object) in order for event to use them

function webcore() {
  this.version = '1.1';

  // Browsers that support the DOM (all should eventually) ------
  this.isDOM = (document.getElementById) ? true : false;

  // IE type browsers -------------------------------------------
  this.isIE = (document.all) ? true : false;

  // Netscape type browsers (6.0+ too) --------------------------
  this.isNS = (document.getElementById && !document.all);
  if (!this.isNS) this.isNS = (document.layers) ? true : false;

  // accessor fields for visibility styles
  this.show = (this.isIE || this.isDOM) ? 'visible' : 'show';
  this.hide = (this.isIE || this.isDOM) ? 'hidden' : 'hide';

  // extra variables to make things easier (providing cross-browser support)
  this.client = new Object();

  this.client.version = (navigator.appVersion) ? parseInt(navigator.appVersion) : 1;
  if (this.isIE) {
    this.client.width = document.body.clientWidth;
    this.client.height = document.body.clientHeight;
  } else {
    this.client.width = window.innerWidth;
    this.client.height = window.innerHeight;
  }
  this.client.screenWidth = (screen.width) ? screen.width : 0;
  this.client.screenHeight = (screen.height) ? screen.height : 0;
  this.client.colorDepth = (screen.colorDepth) ? screen.colorDepth : screen.pixelDepth;

  // If browser is fully DHTML compatable?
  this.isDHTML = (this.client.version >= 4 && (this.isIE || this.isNS));

  // Client document offset relative to their scrolled area
  this.client.getXofs = function() {return (document.body) ? document.body.scrollLeft : (window.pageXOffset) ? window.pageXOffset : 0};
  this.client.getYofs = function() {return (document.body) ? document.body.scrollTop : (window.pageYOffset) ? window.pageYOffset : 0};

  // capture mouse movement. Note, mouse coordinates are always relative to the SCROLLED document
  this.client.getMouseX = function() {return mposx};
  this.client.getMouseY = function() {return mposy};

  // capturing the mouse movement takes up CPU, so only start it when you actually need it.
  this.startMouseCapture = start_mousecapture;
  this.stopMouseCapture = function() {document.onmousemove = null};

  // DHTML ROUTINES
  this.getObj = get_object;	// returns the named object
  this.getObjWidth = get_objwidth;
  this.getObjHeight = get_objheight;
  this.getObjPosX = get_objposx;
  this.getObjPosY = get_objposy;
  this.hideObj = hide_object;	// hide/show an element
  this.toggleVis = hide_object;	// alias for hideObj
  this.setAttr = set_attr;	// sets any element attribute to the value given
  this.setPos = set_pos;	// sets the position of the element on the screen
  this.write = _write;		// writes text into an HTML element

  // PSYCHOSTATS
  this.conf_change = _conf_change;
  this.toggle_box = _toggle_box;
  this.toggle_all_box = _toggle_all_box;
  this.open_box = _open_box;
  this.close_box = _close_box;
  this.save_opt = _save_opt;
  this._lastbox = -1;

}

// this does not work for NS4 (why would anyone still be using that?)
function _write(text,id) {
	o = this.getObj(id);
	if (!o) return false;
	if (document.getElementById) o.innerHTML = '';	// IE5.1 (mac) bug. Must clear the text first
	o.innerHTML = text;
	return false;
}

function set_attr(name,attr,val) {
  if (!this.isDHTML) return;
  var o = this.getObj(name);
  eval('o.style.' + attr + " = '" + val + "'");
}

function set_pos(name,x,y) {
  if (!this.isDHTML) return;
  var o = this.getObj(name);
  o.style.left = x;		// getObj() makes this possible w/o any browser detection here...
  o.style.top = y;
}

function hide_object() {	// name, hiddenFlag. If no hidden flag, then its toggled automatically
  if (!this.isDHTML) return;
  var name = arguments[0];
  var o = this.getObj(name);
  var toggle;
  if (arguments.length >= 2) {
    toggle = arguments[1];		// set visibility expicitly
  } else {
//    toggle = (o.style.visibility == this.hide) ? 0 : 1;
    toggle = (o.style.display == 'none') ? 0 : 1;
  }
//  o.style.visibility = (toggle) ? this.hide : this.show;
  o.style.display = (toggle) ? 'none' : 'block';
}

function get_object(name) {
  var e;
  if (document.getElementById) {
    e = document.getElementById(name);
  } else if (document.all) {
    e = document.all[name];
  } else if (document.layers) {
    e = getObjNN4(document,name);
    if (e) e.style = e;		// so all 3 browser methods will be the same
  } else {
    e = new Object;		// empty object
    e.style = e;
  }
  return e;
}

function get_objwidth(o) {
  var i = 0;
  if (!o) return;
  if (this.isDOM) {
    i = o.offsetWidth;
  } else {
    i = o.clip.width;
  }
  return i;
}
function get_objheight(o) {
  var i = 0;
  if (!o) return;
  if (this.isDOM) {
    i = o.offsetHeight;
  } else {
    i = o.clip.height;
  }
  return i;
}
function get_objposx(o) {
  if (!o) return 0;
  var curleft = 0;
  if (this.isDOM) {
    while (o.offsetParent) {
      curleft += o.offsetLeft;
      o = o.offsetParent;
    }
  } else if (document.layers) {		// Netscape 4
    curleft += o.x;
  }
  return curleft;
}
function get_objposy(o) {
  if (!o) return 0;
  var curtop = 0;
  if (this.isDOM) {
    while (o.offsetParent) {
      curtop += o.offsetTop;
      o = o.offsetParent;
    }
  } else if (document.layers) {
    curtop += o.y;
  }
  return curtop;
}

// works with nested layers in NN4
function getObjNN4(obj,name) {
  var x = obj.layers;
  var thereturn, tmp;
  for (var i = 0; i < x.length; i++) {
    if (x[i].id == name) {
      thereturn = x[i];
    } else if (x[i].layers.length) {
      tmp = getObjNN4(x[i], name);
    }
    if (tmp) thereturn = tmp;
  }
  return thereturn;
}

// these 2 browser detects (not obj detects) are required for the following function, since mouse movement events SUCK ASS
var isOpera = (navigator.userAgent.indexOf('Opera') != -1);
var isIE = (!isOpera && navigator.userAgent.indexOf('MSIE') != -1);
//var isIE = (document.all) ? true : false;
function event_capturemouse(e) {
  if (!e) var e = window.event;
  if (e.pageX || e.pageY) { 
    mposx = e.pageX;
    mposy = e.pageY;
  } else if (e.clientX || e.clientY) {
    mposx = e.clientX;
    mposy = e.clientY;
    if (isIE) { 
      mposx += parseInt(document.body.scrollLeft);
      mposy += parseInt(document.body.scrollTop);
    }
  }
  return true;
}

function start_mousecapture() {
  if (document.captureEvents) document.captureEvents(Event.MOUSEMOVE);
  document.onmousemove = event_capturemouse;
}

// ------------------------------------------------------------------------------------
// PsychoStats specific javascript functions

function _toggle_box(name, open, save) {
	var box = this.getObj('box_' + name + '_frame');
	var img = this.getObj('box_' + name + '_img');
	var toggle = (box.style.display == 'none') ? 1 : 0;
	var prev = toggle;
	if (open != null) toggle = open ? 1 : 0;
	if (save == null) save = 1;
	box.style.display = toggle ? 'block' : 'none';

	if (prev == toggle) {
		if (img.src.indexOf('minus.') != -1) {
			img.src = img.src.replace(/minus\./, 'plus.');
		} else if (img.src.indexOf('plus.') != -1) {
			img.src = img.src.replace(/plus\./, 'minus.');
		}
		if (save) this.save_opt(name, toggle);
	}
}

function _save_opt(name, open) {
	img = this.getObj('optimg');
	if (!img) return false;
	var d = new Date();	// tack on a unique time value so the request is never cached
	var src = "opts.php?t=box&o=" + encodeURIComponent(name) + "&v=" + encodeURIComponent(open) + "&z=" + d.getTime();
	img.src = src;
//	window.alert(src);
}

function _toggle_all_box(open, save) {
	var divs = document.getElementsByTagName('div');
	var re = /^box_([\w\d]+)_frame$/;
	for (var i=0; i<divs.length-1; i++) {
		if (!divs[i].id) continue;
		var match = re.exec( divs[i].id );
		if (!match || match.length != 2) continue;
		this.toggle_box(match[1], open, 0);
	}
	return false;
}

function _open_box(i,rel) {
	if (!i) return;
	var box = 'box_'+i;
	var b = this.getObj(box);
	if (!b) return;
	if (!rel) {
		this.setPos(box, this.client.getMouseX()+8, this.client.getMouseY()+16);
	} else {
//		var r = this.getObj(rel);
//		this.setPos(box, this.getObjPosX(r)+this.getObjWidth(r)+3, this.getObjPosY(r));
		this.setPos(box, this.getObjPosX(rel)+this.getObjWidth(rel)+3, this.getObjPosY(rel));
//		window.alert(this.getObjPosX(b) + 'x' + this.getObjPosY(b));
	}
	this.hideObj(box, 0);
	this._lastbox = i;
}

function _close_box() {
	if (this._lastbox < 0) return;
	var box = 'box_' + this._lastbox;
	this.hideObj(box, 1);
	this._lastbox = -1;
}

// called from <input>'s when a configuration option is changed
function _conf_change() {
//	var box = this.getObj('msg-success');
//	if (!box) return;
//	this.hideObj('msg-success', 0);
}


// NON CLASS FUNCTIONS
function addClassName(el, name) {
	el.className += " " + name;
}

function removeClassName(el, name) {
	var i, curList, newList;

	if (el.className == null) return;

	// Remove the given class name from the element's className property.
	newList = new Array();
	curList = el.className.split(" ");
	for (i = 0; i < curList.length; i++) {
		if (curList[i] != name) newList.push(curList[i]);
	}
	el.className = newList.join(" ");
}

