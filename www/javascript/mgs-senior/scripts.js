$j(function() {
	
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),350,60);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),0,60);	
	})

	$j('#nav li.parent > a span').each(function() {
		$j(this).append('<strong>&rsaquo;</strong>');
	});
	
});