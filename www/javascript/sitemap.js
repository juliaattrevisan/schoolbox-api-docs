var myrules = {
	'.sitemap-openclose' : function(el){
		el.onclick = function() {
			
			var catParts = Sitemap.getCatParts(el.parentNode);
			
			Sitemap.setSelected(catParts.catName);		

			var catContents = $('category_' + catParts.catID);
			if (!catContents || catContents.style.display == 'none') {
				Sitemap.openCat(catParts.insertDiv, catParts.catID);
				Sitemap.openWindow(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/icons/sitemap-collapse.gif';
			} else {
				Sitemap.closeCat(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/icons/sitemap-expand.gif';
			}

		}
	},
	'.sitemap-catname' : function (el) {
		el.onclick = function() {
			if (el.parentNode.className != 'sitemap-category') {
				// normal
				var catDiv = el.parentNode.parentNode;
			} else {
				
				if (el.nextSibling.nextSibling) {
					var catID = el.nextSibling.nextSibling.innerHTML;
				} else if (el.nextSibling) {
					var catID = el.nextSibling.innerHTML;
				} 
				
				var catIDList = $('sitemap-nav').getElementsByTagName('p');
				
				for (var i=0; i< catIDList.length; i++) {
					if (catIDList[i].innerHTML == catID) {
						var catDiv = catIDList[i].parentNode.parentNode;
					}
				}
			}
		
			var catParts = Sitemap.getCatParts(catDiv);
			
			Sitemap.setSelected(catParts.catName);	
			
			var catContents = $('category_' + catParts.catID);
			if (!catContents || catContents.style.display == 'none') {
				Sitemap.openCat(catParts.insertDiv, catParts.catID);
				Sitemap.openWindow(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/icons/sitemap-collapse.gif';
			} else {
				Sitemap.openWindow(catParts.catID);
			}
		},
		el.onmouseover = function () {
			if (el.setStyle) el.setStyle('text-decoration', 'underline');	
		},
		el.onmouseout = function () {
			if (el.setStyle) el.setStyle('text-decoration', 'none');	
		},
		el.onmousedown = function () {
			Sitemap.setSelected(el);	
		}

	},
	'.sitemap-caticon' : function (el) {
		
		el.onmousedown = function() {
			
			var catParts = Sitemap.getCatParts(el.parentNode);

			Sitemap.setSelected(catParts.catName);	

			var catContents = $('category_' + catParts.catID);
			if (!catContents || catContents.style.display == 'none') {
				Sitemap.openCat(catParts.insertDiv, catParts.catID);
				Sitemap.openWindow(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/icons/sitemap-collapse.gif';
			} 	
			el.setStyle('position', 'relative');
		},
		el.onmouseover = function () {
			var catParts = Sitemap.getCatParts(el.parentNode);
			if (catParts.catName.setStyle) catParts.catName.setStyle('text-decoration', 'underline');	
		},
		el.onmouseout = function () {
			
			var catParts = Sitemap.getCatParts(el.parentNode);
			if (catParts.catName.setStyle) catParts.catName.setStyle('text-decoration', 'none');	
			
		}		
	},
	'.sitemap-file' : function (el) {
		
		el.onmousedown = function () {
			
			Sitemap.setSelected((el.childNodes)[3]);	
		}
	}
};	

Behaviour.addLoadEvent(function () { Sitemap.init(); });
Behaviour.register(myrules);	


var Sitemap = {

	init: function () {

		//Sitemap.addDrag();
		
		
	},
	getFiles: function(catID) {
		
		document.body.style.cursor = 'wait';
		
		var url = '/sitemap/file-list/' + catID;
	
		var myAjax = new Ajax(url, {
						  	method: 'get', 
							onSuccess: function () {
								Sitemap.listFiles(this.response.text, catID);
							}
							}).request();
		
		},
	
	listFiles: function (data, catID) {
		
		var div = document.createElement('div');
		div.innerHTML = data;
		div.id = 'window_' + catID;
		var parent = $('sitemap-window');
		
		parent.appendChild(div);
	
		Behaviour.apply();
		RightContext.initialize();
		document.body.style.cursor = '';
		

	},
	openDetails: function (fileID) {
		
		var url = '/sitemap/details/' + fileID;
	
		var myAjax = new Ajax(url, {
								method: 'get', 
								onSuccess: function () {
									Sitemap.showDetails(this.response.text, fileID);
								}
								}).request();		
	},
	showDetails: function (data, fileID) {
		
		var div = document.createElement('div');
		div.innerHTML = data;
		div.id = 'details_' + fileID;
		div.setAttribute('class', 'sitemap-details');
				
		document.body.appendChild(div);

	},
	addDrag: function () {

		$$('.sitemap-category').each(function(o, i){
			
			o.addEvent('mousedown', function(e) {
													  
				e = new Event(e).stop();							  
				
				var clone = this.clone()
					.setStyles(this.getCoordinates())
					.setStyles({'opacity': 0.7, 'position': 'absolute'})
					.addEvent('emptydrop', function(){
						this.remove();
						//drop.removeEvents();			 
					}).inject(document.body);
				
				var drag = clone.makeDraggable({
						droppables: $$('.sitemap-category')
				});
				
				drag.start(e);
			});
			
		    o.addEvents({
				'over': function(el, obj){
					var catParts = Sitemap.getCatParts(this);
					Sitemap.setSelected(this)
					$('debug').value = el;
					
				},
				'leave': function(el, obj){
					//this.setStyle('background-color', '#1d1d20');
				},
				'drop': function(el, obj){
					
				}
			});
		
		});
		
		
	},
	saveHistory: function (catID) {
		var url = '/sitemap.php/save-history/' + catID;
		var myAjax = new Ajax(url, {method: 'get'}).request();	
		
	},
	getDir: function (catDiv, catID) {
		
		document.body.style.cursor = 'wait';
		
		var url = '/sitemap/cat-list/' + catID;
	
		var myAjax = new Ajax(url, {
								method: 'get', 
								onSuccess: function () {
									Sitemap.listDir(catDiv, this.response.text, catID);
								}
								}).request();
		},
	
	listDir: function (catDiv, data, catID) {
		
		var div = document.createElement('div');
		div.innerHTML = data;
		div.id = 'category_' + catID;
		if (catDiv.id != 'sitemap-nav')
			catDiv.style.marginLeft = '30px';
		
		catDiv.appendChild(div);
	
		Behaviour.apply();
		RightContext.initialize();
		document.body.style.cursor = '';

	},

	openCat: function (insertDiv, catID) {
		var catContents = $('category_'+catID);
		if (!catContents) {
			// create
			Sitemap.getDir(insertDiv, catID);
		} else {
			catContents.setStyle('display', 'block');	
		}
		
		
	},
	
		closeCat: function (catID) {
		var catContents = $('category_'+catID);
		if (catContents) {
			catContents.setStyle('display', 'none');
		} 	
		
	},

	openWindow: function (catID) {
	
		var winContainer =  $('sitemap-window');
		
		for (var i=0; i < winContainer.childNodes.length; i++) {
			if ((winContainer.childNodes)[i].setStyle) {
				(winContainer.childNodes)[i].setStyle('display', 'none');
			} else if ((winContainer.childNodes[i]).style) {
				(winContainer.childNodes[i]).style.display	= 'none';
			}
		}
		
		var winContents = $('window_'+catID);
		if (!winContents) {
			// create
			Sitemap.getFiles(catID);
		} else {
			winContents.setStyle('display', 'block');	
		}	
		Sitemap.saveHistory(catID);
	},

	closeWindow: function (catID) {
		var winContents = $('window_'+catID);
		if (winContents) {
			winContents.setStyle('display', 'none');
		} 	
	},

	setSelected: function (catName) {
		if ($('sitemap-selected'))
			$('sitemap-selected').removeAttribute('id');
		catName.setAttribute("id", "sitemap-selected");
	},

	getCatParts: function (catDiv) {
		
		if (catDiv.getElementsByClassName) {
			var openClose = (catDiv.getElementsByClassName("sitemap-openclose"))[0].firstChild;
			var catIcon = (catDiv.getElementsByClassName("sitemap-caticon"))[0].firstChild;
			var catID = (catDiv.getElementsByClassName("sitemap-catid"))[0].innerHTML;
			var catName = (catDiv.getElementsByClassName("sitemap-catname"))[0];
			var insertDiv = (catDiv.getElementsByTagName('div'))[0];
		} else {
			var openClose = catDiv.firstChild.firstChild;
			var catIcon = catDiv.firstChild.nextSibling.firstChild;
			var catID = catDiv.firstChild.nextSibling.nextSibling.firstChild.nextSibling.innerHTML;
			var catName = catDiv.firstChild.nextSibling.nextSibling.firstChild;
			var insertDiv = catDiv.firstChild.nextSibling.nextSibling;
		}
		
		return {'openClose': openClose, 'catIcon': catIcon, 'catID': catID, 'catName': catName, 'insertDiv': insertDiv};
	},
	
	getFileParts: function (fileDiv) {
		if (fileDiv.getElementsByClassName) {
			var fileIcon 	= (fileDiv.getElementsByClassName("sitemap-icon"))[0].firstChild;
			var fileID 		= (fileDiv.getElementsByClassName("sitemap-fileid"))[0].innerHTML;
			var catID 		= (fileDiv.getElementsByClassName("sitemap-catid"))[0].innerHTML;
			var fileName 	= (fileDiv.getElementsByClassName("sitemap-filename"))[0];
			var inline 		= (fileDiv.getElementsByClassName("sitemap-inline"))[0].innerHTML;
		} else {
			
			var fileIcon 	= fileDiv.childNodes[0].firstChild;
			var fileID 		= fileDiv.childNodes[3].innerHTML;
			var catID 		= fileDiv.childNodes[4].innerHTML;
			var fileName 	= fileDiv.childNodes[1];
			var inline 		= fileDiv.childNodes[5].innerHTML;
		}
		
		return {'fileIcon': fileIcon, 'fileID': fileID, 'catID': catID, 'fileName': fileName};
	},
	
	newFile: function (e) {
		
		if ($('sitemap-selected').parentNode.className == 'sitemap-file') {
			var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		} else {
			var fileParts = Sitemap.getCatParts($('sitemap-selected').parentNode.parentNode);
		}
		
		
		window.open('/cms/modResource.php?type=file&cid=' + fileParts.catID, '_self','');	
		return false;
	},
	newURL: function (e) {
		if ($('sitemap-selected').parentNode.className == 'sitemap-file') {
			var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		} else {
			var fileParts = Sitemap.getCatParts($('sitemap-selected').parentNode.parentNode);
		}
		
		window.open('/cms/modResource.php?type=url&cid=' + fileParts.catID, '_self','');	
		return false;
	},
	newCategory: function (e) {
		if ($('sitemap-selected').parentNode.className == 'sitemap-file') {
			var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		} else {
			var fileParts = Sitemap.getCatParts($('sitemap-selected').parentNode.parentNode);
		}
		
		window.open('/cms/modFolder.php?action=add&cid=' + fileParts.catID, '_self','');	
		return false;
	},
	modifyFile: function (e) {
		var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		if (fileParts.fileIcon.src.indexOf('http-url')==-1)
			window.open('/cms/modResource.php?type=modfile&id=' + fileParts.fileID, '_self','');	
		else 
			window.open('/cms/modResource.php?type=modurl&id=' + fileParts.fileID, '_self','');	
		return false;
	},
	deleteFile: function (e) {
		var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		window.open('/cms/delResource.php?fileid=' + fileParts.fileID, '_self','');	
		return false;
	},
	deleteFolder: function (e) {

		if ($('sitemap-selected').parentNode.parentNode.className == 'sitemap-category') {
			var catDiv = $('sitemap-selected').parentNode.parentNode;	
		} else {
			if ($('sitemap-selected').nextSibling.nextSibling) {
				var catID = $('sitemap-selected').nextSibling.nextSibling.innerHTML;
			} else if ($('sitemap-selected').nextSibling) {
				var catID = $('sitemap-selected').nextSibling.innerHTML;
			} 
			
			var catIDList = $('sitemap-nav').getElementsByTagName('p');
			
			for (var i=0; i< catIDList.length; i++) {
				if (catIDList[i].innerHTML == catID) {
					var catDiv = catIDList[i].parentNode.parentNode;
				}
			}
		}
		
		var catParts = Sitemap.getCatParts(catDiv);
		window.open('/cms/modFolder.php?delete=' + catParts.catID, '_self','');	
		return false;
	},
	modifyFolder : function (e) {
		if ($('sitemap-selected').parentNode.parentNode.className == 'sitemap-category') {
			var catDiv = $('sitemap-selected').parentNode.parentNode;	
		} else {
			if ($('sitemap-selected').nextSibling.nextSibling) {
				var catID = $('sitemap-selected').nextSibling.nextSibling.innerHTML;
			} else if ($('sitemap-selected').nextSibling) {
				var catID = $('sitemap-selected').nextSibling.innerHTML;
			} 
			
			var catIDList = $('sitemap-nav').getElementsByTagName('p');
			
			for (var i=0; i< catIDList.length; i++) {
				if (catIDList[i].innerHTML == catID) {
					var catDiv = catIDList[i].parentNode.parentNode;
				}
			}
		}
		
		var catParts = Sitemap.getCatParts(catDiv);
		window.open('/cms/modFolder.php?action=mod&cid=' + catParts.catID, '_self','');	
		return false;
		
	},
	openFile: function (e) {
		
		var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		window.open('/sendframe.php?id='+fileParts.fileID, '_blank','');	
		return false;
	},
	saveAs: function (e) {
		
		var fileParts = Sitemap.getFileParts($('sitemap-selected').parentNode);
		window.open('/send.php?id='+fileParts.fileID+'&save=1', '_blank','');	
		return false;
	},	
	viewHomepage: function (e) {
	
		if ($('sitemap-selected').parentNode.parentNode.className == 'sitemap-category') {
			var catDiv = $('sitemap-selected').parentNode.parentNode;	
		} else {
			if ($('sitemap-selected').nextSibling.nextSibling) {
				var catID = $('sitemap-selected').nextSibling.nextSibling.innerHTML;
			} else if ($('sitemap-selected').nextSibling) {
				var catID = $('sitemap-selected').nextSibling.innerHTML;
			} 
			
			var catIDList = $('sitemap-nav').getElementsByTagName('p');
			
			for (var i=0; i< catIDList.length; i++) {
				if (catIDList[i].innerHTML == catID) {
					var catDiv = catIDList[i].parentNode.parentNode;
				}
			}
		}
	
		var catParts = Sitemap.getCatParts(catDiv);
		window.open('/homepage.php?category=' + catParts.catID, '_self','');	
		return false;
	}

};

function makeMoveable () {

		// WebBoxes shared vars
		var files = $$('.sitemap-file');
		var marker = new Element('div').addClass('marker').setStyles({'display': 'none'}).injectInside($('sitemap'));
		var container = $('sitemap');
		
		// WebBoxes drag behavior for each
		files.each(function(element, i){
		// Giver the handle a cursor
		//(element.getElementsBySelector('.sitemap-icon')[0]).setStyle('cursor', 'move');
		
		// Make each component draggable using the handle
		element.makeDraggable({
			limit: $('sitemap'),
			droppables: $('sitemap'),
			'onBeforeStart': function() {
				// Introduce the marking box, change the draging box to absolute
				// The order the next 4 lines seems to be important for it to work right in Firefox
				marker.injectAfter(element).setStyles({'display': 'block', 'height': element.getStyle('height')});
				element.setStyles({'opacity': '0.55', 'z-index': '3', 'width': element.getStyle('width'), 'position': 'absolute'});
				element.injectInside($('sitemap'));
				element.setStyles({'top': marker.getCoordinates().top + "px", 'left': marker.getCoordinates().left + "px"});
				container = element.parentNode;
				
			},
			'onComplete': function() {
				// Replace the draging webBox
				element.injectBefore(marker).setStyles({'opacity': '1', 'z-index': '1', 'margin': '0 0 10px 0', 'position': 'relative', 'top': '0', 'left': '0'});
				
				// Remove width
				element.setStyle('width', '');
				
				// Remove the marking box
				marker.injectInside($('content_text')).setStyles({'display': 'none'});
				
				// Reassess order
				var count = [0, 0];
				var order = [[],[]];
				var files = $$('.sitemap-file');
				files.each(function(element, i){
					if(element.getParent().id == 'left-homepage-col'){
						order[0][count[0]] = element.id.substr(9);
						count[0]++;
					}else{
						order[1][count[1]] = element.id.substr(9);
						count[1]++;
					}
				});

				// Update the database
				var myAjax = new Ajax('/subject/customisePage.php?cid='+getURLParam('category')+'&column1='+order[0]+'&column2='+order[1]+'&type=components').request();
			},
			'onDrag': function() {
				var mouseX = this.mouse.now.x; var mouseY = this.mouse.now.y;

				 // Work from first column out and top down
				webBoxTargetCol = container[0];
				webBoxTargetDiv = null;

				// X - Which column?
				var catDivs = container.getElementsBySelector('sitemap-category');
				
				catDivs.each(function(element, i){
					if (mouseX > element.getCoordinates().left) webBoxTargetCol = element;
				});

				// Y - If we're half way or more past this webBox then insert it after this one
				webBoxTargetCol.getChildren().each(function(element, i){
					if (mouseY > (element.getCoordinates().top + Math.round(element.getCoordinates().height / 2))) webBoxTargetDiv = element;
				});

				// Place the marker
				if (webBoxTargetDiv == null)
				{
					// On top
					if (webBoxTargetCol.getChildren()[0] != marker) marker.injectTop(webBoxTargetCol);
				}
				else
				{
					// Or after a webBox
					if ((webBoxTargetDiv != marker) && (webBoxTargetDiv != marker.getPrevious())) marker.injectAfter(webBoxTargetDiv);
				}
			}
		});
	});

}
