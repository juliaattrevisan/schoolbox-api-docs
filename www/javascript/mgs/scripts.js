$j(function() {
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),520,160);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),580,210);	
	});
});