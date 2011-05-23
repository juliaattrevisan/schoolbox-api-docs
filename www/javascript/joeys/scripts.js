$j(function() {
	
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),500,180);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),500,170);	
	});
		

});