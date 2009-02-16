// applies rollover class to table rows
$(document).ready(function() {
	$("table.ruler tbody tr").each(function() {
		$(this).mouseover(function() {
			$(this).addClass("ruled");
		});
		$(this).mouseout(function() {
			$(this).removeClass("ruled");
		});		
	});							
});