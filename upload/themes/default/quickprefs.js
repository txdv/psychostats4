qTimeout = null;
qInit = 0;
qTimeoutSeconds = 2;

function toggle_quickprefs() {
	q = web.getObj('quickprefs');
	i = web.getObj('quickimg');
	web.hideObj('quickprefs');
	// change the z-index so it's behind EVERYTHING, so we won't see it until AFTER it's positioned
	q.style.zIndex = -1;
	web.setPos('quickprefs', 
		web.getObjPosX(i) - web.getObjWidth(q) + 25,
		web.getObjPosY(i) + 20
	);
	// now bring it in front of EVERYTHING
	q.style.zIndex = 65535;
	// initialize the quickprefs events (only once)
	if (!qInit) {
		qInit = 1;
		q.onmouseover = function () { clearTimeout(qTimeout) };
		q.onmouseout = function () { qTimeout = setTimeout('toggle_quickprefs()', qTimeoutSeconds*1000) };
	}

	// handle auto-closing of the box
	if (q.style.display == 'block') {
		// auto-close the window after the mouse leaves
		qTimeout = setTimeout('toggle_quickprefs()', qTimeoutSeconds*1000);
	} else {
		// clear timeout otherwise the box will open again!
		clearTimeout(qTimeout);
	}
}
