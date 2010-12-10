(function($) {

	$(document).ready(function () {
		
		/* remove invisible column */
		$('div.right:hidden').remove();
		
		/* add drag/drop */
		$('#content').sortable({
			cursor: 'move',
			distance: 0,
			dropOnEmpty: true,
			forcePlaceholderSize: true,
			handle: 'h2',
			helper: 'clone',
			items: '.box',
			cancel: '.permanent',
			placeholder: 'marker',
			tolerance: 'pointer',
			
			update: function () {
				var left = '', right = '';
				$('div.left').children('div.box').each(function () {
					left += $(this).attr('id').substring(9) + ',';
				});
				$('div.right').children('div.box').each(function () {
					right += $(this).attr('id').substring(9) + ',';
				});
				$.post('/eportfolio/configForm.php', {uid: $('#userID').val(), type: 'components', column1: left, column2: right});
				
			},			
			sort: function (event, ui) {
				var leftOffset = $('div.left').offset();
			
				if (event.pageX < (leftOffset.left + $('div.left').width()) &&
					event.pageY > (leftOffset.top + $('div.left').height())) {
					// left bottom
					ui.placeholder.appendTo($('div.left'));
				} 
				else if (event.pageX > (leftOffset.left + $('div.left').width()) &&
						 event.pageY > ($('div.right').offset().top + $('div.right').height())) {
					// right bottom
					ui.placeholder.appendTo($('div.right'));

				} 
				
			}
		});
		
		$('div.box h2').css('cursor', 'move');
		
		/* show/hide text based components */
		$(document).keydown(function (event) {
			if (event.shiftKey) {
				$('div.manage h2').each(function () {
					$(this).css('display', 'block');
				});
			}
		});
		$(document).keyup(function (event) {
			$('div.manage h2').each(function () {
				$(this).css('display', 'none');
			});
		});
		
		$("a.edit").hover(function() {
			if(!$(this).children('em').data('pinned')) {
				$(this).children('em').addClass('show-it');
				
			}
			}, function() {
			if(!$(this).children('em').data('pinned')) {
				$(this).children('em').removeClass('show-it');
			}
			}
				).live('click', function(event) {
				event.preventDefault();
				$(this).toggleClass('active');
				$(this).siblings('.editPanel.hide').slideToggle("fast");

				$(this).children('em').data('pinned', !$(this).children('em').data('pinned'));
				



			});
		
	});

})(jQuery);
