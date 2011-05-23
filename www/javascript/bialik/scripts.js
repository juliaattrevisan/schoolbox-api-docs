$j(function() {
	
	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),600,200);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),600,250);	
	})

	$j('#nav li.parent > a span').each(function() {
		$j(this).append('<strong>&rsaquo;</strong>');
	});
	
});