var myrules = {
	'.resources-openclose' : function(el){
		el.onclick = function() {
			
			var catParts = Resources.getCatParts(el.parentNode);
			
			Resources.setSelected(catParts.catName);		

			var catContents = $('category_' + catParts.catID);
			if (!catContents || catContents.style.display == 'none') {
				Resources.openCat(catParts.insertDiv, catParts.catID);
				Resources.openWindow(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/expanded.gif';
			} else {
				Resources.closeCat(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/collapsed.gif';
			}

		}
	},
	'.resources-catname' : function (el) {
		el.onclick = function() {
			
			if (el.parentNode.className != 'resources-category') {
				// normal
				var catDiv = el.parentNode.parentNode;
			} else {

				if (el.nextSibling.nextSibling) {
					var catID = el.nextSibling.nextSibling.innerHTML;
				} else if (el.nextSibling) {
					var catID = el.nextSibling.innerHTML;
				} 
				
				var catIDList = $('resources-nav').getElementsByTagName('p');
				
				for (var i=0; i< catIDList.length; i++) {
					if (catIDList[i].innerHTML == catID) {
						var catDiv = catIDList[i].parentNode.parentNode;
					}
				}
			}
		
			var catParts = Resources.getCatParts(catDiv);
			
			Resources.setSelected(catParts.catName);	
			
			var catContents = $('category_' + catParts.catID);
			if (!catContents || catContents.style.display == 'none') {
				Resources.openCat(catParts.insertDiv, catParts.catID);
				Resources.openWindow(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/icons/resources-collapse.gif';
			} else {
				Resources.openWindow(catParts.catID);
			}
		},
		el.onmouseover = function () {
			if (el.setStyle) el.setStyle('text-decoration', 'underline');	
		},
		el.onmouseout = function () {
			if (el.setStyle) el.setStyle('text-decoration', 'none');	
		},
		el.onmousedown = function () {
			Resources.setSelected(el);	
		}

	},
	'.resources-caticon' : function (el) {
		
		el.onmousedown = function() {
			
			var catParts = Resources.getCatParts(el.parentNode);
	
			Resources.setSelected(catParts.catName);	
	
			var catContents = $('category_' + catParts.catID);
			if (!catContents || catContents.style.display == 'none') {
				Resources.openCat(catParts.insertDiv, catParts.catID);
				Resources.openWindow(catParts.catID);
				if (catParts.openClose) catParts.openClose.src = '/images/icons/resources-collapse.gif';
			} 	
			el.setStyle('position', 'relative');
			
		},
		el.onmouseover = function () {
			var catParts = Resources.getCatParts(el.parentNode);
			if (catParts.catName.setStyle) catParts.catName.setStyle('text-decoration', 'underline');	
		},
		el.onmouseout = function () {
			
			var catParts = Resources.getCatParts(el.parentNode);
			if (catParts.catName.setStyle) catParts.catName.setStyle('text-decoration', 'none');	
			
		}		
	},
	'.resources-file' : function (el) {
		
		el.onmousedown = function () {
			
			Resources.setSelected((el.childNodes)[3]);	
		}
	}
};	

Behaviour.addLoadEvent(function () { Resources.init(); });
Behaviour.register(myrules);	


var Resources = {

	init: function () {

		//Resources.addDrag();
		
	},
	pause: function (millis) {
		var date = new Date();
		var curDate = null;
		
		do { curDate = new Date(); }
		while(curDate-date < millis);
	},
	getFiles: function(catID) {
		
		var url = '/resources/file-list/' + catID;
	
		var myAjax = new Ajax(url, {
						  	method: 'get',
							onSuccess: function () {
								Resources.listFiles(this.response.text, catID);
							}
							}).request();
		
		},
	
	listFiles: function (data, catID) {
		
		var div = document.createElement('div');
		div.innerHTML = data;
		div.id = 'window_' + catID;
		var parent = $('resources-window');
		
		parent.appendChild(div);
	
		Behaviour.apply();
		RightContext.initialize();
		

	},
	openDetails: function (fileID, e) {
		var url = '/resources/details/' + fileID;

		var win = new Windoo({
			resizable: false,
			destroyOnClose: false,
			title: 'Properties',
			width: 220,
			height: 360,
			theme: Windoo.Themes.alphacube
		});

		// clear window content, display loading icon and ensure window is shown
		win.setHTML('<div><img src="/images/resources/loading.gif" class="loading" alt="loading..."/></div>').show();

		// send ajax request
		new Windoo.Ajax(url, {
			evalScripts: true,
			window: win
		}).request();

	},
	addDrag: function () {

		$$('.resources-category').each(function(o, i){
			
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
						droppables: $$('.resources-category')
				});
				
				drag.start(e);
			});
			
		    o.addEvents({
				'over': function(el, obj){
					var catParts = Resources.getCatParts(this);
					Resources.setSelected(this)
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
		var url = '/resources/save-history/' + catID;
		var myAjax = new Ajax(url, {method: 'get'}).request();	
		
	},
	getDir: function (catDiv, catID) {
		
		var parentDiv = catDiv.parentNode;
		var catParts = Resources.getCatParts(parentDiv);
		
		var url = '/resources/cat-list/' + catID;
	
		var myAjax = new Ajax(url, {
								method: 'get', 
								onSuccess: function () {
									Resources.listDir(catDiv, this.response.text, catID);
								}
								}).request();
	},
	
	listDir: function (catDiv, data, catID) {
		
		var div = document.createElement('div');
		div.innerHTML = data;
		div.id = 'category_' + catID;
		div.style.display = 'none';
		
		if (catDiv.id != 'resources-nav')
			catDiv.style.marginLeft = '30px';
		
		catDiv.appendChild(div);
	
		div.style.display = 'block';
	
		Behaviour.apply();
		RightContext.initialize();

		var catParts = Resources.getCatParts(catDiv.parentNode);
		//catParts.catIcon.src = "/images/mime/folder_blue_full.png";
	},

	openCat: function (insertDiv, catID) {
		var catContents = $('category_'+catID);
		if (!catContents) {
			// create
			
			Resources.getDir(insertDiv, catID);
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
	
		var winContainer =  $('resources-window');
		
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
			Resources.getFiles(catID);
		} else {
			winContents.setStyle('display', 'block');	
		}	
		Resources.saveHistory(catID);
	},

	closeWindow: function (catID) {
		var winContents = $('window_'+catID);
		if (winContents) {
			winContents.setStyle('display', 'none');
		} 	
	},

	setSelected: function (catName) {
		if ($('resources-selected'))
			$('resources-selected').removeAttribute('id');
		catName.setAttribute("id", "resources-selected");
	},

	getCatParts: function (catDiv) {
		
		if (catDiv.getElementsByClassName) {
			var openClose = (catDiv.getElementsByClassName("resources-openclose"))[0].firstChild;
			var catIcon = (catDiv.getElementsByClassName("resources-caticon"))[0].firstChild;
			var catID = (catDiv.getElementsByClassName("resources-catid"))[0].innerHTML;
			var catName = (catDiv.getElementsByClassName("resources-catname"))[0];
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
			var fileIcon 	= (fileDiv.getElementsByClassName("resources-icon"))[0].firstChild;
			var fileID 		= (fileDiv.getElementsByClassName("resources-fileid"))[0].innerHTML;
			var catID 		= (fileDiv.getElementsByClassName("resources-catid"))[0].innerHTML;
			var fileName 	= (fileDiv.getElementsByClassName("resources-filename"))[0];
			var inline 		= (fileDiv.getElementsByClassName("resources-inline"))[0].innerHTML;
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
		
		if ($('resources-selected').parentNode.className == 'resources-file') {
			var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		} else {
			var fileParts = Resources.getCatParts($('resources-selected').parentNode.parentNode);
		}
		
		
		window.open('/cms/modResource.php?type=file&cid=' + fileParts.catID, '_self','');	
		return false;
	},
	newURL: function (e) {
		if ($('resources-selected').parentNode.className == 'resources-file') {
			var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		} else {
			var fileParts = Resources.getCatParts($('resources-selected').parentNode.parentNode);
		}
		
		window.open('/cms/modResource.php?type=url&cid=' + fileParts.catID, '_self','');	
		return false;
	},
	newCategory: function (e) {
		if ($('resources-selected').parentNode.className == 'resources-file') {
			var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		} else {
			var fileParts = Resources.getCatParts($('resources-selected').parentNode.parentNode);
		}
		
		window.open('/cms/modFolder.php?action=add&cid=' + fileParts.catID, '_self','');	
		return false;
	},
	modifyFile: function (e) {
		var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		if (fileParts.fileIcon.src.indexOf('http-url')==-1)
			window.open('/cms/modResource.php?type=modfile&id=' + fileParts.fileID, '_self','');	
		else 
			window.open('/cms/modResource.php?type=modurl&id=' + fileParts.fileID, '_self','');	
		return false;
	},
	deleteFile: function (e) {
		var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		window.open('/cms/delResource.php?fileid=' + fileParts.fileID, '_self','');	
		return false;
	},
	deleteFolder: function (e) {

		if ($('resources-selected').parentNode.parentNode.className == 'resources-category') {
			var catDiv = $('resources-selected').parentNode.parentNode;	
		} else {
			if ($('resources-selected').nextSibling.nextSibling) {
				var catID = $('resources-selected').nextSibling.nextSibling.innerHTML;
			} else if ($('resources-selected').nextSibling) {
				var catID = $('resources-selected').nextSibling.innerHTML;
			} 
			
			var catIDList = $('resources-nav').getElementsByTagName('p');
			
			for (var i=0; i< catIDList.length; i++) {
				if (catIDList[i].innerHTML == catID) {
					var catDiv = catIDList[i].parentNode.parentNode;
				}
			}
		}
		
		var catParts = Resources.getCatParts(catDiv);
		window.open('/cms/modFolder.php?delete=' + catParts.catID, '_self','');	
		return false;
	},
	modifyFolder : function (e) {
		if ($('resources-selected').parentNode.parentNode.className == 'resources-category') {
			var catDiv = $('resources-selected').parentNode.parentNode;	
		} else {
			if ($('resources-selected').nextSibling.nextSibling) {
				var catID = $('resources-selected').nextSibling.nextSibling.innerHTML;
			} else if ($('resources-selected').nextSibling) {
				var catID = $('resources-selected').nextSibling.innerHTML;
			} 
			
			var catIDList = $('resources-nav').getElementsByTagName('p');
			
			for (var i=0; i< catIDList.length; i++) {
				if (catIDList[i].innerHTML == catID) {
					var catDiv = catIDList[i].parentNode.parentNode;
				}
			}
		}
		
		var catParts = Resources.getCatParts(catDiv);
		window.open('/cms/modFolder.php?action=mod&cid=' + catParts.catID, '_self','');	
		return false;
		
	},
	openFile: function (e) {
		
		var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		window.open('/sendframe.php?id='+fileParts.fileID, '_blank','');	
		return false;
	},
	saveAs: function (e) {
		
		var fileParts = Resources.getFileParts($('resources-selected').parentNode);
		window.open('/send.php?id='+fileParts.fileID+'&save=1', '_blank','');	
		return false;
	},	
	viewHomepage: function (e) {
	
		if ($('resources-selected').parentNode.parentNode.className == 'resources-category') {
			var catDiv = $('resources-selected').parentNode.parentNode;	
		} else {
			if ($('resources-selected').nextSibling.nextSibling) {
				var catID = $('resources-selected').nextSibling.nextSibling.innerHTML;
			} else if ($('resources-selected').nextSibling) {
				var catID = $('resources-selected').nextSibling.innerHTML;
			} 
			
			var catIDList = $('resources-nav').getElementsByTagName('p');
			
			for (var i=0; i< catIDList.length; i++) {
				if (catIDList[i].innerHTML == catID) {
					var catDiv = catIDList[i].parentNode.parentNode;
				}
			}
		}
	
		var catParts = Resources.getCatParts(catDiv);
		window.open('/homepage.php?category=' + catParts.catID, '_self','');	
		return false;
	}

};

function makeMoveable () {

		// WebBoxes shared vars
		var files = $$('.resources-file');
		var marker = new Element('div').addClass('marker').setStyles({'display': 'none'}).injectInside($('resources'));
		var container = $('resources');
		
		// WebBoxes drag behavior for each
		files.each(function(element, i){
		// Giver the handle a cursor
		//(element.getElementsBySelector('.resources-icon')[0]).setStyle('cursor', 'move');
		
		// Make each component draggable using the handle
		element.makeDraggable({
			limit: $('resources'),
			droppables: $('resources'),
			'onBeforeStart': function() {
				// Introduce the marking box, change the draging box to absolute
				// The order the next 4 lines seems to be important for it to work right in Firefox
				marker.injectAfter(element).setStyles({'display': 'block', 'height': element.getStyle('height')});
				element.setStyles({'opacity': '0.55', 'z-index': '3', 'width': element.getStyle('width'), 'position': 'absolute'});
				element.injectInside($('resources'));
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
				var files = $$('.resources-file');
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
				var catDivs = container.getElementsBySelector('resources-category');
				
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
