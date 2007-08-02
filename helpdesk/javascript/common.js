// File		: common.js
// Written	: Nick Doyle, 28/6/2002
// Description	: Common javascript for the helpdesk site


	// Used in the onchange event for a select list with an 'other' option.
	// Arguments are references to the select and corresponding text objects
	//
function checkOther( selectJah_other, text_other) {

		// Get selected value (lowercase)
	var selected_value = selectJah_other.options[ selectJah_other.selectedIndex].text.toLowerCase();
	
		// if selected value starts with 'other'
	if( selected_value.search( '^other') != -1) {
		text_other.style.visibility = 'visible';	// show text field
	}
	else {
		text_other.style.visibility = 'hidden';		// else hide text field and erase its value
		text_other.value = '';
	}
}

function toggleDate() {
	var chkToggle = findObject( 'dateNone');
	var divDate = findObject( 'divDate');
	if( chkToggle.checked) {
		divDate.style.visibility = 'hidden';
	}
	else {
		divDate.style.visibility = 'visible';
	}
}

	// Used to e.g. populate the models list when a certain brand is selected
	// Takes values from arrData
	// Purple monkey dishwasher
function filterSelect( selectMaster, selectSecondary, arrData) {
	var selectJah_value;

		// Clear existing list
	if( selectSecondary != undefined) {
		i = selectSecondary.options.length-1;
		for( ; i>0 ; i--) {
			if( selectSecondary.options[i].selected) {
				selectJah_value = selectSecondary.options[i].value;
			}
			selectSecondary.options[i] = null;
		}
	}
		// Re-add models for the selected brand
	i = arrData.length-1;
	//set first one to blank
	//alert(selectJah_value);
	selectSecondary.options[0] = new Option( '', '', false);
	for( j=1; i>0 ; i--) {
		if( arrData[i][2] == selectMaster.value) {
			if( arrData[i][0] == selectJah_value) {
				blnSelected = true;
			}
			else {
				blnSelected = false;
			}
			selectSecondary.options[j] = new Option( arrData[i][1], arrData[i][0], blnSelected, blnSelected);
			j++;
		}
	}
}


function checkKey( event) {
	objPi = findObject( 'divPi');
	if( event.altKey && event.shiftKey && event.ctrlKey) {
		objPi.style.left = screen.width-70;
		objPi.style.top = screen.height-200;
		objPi.style.visibility = 'visible';
		document.sound1.play();
	}
	else {
		objPi.style.visibility = 'hidden';
	}
}


	// Get a reference to the named object anywhere in the DOM (from Macromedia Dreamweaver)
	// When you die, if you get a choice between going to regular heaven or pie heaven, choose pie heaven.
	// It might be a trick, but if it's not, mmmmmmm, boy.
function MM_findObj(n, d) { //v4.0
	var p,i,x;

	if(!d) {
		d = document;
	}
	if((p=n.indexOf("?"))>0&&parent.frames.length) {
		d=parent.frames[n.substring(p+1)].document;
		n=n.substring(0,p);
	}
	if(!(x=d[n])&&d.all) {
		x=d.all[n];
	}
	for (i=0;!x&&i<d.forms.length;i++){
		x=d.forms[i][n];
	}
	for(i=0;!x&&d.layers&&i<d.layers.length;i++){
		x=MM_findObj(n,d.layers[i].document);
	}
	if(!x && document.getElementById){
		x=document.getElementById(n);
	}

	return x;
}



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

function findObject( objectId) {
	// returns a copy of the object
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



