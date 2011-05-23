$j(function() {
		$j('ul.js_happening li').click(function(){
		calendarPos($j(this),540,170);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),580,240);	
	});
	
});