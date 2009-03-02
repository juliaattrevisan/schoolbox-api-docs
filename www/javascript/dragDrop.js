function getURLParam(strParamName){
	var strReturn = "";
	var strHref = window.location.href;
	if ( strHref.indexOf("?") > -1 ){
	var strQueryString = strHref.substr(strHref.indexOf("?")).toLowerCase();
	var aQueryString = strQueryString.split("&");
	for ( var iParam = 0; iParam < aQueryString.length; iParam++ ){
		if (aQueryString[iParam].indexOf(strParamName + "=") > -1 ){
			var aParam = aQueryString[iParam].split("=");
			strReturn = aParam[1];
			break;
		}
	}
	}
	return strReturn;
} 

document.addEvents({
	'keydown': function (e) {
		if (e.shiftKey) {
			$$('.manage h2').each(function (o) { 
				o.setStyle('display', 'block');
			});
		}
	},
	'keyup': function (e) {
		$$('.manage h2').each(function (o) { 
			o.setStyle('display', 'none');
		});
	}
});

Behaviour.addLoadEvent(function () { initDrag(); });

var initDrag = function(){
	// WebBoxes shared vars
	var components = $$('.box');
	var container = $$('#content div')[0];
	var marker = new Element('div').addClass('marker').setStyles({'display': 'none'}).injectInside(container);
	
	// WebBoxes drag behavior for each
	components.each(function(element, i){
		
		// Giver the handle a cursor
		(element.getChildren('h2')[0]).setStyle('cursor', 'move');
		
		// Make each component draggable using the handle
		element.makeDraggable({
			handle: element.getChildren("h2")[0],
			'onStart' :  function () {
				//if (!allowMove) element.removeEvent();
				
			},
			'onBeforeStart': function() {

				// Introduce the marking box, change the draging box to absolute
				// The order the next 4 lines seems to be important for it to work right in Firefox
				marker.injectAfter(element).setStyles({'display': 'block', 'height': element.getStyle('height')});
				element.setStyles({'opacity': '0.55', 'z-index': '3', 'width': element.getStyle('width'), 'position': 'absolute'});
				element.injectInside(container);
				element.setStyles({'top': marker.getCoordinates().top + "px", 'left': marker.getCoordinates().left + "px"});
			},
			'onComplete': function() {
				// Replace the draging webBox
				element.injectBefore(marker).setStyles({'opacity': '1', 'z-index': '1', 'position': 'relative', 'top': '0', 'left': '0'});
				
				// Remove width
				element.setStyle('width', '');
				
				// Remove the marking box
				marker.injectInside($('content')).setStyles({'display': 'none'});
				
				// Reassess order
				var count = [0, 0];
				var order = [[],[]];
				var components = $$('.box');
				components.each(function(element, i){
					
					if(element.getParent().hasClass('left')){
						order[0][count[0]] = element.id.substr(9);
						count[0]++;
					}else{
						order[1][count[1]] = element.id.substr(9);
						count[1]++;
					}
				});
				
				// Update the database
				var myAjax = new Ajax('/homepage/configForm.php', {
					data: 'cid='+categoryID+'&column1='+order[0]+'&column2='+order[1]+'&type=components'
				}).request();
			},
			'onDrag': function() {
				var mouseX = this.mouse.now.x; var mouseY = this.mouse.now.y;

				 // Work from first column out and top down
				webBoxTargetCol = container.getChildren(".left");
				webBoxTargetDiv = null;

				// X - Which column?
				$$(".left", ".right").each(function(element, i){
												 
					if (mouseX > element.getCoordinates().left) webBoxTargetCol = element;
				});
				
				// Y - If we're half way or more past this webBox then insert it after this one
				webBoxTargetCol.getChildren(".box").each(function(element, i){
					if (element.hasClass("box")) {
						if (mouseY > (element.getCoordinates().top + Math.round(element.getCoordinates().height / 2))) webBoxTargetDiv = element;
					}
				});
				
				// Place the marker
				if (webBoxTargetDiv == null)
				{
					
					// On top
					if (webBoxTargetCol.getChildren()[0] != marker) marker.inject(webBoxTargetCol, 'top');
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
