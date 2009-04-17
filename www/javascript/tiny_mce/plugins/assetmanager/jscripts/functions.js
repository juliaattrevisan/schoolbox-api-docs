var win = tinyMCE.getWindowArg("window");
var input = tinyMCE.getWindowArg("input");
var res = tinyMCE.getWindowArg("resizable");
var inline = tinyMCE.getWindowArg("inline");

function insertImage() {
	
	var current = document.getElementById('assetmanager_current');
	if (!current) { alert('Please select an image first.'); return false; }
	
	var img = current.getElementsByTagName('img');
	img = img[0];
	
	var input = current.getElementsByTagName('input');
	input = input[0];
	
	var URL = img.src;
	var title = img.alt;
	var win = tinyMCE.getWindowArg("window");
	var desc = input.value;
	
	// insert information now
	win.document.getElementById(tinyMCE.getWindowArg("input")).value = URL;
	win.document.getElementById(tinyMCE.getWindowArg("title")).value = title;
	win.document.getElementById(tinyMCE.getWindowArg("desc")).value = desc;
	
	// for image browsers: update image dimensions
	if (win.getImageData) win.getImageData();
	
	// Preview image 
	if (win.showPreviewImage) win.showPreviewImage(URL);
	
	// Let TinyMCE know that something was modified
	tinyMCE.triggerNodeChange(false);
	
	// close popup window
	tinyMCEPopup.close();
}

function insertFile() {
	var current = document.getElementById('assetmanager_current');
	if (!current) { alert('Please select a file.'); return false; }
	
	
	var input 	= current.getElementsByTagName('input');
	var url_val = input[0].value;
	var title_val = input[1].value;

	
	var URL = url_val;
	var title = title_val;
	var win = tinyMCE.getWindowArg("window");
	
	// insert information now
	win.document.getElementById(tinyMCE.getWindowArg("input")).value = URL;
	//win.document.getElementById('title').value = title;
	input = win.document.getElementsByTagName('input');
	input = input[1];
	input.value = title;
	
	
	// for image browsers: update image dimensions
	if (win.getImageData) win.getImageData();
	
	// Let TinyMCE know that something was modified
	tinyMCE.triggerNodeChange(false);
	
	// close popup window
	tinyMCEPopup.close();	
}
  
function setCurrent(obj) {
	var current = document.getElementById('assetmanager_current');
	if (current) current.removeAttribute('id');
	obj.setAttribute('id', 'assetmanager_current');
}