$j(function() {
	
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),580,210);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),580,240);	
	});
			
	$j('#nav li.parent > a span').each(function() {
		$j(this).append('<strong>&rsaquo;</strong>');
	});
	
});