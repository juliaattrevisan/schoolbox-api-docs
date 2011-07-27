	$j(function () {

		//unfortunately we have to do this for IE to make sure words don't fail wrap wise.
		if ($j('#month-view').length > 0) {
			$j('.js_happening li span').each(function() {
				$j(this).css({
					'width': ($j('#month-view').width() - 112) / 7
				});
			})
		} else {
			$j('.js_happening li span.note').each(function() {
				$j(this).css({
					'width': ($j('#week-view').width() - 112) / 7
				});
			})
		}
		
		$j(window).resize(function() {
			
			if ($j('.cal-bubble').is(':visible')) {	
				$j('.cal-bubble').hide();	
			};
			
			//unfortunately we have to do this for IE to make sure words don't fail wrap wise.
			if ($j('#month-view').length > 0) {
				$j('.js_happening li span').each(function() {
					$j(this).css({
						'width': ($j('#month-view').width() - 112) / 7
					});
				});
			} else {
				$j('.js_happening li span.note').each(function() {
					$j(this).css({
						'width': ($j('#week-view').width() - 112) / 7
					});
				})
			}
		});
		
		$j('.close').click(function(){
			$j('.cal-bubble').hide();		
		});
		
		$j('a.filters').click(function(){				
			$j('.filter-drop').toggle();
			return false;

		});
		
		//make all of the checkboxes checked... darn you firefoxxxxxx
		$j('.filter-list .checkbox').attr('checked', true);
		
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