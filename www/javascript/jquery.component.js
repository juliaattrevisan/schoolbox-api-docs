(function($) {

	$(document).ready(function () {
		
		/* add drag/drop */
		/*
		var placeholder = $('<div></div>').addClass('marker').attr("id", "placeholder").appendTo($('div.homepage')).hide();
		
		$('div.box').each(function () {
			var node = $(this);
			$(this).draggable({
				cursor: 'move',
				handle: 'h2',
				helper: 'clone',
				start: function (event, ui) {
					// add placeholder
					$('#placeholder').after(node).show();
				},
				drag: function (event, ui) {
					
					ui.helper.width(node.width()).height(node.height());
					
					
					var targetColumn = null, targetDiv = null;
					
					if (event.pageX < ( $('div.left').offset().left + $('div.left').width())) {
						// left
						
						targetColumn = $('div.left');
					} 
					else {
						
						targetColumn = $('div.right');
					}
					
					$(targetColumn).find('div.box').each(function () {
						console.log($(this));
						console.log('x');
						if (event.pageY > ($(this).offset().top + Math.round(this.height() / 2)))
							targetDiv = this;
					});
					
					if (targetDiv != null)
						$('#placeholder').insertAfter(targetDiv);
					else
						$('#placeholder').prependTo($(targetColumn));
				}
			});
		});
		*/
		
		/*,
		$('div.homepage').sortable({
			cursor: 'move',
			distance: 0,
			dropOnEmpty: true,
			forcePlaceholderSize: true,
			handle: 'h2',
			helper: 'clone',
			items: 'div.box',
			placeholder: 'marker',
			tolerance: 'pointer',
			update: function () {
				var left = '', right = '';
				$('div.left').children('div.box').each(function () {
					left += $(this).attr('id').substring(9) + ',';
				});
				$('div.right').children('div.box').each(function () {
					right += $(this).attr('id').substring(9) + ',';
				});
				$.post('/homepage/configForm.php', {cid: $('#categoryID').val(), type: 'components', column1: left, column2: right});
				
			}
			
			sort: function (event, ui) {
				//console.log($('div.left').offset().left + $('div.left').width());
				var leftOffset = $('div.left').offset();
				console.log(event.pageY);
				if (event.pageX < (leftOffset.left + $('div.left').width()) &&
					event.pageY > (leftOffset.top + $('div.left').height())) {
					// left bottom
					console.log('left');
					ui.placeholder.appendTo($('div.left'));
				} 
				else if (event.pageY > ($('div.right').offset().top + $('div.right').height())) {
					// right bottom
					console.log('right');
					ui.placeholder.appendTo($('div.right'));
				}
			}
		});
		*/
		$('div.box h2').css('cursor', 'move');
		
		/* show/hide text based components */
		$(document).keydown(function (event) {
			if (event.shiftKey) {
				$('div.manage h2').each(function () {
					$(this).css('display', 'block');
				});
			}
		});
		$(document).keyup(function (event) {
			$('div.manage h2').each(function () {
				$(this).css('display', 'none');
			});
		});
		
		/* show/hide edit panel */
		$('a.edit').each(function () {
			$(this).click(function () {
				$(this).siblings('div.editPanel:first').toggleClass("hide");
			});
			
		});
		
	});

})(jQuery);

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
				element.injectBefore(marker).setStyles({'opacity': '1', 'z-index': '0', 'position': 'relative', 'top': '0', 'left': '0'});
				
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
				
				jQuery(document).triggerHandler("componentMove");
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

window.addEvent('domready', function () { initDrag(); });