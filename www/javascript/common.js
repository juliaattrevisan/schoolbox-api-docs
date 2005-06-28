// File		: common.js
// Written	: Nick Doyle, 10/9/2002
// Description	: Common javascript for the portal site

	// This code follows Chapter 11 of "DHTML and CSS for the World Wide Web"
	// by Jason Cranford Teague.

	// First, set up flags.
	// We'll use these later to quickly check what sort of DOM we're using.
if (document.getElementById) {
	// uses W3C standard DOM
	var isDHTML = 1;
	var isLayers = 0;
	var isAll = 0;
	var isId = 1;
} else if (document.all) {
	// uses Microsoft DOM
	var isDHTML = 1;
	var isLayers = 0;
	var isAll = 0;
	var isId = 1;
} else if ((navigator.appName.indexOf('Netscape') != -1) 
	&& (parseInt(navigator.appVersion) == 4)){
	// uses Netscape 4 DOM
	var isDHTML = 1;
	var isLayers = 0;
	var isAll = 0;
	var isId = 1;
} else {
	// uses no DOM (or unknown)
	var isDHTML = 0;
	var isLayers = 0;
	var isAll = 0;
	var isId = 0;
}


	// returns a copy of the object
function findObject( objectId) {
	if (isId) {
		return (document.getElementById(objectId));
	} else if (isAll) {
		return (document.all[objectId]);
	} else if (isLayers) {
		return (document.layers[objectId]);
	} else {
		return (NULL);
	}
}



