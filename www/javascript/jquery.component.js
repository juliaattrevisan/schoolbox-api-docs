(function($) {

	$(document).ready(function () {
		/* add drag/drop */
		$('div.homepage').sortable({
			cursor: 'move',
			distance: 0,
			forcePlaceholderSize: true,
			handle: 'h2',
			helper: 'clone',
			items: 'div.box',
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
				$.post('/homepage/configForm.php', {cid: $('#categoryID').val(), type: 'components', column1: left, column2: right});
			},
			out: function () {
				console.log('out');	
			}
		});
		$('div.box h2').css('cursor', 'move');
		
		var leftHeight = $('div.left').height(), rightHeight = $('div.right').height();
		var maxHeight = leftHeight > rightHeight ? leftHeight : rightHeight;
		$('div.left').height(maxHeight);
		$('div.right').height(maxHeight);
	
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
		
		/* show/hide edit panel */
		$('a.edit').each(function () {
			$(this).click(function () {
				$(this).siblings('div.editPanel:first').toggleClass("hide");
			});
			
		});
	});

})(jQuery);