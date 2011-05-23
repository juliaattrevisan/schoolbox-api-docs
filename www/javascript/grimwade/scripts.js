$j(function() {
	
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),500,200);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),500,250);	
	});
	
});