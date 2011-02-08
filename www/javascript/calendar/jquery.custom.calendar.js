	$j(function () {
		$j(window).resize(function() {

			if ($j('.cal-bubble').is(':visible')) {	
				$j('.cal-bubble').hide();	
			}
			
		});
		
		$j('.close').click(function(){
			$j('.cal-bubble').hide();		
			
		});
		
		$j('a.filters').click(function(){
			
			//$j('.cal-bubble').hide();
				
			$j('.filter-drop').toggle();

		});
		
		
		//position the bubbles, depending on where you are in the portal.
		
		/*$j('ul.js_happening li').click(function(){ 
			var dynamicHTML = $j(this).children('.hidden-fields').html();
			$j('.cal-bubble .middle').children('.js-data').html(dynamicHTML);
			
			var position = $j(this).offset();
			$j('.cal-bubble').show();
			var annoyingHeight = ($j('.cal-bubble .middle').height() + ($j('.cal-bubble .top').height()) + ($j('.cal-bubble .bottom').height())) 			
			$j('.cal-bubble').css("left", (position.left - 550));
			$j('.cal-bubble').css("top", (position.top - annoyingHeight - 150));	
			
			if (($j('.cal-bubble').css("left")) < '0px') {
					$j('.cal-bubble').css("left", '0');
			}	
		});
		
		$j('.text ul.js_happening li').click(function(){
			var dynamicHTML = $j(this).children('.hidden-fields').html();
			$j('.cal-bubble .middle').children('.js-data').html(dynamicHTML);
			
			var position = $j(this).offset();
			$j('.cal-bubble').show();
			var annoyingHeight = ($j('.cal-bubble .middle').height() + ($j('.cal-bubble .top').height()) + ($j('.cal-bubble .bottom').height())) 			
			$j('.cal-bubble').css("left", (position.left - 550));
			$j('.cal-bubble').css("top", (position.top - annoyingHeight - 200));	
			
			if (($j('.cal-bubble').css("left")) < '0px') {
					$j('.cal-bubble').css("left", '0');
			}	
			
			
		});*/
		
		//show and hide the filters drop down

		
		//make all of the checkboxes checked... darn you firefoxxxxxx
		$j('.checkbox').attr('checked', true);
		
				//run the filter checker to show and hide the items
		$j('#applyFilters').click(function() {
			filterChecker()
		});
		
		function filterChecker() {
			//specify filter hierarchy

				
			filterLoop('groups');
			filterLoop('campus');
			filterLoop('events');

			$j('.filter-drop').hide();	
			
		}//end of filterchecker
		
		function filterLoop(element) {
			//for each unchecked element
			$j('.filter-list .' + element + ' input:not(:checked)').each(function(){
				var n = $j(this).attr('value');
				if ($j('ul.js_happening .' + n).is(':visible')) {
				  $j('ul.js_happening .' + n).hide();
				}
			});
			
			$j('.filter-list .' + element + ' input:checked').each(function(){
				var n = $j(this).attr('value');
				 $j('ul.js_happening li.' + n).show();
			});
		}
		
		$j('.modify a').html('<span></span>Modify');
		$j('.modify a').addClass('btn modify float-left')
		
		
	});