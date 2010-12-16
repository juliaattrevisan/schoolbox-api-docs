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
	
	$j('#tabs-homepage').tabs({selected:1});
	$j('#tabs-sub').tabs({selected:0});
	
	$j('.add-component strong').qtip({
		content: 'How many instances of the module currently active',
		show: 'mouseover',
		hide: 'mouseout',
		position: {
			corner: {
			tooltip: 'bottomLeft',
			target: 'topright'
			}
		},
		style: { 
			background: '#CAE9B9',
			color: 'black',
			border: {
				width: 6,
				radius: 4,
				color: '#fbfbfb'
			},
			
			name: 'green' // Inherit from preset style
	   }
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
	
	$j('.customise').click(function(event) {
		event.preventDefault();
		$j('#tabs-homepage').slideToggle();
		$j(this).children('span').toggle();
	});

	$j("a.edit").hover(function() {
		
		var $this = $j(this);
		
		if(!$this.children('em').data('pinned')) {
			$this.children('em').addClass('show-it');
		}
		
		}, function() {
			var $this = $j(this);
			
			if(!$this.children('em').data('pinned')) {
				$this.children('em').removeClass('show-it' );
			}
		
		}).live('click', function(event) {
			var $this = $j(this);
			event.preventDefault();
			
			if ($this.siblings('.editPanel.delete-it').is(':visible')) {
				$this.siblings('.editPanel.delete-it').slideUp();
			}
			
			$this.toggleClass('active');
			$this.siblings('.editPanel.settings.hide').slideToggle("fast");
			$this.children('em').data('pinned', !$this.children('em').data('pinned'));
			
	});
	
	$j('.close-delete').live('click', function(event){
		event.preventDefault();
		var $this = $j(this);
		
		if ($this.siblings('.editPanel.settings').is(':visible')) {
			$this.siblings('.editPanel.settings').slideUp();
			$this.siblings('a.edit').children('em').data('pinned', !$this.siblings('a.edit').children('em').data('pinned'));
			$this.siblings('a.edit').removeClass('active');
			$this.siblings('a.edit').children('em').removeClass('show-it');
		}
		$this.siblings('.editPanel.delete-it.hide').slideToggle("fast");
	});


	$j('.delete-accept').click(function(event) {
		event.preventDefault();
		$j(this).parent().parent().animate({opacity:0}, 300,
			function() {
				$j(this).slideUp(200, function() {
					$j(this).remove();	
					
					//update with an ajax call here.
					
				});
		});
	});


	$j('.delete-reject').click(function(event) {
		event.preventDefault();
		$j(this).parent().slideUp();
		
	});

	$j(".add-component a").live('click', function(event) {
		event.preventDefault();
		var $this = $j(this);
		
		if($this.parent().hasClass('one-only')) {
			$this.siblings('.notification').fadeIn(200, function() {
				$j(this).delay(4000).fadeOut();
			});
			$this.remove();
			$this.parent().addClass('.disabled');
		} else if ($j('.how-many').html() == '') {
			//if there are no modules but you can add one
			$this.siblings('.how-many').append('You already have <em>1</em>');
		} else {
			//include the current amount of the module
			var currentCount = Number($this.siblings('strong em'));
			currentCount++;
			$this.siblings('strong em').html(currentCount);
		}
		
		$this.siblings('.notification').fadeIn(200, function() {
			$j(this).delay(4000).fadeOut();
		});
		
		ajaxRequest();
		
		function ajaxRequest() {}
	});


	
	/* now the colour nonsense :| */
	
	var pickers = {
		'head_font'		: '#013A63',
		'head_bg'		: '#CAE9B9',
		'font'			: '#013A63',
		'bg_primary'	: '#ffffff',
		'bg_secondary'	: '#FAF9F5',
		'link'			: '#1d9ffc',
		'hover'			: '#ff5a00',
		
		'head_font_right'	: '#013A63',
		'head_bg_right'	: '#CAE9B9',
		'font_right'	: '#013A63',
		'bg_primary_right'	: '#ffffff',
		'bg_secondary_right'	: '#FAF9F5',
		'link_right'	: '#1d9ffc',
		'hover_right'	: '#ff5a00'
	};
	var defaults = {
		'head_font'		: '#013a63',
		'head_bg'		: '#cae9b9',
		'font'			: '#000000',
		'bg_primary'	: '#ffffff',
		'bg_secondary'	: '#faf9f5',
		'link'			: '#1d9ffc',
		'hover'			: '#ff5a00',
		
		'head_font_right'	: '#013a63',
		'head_bg_right'	: '#cae9b9',
		'font_right'	: '#000000',
		'bg_primary_right'	: '#ffffff',
		'bg_secondary_right'	: '#faf9f5',
		'link_right'	: '#1d9ffc',
		'hover_right'	: '#ff5a00'
	};

	
	
	$j('div.colorBox').each(function () {
		$j(this).css('backgroundColor', pickers[$j(this).attr('id')]);
		$j(this).next().val(pickers[$j(this).attr('id')]);
	});
	

	var f = $j.farbtastic('#picker');
	f.setColor("#b6b6ff"); //set initial color

	f.linkTo(onColorChange);
	//f.linkTo($j('#bg_primary').next().add($j('#bg_primary')).add($j('#currentColour')));
	
	function onColorChange(color) {

		var current = $j('.selected');
		
		switch (current.attr('ID')) {
			case 'bg_primary':
				$j('.left .box').css('background-color', color);
			break;
			case 'bg_secondary':
				$j('.left .dark').css('background-color', color);
			break;
			case 'head_font':
				$j('.left h2').css('color', color);
			break;
			case 'head_bg':
				$j('.left h2').css('background-color', color);
			break;
			case 'font':
				$j('.left').css('color', color);
			break;	
			case 'link':
				$j('.left a').css('color', color);
			break;	
			case 'hover':
				$j('.left a:hover').css('color', color);
			break;	
			case 'bg_primary_right':
				$j('.right .box').css('background-color', color);
			break;
			case 'bg_secondary_right':
				$j('.right .dark').css('background-color', color);
			break;
			case 'head_font_right':
				$j('.right h2').css('color', color);
			break;
			case 'head_bg_right':
				$j('.right h2').css('background-color', color);
			break;
			case 'font_right':
				$j('.right').css('color', color);
			break;	
			case 'link_right':
				$j('.right a').css('color', color);
			break;	
			case 'hover_right':
				$j('.right a:hover').css('color', color);
			break;	
			default:
				console.log('shut up right')
			break;
		}
	}

	$j('div.colorBox').click(function () {
		$j('.selected').removeClass('selected');
		$j(this).addClass('selected');
	});
	
	$j('div.colours p').click(function () {
		$j(this).prev().prev().click();
	});
	

});