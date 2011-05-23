$j(function() {
	
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),355,115);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),250,180);	
	});
		
	$j('#nav li.parent > a span').each(function() {
		$j(this).append('<strong>&rsaquo;</strong>');
	});
	
});