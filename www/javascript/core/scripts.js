$j(function() {

	$j('ul.js_happening li').click(function(){
		calendarPos($j(this),350,50);
	});
	
	$j('.text ul.js_happening li').click(function(){
		calendarPos($j(this),275,50);	
	})
		
	$j('#nav li.parent > a span').each(function() {
		$j(this).append('<strong>&rsaquo;</strong>');
	});
	
});