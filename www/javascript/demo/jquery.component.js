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
			$j.post('/homepage/configForm.php', {cid : cid, type: 'components', column1: left, column2: right});
			
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
			
			$j('.cal-bubble').hide();
			
		}
	});
	
	$j('div.box h2').css('cursor', 'move');
	
	
	/* from here down I've changed the component functions!!!!*/
	
	$j('.manage h2, a.folder-manage').each(function () {
		$j(this).hide();
	});
	
	$j('#tabs-homepage').tabs();
	$j('#tabs-sub').tabs();
	
	
	$j('.add-component strong').each(function() {
		
		var howManyInstances = $j(this).children('em').html();
		if (howManyInstances == 0) {
			howManyInstances = '<strong>no</strong> instances '
		} else if (howManyInstances == 1)  {
			howManyInstances = '<strong>1</strong> instance '
		} else {
			howManyInstances = '<strong>' + howManyInstances + '</strong> instances '
		}
		
		$j(this).qtip({
			content: 'You have '+ howManyInstances +'of this component currently active',
			show: 'mouseover',
			hide: 'mouseout',
			position: {
				corner: {
					tooltip: 'bottomLeft',
					target: 'topright'
				}
			},
			api: {
					// Retrieve the content when tooltip is first rendered
					 onRender: function()
					 {
						var self = this;
					 }
				 },
			style: { 
				background: '#fffde9',
				color: 'black',
				border: {
					width: 3,
					radius: 4,
					color: '#fff9bf'
				},
				name: 'green' // Inherit from preset style
		   }
		});
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
		$j('.manage h2, a.folder-manage').each(function () {
			$j(this).toggle();
		});
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

	$j('.delete-accept').live('click',function(event) {
		
		event.preventDefault();
       
	    //update with an ajax call here.
		$j.post('/homepage/configForm.php', $j(this).closest('form').serialize());
		
        $j(this).closest('.box').animate({opacity:0}, 300,
			function() {
				$j(this).slideUp(200, function() {
					$j(this).remove();	
				});
		});
		
		var componentToDelete = Number($j(this).siblings("input[name=component]").val());
		var currentAmount = Number($j('a[rel='+componentToDelete+']').siblings('h4').children('strong').children('em').html());
		currentAmount--;
		$j('a[rel='+componentToDelete+']').siblings('h4').children('strong').children('em').html(currentAmount);
		
		if ($j('a[rel='+componentToDelete+']').parent().hasClass('disabled')) {
			$j('a[rel='+componentToDelete+']').parent().removeClass('disabled')
		}
		
	});


	$j('.delete-reject').live('click',function(event) {
		event.preventDefault();
		$j(this).closest('.delete-it').slideUp();
	});

	$j(".add-component a").live('click', function(event) {
		event.preventDefault();
		var $this = $j(this);
		$j.post('/homepage/configForm.php', {add : $j(this).attr('rel'), cid : cid }, function(data){
			$j(data).hide().prependTo('.left').css('opacity','0').slideDown(400,function(){
				$j(this).animate({opacity:1}, 300);
			});
        });		

		if($this.parent().hasClass('one-only')) {
			$this.parent().addClass('disabled');
		} else if ($j('.how-many').html() == '') {
			//if there are no modules but you can add one
			$this.siblings('.how-many').append('You already have <em>1</em>');
		} else {
			//include the current amount of the module
			var currentCount = Number($this.siblings('strong em'));
			currentCount++;
			$this.siblings('strong em').html(currentCount);
		}
		
		if (!$this.siblings('.notification').is(':visible')) {
			$this.siblings('.notification').fadeIn(200, function() {
				$j(this).delay(2000).fadeOut();
			});
		}
		
		var currentAmount = Number($this.siblings('h4').children('strong').children('em').html());
		currentAmount++;
		$this.siblings('h4').children('strong').children('em').html(currentAmount);
		
	});
	
	$j('div.colorBox').each(function () {
		$j(this).css('backgroundColor', pickers[$j(this).attr('id')]);
		//$j(this).next().val(pickers[$j(this).attr('id')]);
	});

	var f = $j.farbtastic('#picker');
	f.setColor("#b6b6ff"); //set initial color
	
	$j('#head_font').parent().addClass('active-box');
	var initialColour = rgbConvert($j('#left_head_font').css('background-color'));
	checkTextColour();
	$j('#currentColour').css('background-color',initialColour);
	$j('#currentColour').val(initialColour);

	f.linkTo(onColorChange);
	
	$j('#currentColour').keyup(function() {	
		f.setColor($j(this).val());	
	});
	
	function h2d(h) {return parseInt(h,16);} 

	function rgbConvert(str) {
		str = str.replace(/rgb\(|\)/g, "").split(",");
		str[0] = parseInt(str[0], 10).toString(16).toLowerCase();
		str[1] = parseInt(str[1], 10).toString(16).toLowerCase();
		str[2] = parseInt(str[2], 10).toString(16).toLowerCase();
		str[0] = (str[0].length == 1) ? '0' + str[0] : str[0];
		str[1] = (str[1].length == 1) ? '0' + str[1] : str[1];
		str[2] = (str[2].length == 1) ? '0' + str[2] : str[2];
		return ('#' + str.join(""));
	}
	
	
	function checkTextColour() {
		var currentColour = $j('#currentColour').val().substr(1);
			if (h2d(currentColour) < 4473924) {
			$j('#currentColour').css('color', '#ffffff');
		} else {
			$j('#currentColour').css('color', '#000000');
		};	
	};
	
	function onColorChange(color) {
		checkTextColour();
		var current = $j('.selected');
		current.css('background-color', color);
		$j('#currentColour').val(color);
		$j('#currentColour').css('background-color', color);
        pickers[current.attr('ID')] = color;

		switch (current.attr('ID')) {
			case 'left_bg_primary':
				return $j('.left .text').css('background-color', color);
			break;
			case 'left_bg_secondary':
				return $j('.left .dark').css('background-color', color);
			break;
			case 'left_head_font':
				return $j('.left h2').css('color', color);
			break;
			case 'left_head_bg':
				return $j('.left h2').css('background-color', color)
			break;
			case 'left_font':
				return $j('.left, .left p').css('color', color)
			break;	
			case 'left_link':
				return $j('.left a').css('color', color);
			break;	
			case 'left_hover':
				return $j('.right a:hover').css('color', color);
			break;	
			case 'right_bg_primary':
				return $j('.right .text').css('background-color', color);
			break;
			case 'right_bg_secondary':
				return $j('.right .dark').css('background-color', color);
			break;
			case 'right_head_font':
				return $j('.right h2').css('color', color);
			break;
			case 'right_head_bg':
				return $j('.right h2').css('background-color', color);
			break;
			case 'right_font':
				return $j('.right, .right p').css('color', color);
			break;	
			case 'right_link':
				return $j('.right a').css('color', color);
			break;	
			case 'right_hover':
				return $j('.right a:hover').css('color', color);
			break;	
			default:
				return $j('.left .text').css('background-color', color);
			break;
		}
	}

	$j('.colour').click(function(event) {
		event.preventDefault();
		$j('.selected').removeClass('selected');
		$j('.active-box').removeClass('active-box');
		$j(this).addClass('active-box');
		$j(this).children('.colorBox').addClass('selected');
		$j(this).next().next('p').addClass('selected');
		f.setColor(rgbConvert($j(this).children('.colorBox').css('background-color')));
		checkTextColour()
	});

	$j('.layouts a').click(function(event) {
		event.preventDefault();
		
		var templateChoice = $j(this).attr('title');
		var newChoice = "";
		
		switch (templateChoice) {
			case '1':
				newChoice = 'template1';
			break;
			case '2':
				newChoice = 'template2';
			break;
			case '3':
				newChoice = 'template3';
			break;
			case '4':
				newChoice = 'template4';
			break;
			default:
				newChoice = 'template1';
			break;
		}
		
        $j('.homepage').removeClass().addClass(newChoice + ' homepage ui-sortable');
		$j('.current').removeClass('current');
		$j(this).addClass('current');
        $j('#templateVal').val(templateChoice);
	});	
	
	$j('#saveTemplate').click(function () {
        $j.post('/homepage/configForm.php', $j(this).closest('form').serialize());
    });

    $j('#saveColour').click(function () {
       $j.post('/homepage/configForm.php', {colors: pickers, cid: cid});
    });
	
	$j("#leftRight").click(function () {	
		var rightBox;
		
		$j('div.leftColumn div.colorBox').each(function () {
			var str = $j(this).attr('id');
			var newstr = str.replace(/^.*left_(.*).*$/m, '$1');

			rightBox = $j('#' + 'right_'+newstr);
			rightBox.css('backgroundColor', $j(this).css('backgroundColor'));
			rightBox.next().val($j(this).next().val());
		});

		$j('.right .text').css('background-color', $j('.left .text').css('background-color'));
		$j('.right .dark').css('background-color', $j('.left .dark').css('background-color'));
		$j('.right, .right p').css('color', $j('.left').css('color'));
		$j('.right h2').css('background-color', $j('.left h2').css('background-color'));
		$j('.right, .right p').css('color', $j('.left, .left p').css('color'));
		$j('.right a').css('color', $j('.left a').css('color'));

	});
	
	$j('#restore').click(function() {
		$j('.colorBox').each(function () {
			$j(this).css('background-color', defaults[$j(this).attr('id')]);
			//$j('.text, .dark, h2, a, p, .left, .right').removeAttr("style");
		});
		
		$j('.left h2').css({
			"color": defaults['left_head_font'],
			"background-color":defaults['left_head_bg']
		});
		$j('.left p, .left h3, .left .listing p.small').css("color", defaults['left_font']);
		$j('.left a, .left .listing p.small a').css('color', defaults['left_link']);
		$j('.left .box a:hover, .left .listing p.small a:hover').css('color', defaults['left_hover']);
		$j('.left .text').css('background',defaults['left_bg_primary']);
		$j('.left .text.listing .dark ').css('background',defaults['left_bg_secondary']);
		
		$j('.right h2').css({
			"color": defaults['right_head_font'],
			"background-color":defaults['right_head_bg']
		});
		$j('.right p, .right h3, .right .listing p.small').css("color", defaults['right_font']);
		$j('.right a, .right .listing p.small a').css('color', defaults['right_link']);
		$j('.right .box a:hover, .right .listing p.small a:hover').css('color', defaults['right_hover']);
		$j('.right .text').css('background',defaults['right_bg_primary']);
		$j('.right .text.listing .dark ').css('background',defaults['right_bg_secondary']);
	});
	
	$j('#cancel').click(function() {
		$j('.colorBox').each(function () {
			$j(this).css('background-color', original[$j(this).attr('id')]);
		});
		
		$j('.text, .dark, h2, a, p, .left, .right').removeAttr("style");
		
	});
	


});