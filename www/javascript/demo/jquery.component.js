$j(function() {

		
		/* remove invisible column */
		$j('div.right:hidden').remove();
		
		/* add drag/drop */
		$j('div.homepage').sortable({
			cursor: 'move',
			distance: 0,
			dropOnEmpty: true,
			forcePlaceholderSize: true,
			handle: 'h2',
			helper: 'clone',
			items: 'div.box',
			placeholder: 'marker',
			tolerance: 'pointer',
			
			update: function () {
				var left = '', right = '';
				$j('div.left').children('div.box').each(function () {
					left += $j(this).attr('id').substring(9) + ',';
				});
				$j('div.right').children('div.box').each(function () {
					right += $j(this).attr('id').substring(9) + ',';
				});
				$j.post('/homepage/configForm.php', {cid: $j('#categoryID').val(), type: 'components', column1: left, column2: right});
				
			},			
			sort: function (event, ui) {
				var leftOffset = $j('div.left').offset();
			
				if (event.pageX < (leftOffset.left + $j('div.left').width()) &&
					event.pageY > (leftOffset.top + $j('div.left').height())) {
					// left bottom
					ui.placeholder.appendTo($j('div.left'));
				} 
				else if (event.pageX > (leftOffset.left + $j('div.left').width()) &&
						 event.pageY > ($j('div.right').offset().top + $j('div.right').height())) {
					// right bottom
					ui.placeholder.appendTo($j('div.right'));

				} 
				
			}
		});
		
		$j('div.box h2').css('cursor', 'move');
		
		/* show/hide text based components */
		$j(document).keydown(function (event) {
			if (event.shiftKey) {
				$j('div.manage h2').each(function () {
					$j(this).css('display', 'block');
				});
			}
		});
		$j(document).keyup(function (event) {
			$j('div.manage h2').each(function () {
				$j(this).css('display', 'none');
			});
		});
		
		/* from here down I've changed the component functions!!!!*/

		/* show/hide edit panel */
		
		$j("a.edit").hover(function() {
			if(!$j(this).children('em').data('pinned')) {
				$j(this).children('em').addClass('show-it');
				
			}
			}, function() {
				if(!$j(this).children('em').data('pinned')) {
					$j(this).children('em').removeClass('show-it');
				}
			}).live('click', function(event) {
				event.preventDefault();
				$j(this).toggleClass('active');
				$j(this).siblings('.editPanel.hide').slideToggle("fast");
				$j(this).children('em').data('pinned', !$j(this).children('em').data('pinned'));
		});
		

		$j(".box h2").mousemove(function(e){
        $j("#tooltip").show();
        $j("#tooltip").css({
            top: (e.pageY -8) + "px",
            left: (e.pageX + 20) + "px"
        });
    });
    $j(".box h2").mouseout(function(e){
        $j("#tooltip").hide();
    });


});