window.onload = function(){
	// WebBoxes shared vars
	var components = $$('.component');
	var marker = new Element('div').addClass('marker').setStyles({'display': 'none'}).injectInside($('content_text'));
	var container = $('container').getChildren()[0].getChildren().getChildren()[0];
	
	// Count and order for each side
	var order = [[],[]];
	var count = [1, 1];
	
	// WebBoxes drag behavior for each
	components.each(function(element, i){
		// Asses initial order
		if(element.getParent().id == 'left-homepage-col'){
			order[0][count[0]] = element.id.substr(9);
			count[0]++;
		}else{
			order[1][count[1]] = element.id.substr(9);
			count[1]++;
		}
		
		// Make each component draggable using the handle
		element.makeDraggable({
			handle: element.getElementsBySelector('.componentHeader')[0],
			'onBeforeStart': function() {
				// Introduce the marking box, change the draging box to absolute
				// The order the next 4 lines seems to be important for it to work right in Firefox
				marker.injectAfter(element).setStyles({'display': 'block', 'height': element.getStyle('height')});
				element.setStyles({'opacity': '0.55', 'z-index': '3', 'width': element.getStyle('width'), 'position': 'absolute'});
				element.injectInside($('content_text'));
				element.setStyles({'top': marker.getCoordinates().top + "px", 'left': marker.getCoordinates().left + "px"});
			},
			'onComplete': function() {
				// Replace the draging webBox
				element.injectBefore(marker).setStyles({'opacity': '1', 'z-index': '1', 'margin': '0 0 10px 0', 'position': 'relative', 'top': '0', 'left': '0'});
				
				// Remove width
				element.setStyle('width', '');
				
				// Remove the marking box
				marker.injectInside($('content_text')).setStyles({'display': 'none'});
				
				// Reassess order
				var count = [1, 1];
				components.each(function(element, i){
					if(element.getParent().id == 'left-homepage-col'){
						order[0][count[0]] = element.id.substr(9);
						count[0]++;
					}else{
						order[1][count[1]] = element.id.substr(9);
						count[1]++;
					}
				});

				// Update the database
				var myAjax = new Ajax('/subject/customisePage.php?column1='+order[0]+'&column2='+order[1]+'&update=components').request();
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