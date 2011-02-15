$j(function() {
	$j('ul.js_happening li').click(function(){

		var dynamicHTML = $j(this).children('.hidden-fields').html();

		$j('.cal-bubble .middle').children('.js-data').html(dynamicHTML);
		var position = $j(this).offset();
		$j('.cal-bubble').show();
		var annoyingHeight = ($j('.cal-bubble .middle').height() + ($j('.cal-bubble .top').height()) + ($j('.cal-bubble .bottom').height())) 			
		$j('.cal-bubble').css("left", (position.left - 250));
		$j('.cal-bubble').css("top", (position.top - annoyingHeight));	
		
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
			$j('.cal-bubble').css("left", (position.left - 250));
			$j('.cal-bubble').css("top", (position.top - annoyingHeight - 160));	
			
			if (($j('.cal-bubble').css("left")) < '0px') {
					$j('.cal-bubble').css("left", '0');
			}	
			
			
		});	
		

});