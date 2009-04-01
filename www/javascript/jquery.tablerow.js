(function($) {
	var options;
	
	$.fn.tablerow = function(opts) {
		options = $.extend($.fn.tablerow.defaults, opts);
		return $(this).each(function() {
			initialize($(this));
		});
	};

	$.fn.tablerow.defaults = {
		headClass: 'over',
		rowClass: 'ruled'
	};

	/* private functions */
	
	function initialize (node) {
 		
		// apply hover class to thead
		node.find('thead th:has(a)').each(function () {
			$(this).mouseover(function () {
				$(this).toggleClass(options.headClass, true);
			});
			$(this).mouseout(function () {
				$(this).toggleClass(options.headClass, false);
			});
			$(this).click(function () {
				window.open($(this).children("a:first").attr("href"),'_self','');
				return false;
			});
			$(this).css('cursor', 'pointer');
		});
		
		
		node.find('tbody tr').each(function () {
			// add click event to cells with links
			$(this).children('td:has(a)').each(function () {
				$(this).click(function () {
					window.open($(this).find('a:first').attr("href"),'_self','');
					return false;
				});			  
			});
			
			// add click event to row
			var link;
			if (link = $(this).find('a:first')) {
				$(this).children('td:not(:has(:input, a))').each(function () {
					$(this).click(function () {
						window.open(link.attr("href"),'_self','');
						return false;
					});
				});
				$(this).css('cursor', 'pointer');
			}
											 
			// add hover class to tbody rows
			$(this).mouseover(function () {
				$(this).toggleClass(options.rowClass, true);
			});
			$(this).mouseout(function () {
				$(this).toggleClass(options.rowClass, false);
			});
		});

	};
	
	// init
	$(document).ready(function () {
		$('table.row_hover').tablerow();
	});
	
})(jQuery);