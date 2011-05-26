$j(function() {
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),540,180);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),580,180);	
	});
		

});