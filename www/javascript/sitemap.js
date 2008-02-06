var myrules = {
	'.sitemap-category' : function (el) {
		el.onclick = function() {
			
			var catDiv = el;
			var catID = catDiv.getElementsByClassName("sitemap-catid");
			catID = catID[0].innerHTML;
			
			var catName = catDiv.getElementsByClassName("sitemap-catname");
			if ($('sitemap-selected'))
				$('sitemap-selected').removeAttribute('id');
			catName[0].setAttribute("id", "sitemap-selected");

			var insert = catDiv.getElementsByTagName('div');

			if ($('window_'+openCatID))
				$('window_'+openCatID).setStyle('display', 'none');
			openCatID = catID;	
			
			var icon = catDiv.getElementsByClassName("sitemap-openclose");
			icon = icon[0].firstChild;
			
			var isOpen = $('category_'+catID);
			if (!isOpen) {
				getDir(insert[0], catID);
				icon.src = '/images/icons/sitemap-collapse.gif';
			} else if (isOpen.style.display == 'none') {
				isOpen.style.display = 'block';
				icon.src = '/images/icons/sitemap-collapse.gif';
			} else {
				isOpen.style.display = 'none';
				icon.src = '/images/icons/sitemap-expand.gif';
			}
		
			
			var winOpen = $('window_'+catID);
			if (!winOpen) {
				getFiles(catID);
			} else if (winOpen.style.display == 'none') {
				winOpen.style.display = 'block';
			} else {
				winOpen.style.display = 'none';
			}

		}		
		
	},
	'.sitemap-openclose' : function(el){
		el.onclick = function() {
			
			var catDiv = el.parentNode;
			
			var catID = catDiv.getElementsByClassName("sitemap-catid");
			catID = catID[0].innerHTML;
			
			var catName = catDiv.getElementsByClassName("sitemap-catname");
			if ($('sitemap-selected'))
				$('sitemap-selected').removeAttribute('id');
			catName[0].setAttribute("id", "sitemap-selected");
			
			var icon = el.firstChild;
			//alert(icon);

			var isOpen = $('category_'+catID);
			if (!isOpen) {
				icon.src = '/images/icons/sitemap-collapse.gif';
			} else if (isOpen.style.display == 'none') {
				icon.src = '/images/icons/sitemap-collapse.gif';
			} else {
				icon.src = '/images/icons/sitemap-expand.gif';
			}

		}
	}
};	
var openCatID = 0;

Behaviour.addLoadEvent(init);
Behaviour.register(myrules);	

function init() {
	
	getDir($('sitemap-nav'), 0);
	//getFiles($('sitemap-window'), 0);

	makeMoveable();
	
}

function getFiles(catID) {
	var url = '/sitemap.php?fileList=' + catID;

	var myAjax = new Ajax(url, {
						  	method: 'get', 
							onSuccess: function () {
								listFiles(this.response.text, catID);
							}
							}).request();
	
}

function listFiles(data, catID) {
	
	var div = document.createElement('div');
	div.innerHTML = data;
	div.id = 'window_' + catID;
	var parent = $('sitemap-window');
	
	parent.appendChild(div);

	Behaviour.apply();
	//makeMoveable();
}

function getDir(catDiv, catID) {
	var url = '/sitemap.php?catList=' + catID;

	var myAjax = new Ajax(url, {
						  	method: 'get', 
							onSuccess: function () {
								listDir(catDiv, this.response.text, catID);
							}
							}).request();
}

function listDir(catDiv, data, catID) {
	
	var div = document.createElement('div');
	div.innerHTML = data;
	div.id = 'category_' + catID;
	if (catDiv.id != 'sitemap-nav')
		catDiv.style.marginLeft = '30px';
	
	catDiv.appendChild(div);

	Behaviour.apply();
	//makeMoveable();
}

function makeMoveable () {

		// WebBoxes shared vars
		var files = $$('.sitemap-file');
		var marker = new Element('div').addClass('marker').setStyles({'display': 'none'}).injectInside($('sitemap'));
		var container = $('sitemap');
		
		// WebBoxes drag behavior for each
		files.each(function(element, i){
		// Giver the handle a cursor
		(element.getElementsBySelector('.sitemap-icon')[0]).setStyle('cursor', 'move');
		
		// Make each component draggable using the handle
		element.makeDraggable({
			handle: element.getElementsBySelector('.sitemap-icon')[0],
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
				container.each(function(element, i){
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
