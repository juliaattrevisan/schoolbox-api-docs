setupCustomise = {

	ajaxURL: '',
	id: '',
	key: 'cid',
	leftColumnWidth: '',

	emptyColumnCheck: function(target) {
		for (i=0; i < target.length; i++ ) {
			var column = $j(target[i]);
			if (column.find('.box').length == 0) {
				column.addClass('empty-column');
				if ($j('#tabs-homepage').is(':visible')) {
					$j('.empty-column').each(function() {
						$j(this).show();
					})
				}
				column.html('<p class="placeholder">You can drag components into this space.</p>');
			} else {
				column.children('.placeholder').remove();
				column.removeClass('empty-column');
			}
		}
	},

	customisationDropdown: {

        /**
         * Sets up the tabs and then one by one initialises the three tabs contained
		 * within the dropdown panel.
		 *
         */
		init: function() {
			$j('#tabs-homepage').tabs();
			$j('#tabs-sub').tabs();
//			$j('.customise').click(function(event) {
//				$j('#tabs-homepage, .empty-column').slideToggle();
//				$j(this).children('span').toggle();
//				$j('h2.hide').each(function () {
//					$j(this).toggle();
//				});
//				return false;
//			});

			// Component and column buttons
			var buttons = $j('#tab-component, #tab-columns');
			buttons.click(function(event) {
				event.preventDefault;
				var openMe = true;
				if ($(this).hasClass('hover')) {
					openMe = false;
				}

				// Set hover classes, and remove it from other buttons
				$(this).toggleClass('hover', openMe);
				buttons.not(this).removeClass('hover');

				// Close all other tabs, and open this tab if needed
				$('#tabs-homepage').children('.customise-components').removeClass('open');
				if (openMe) {
					$('#'+$(this).data('tab')).addClass('open');
				}
			});
			this.colours(setupCustomise.id);
			this.components();
			this.columnWidths();
			this.columnSelectors();
		},

		/**
		 * Allows for control of the components in the customise dropdown.
		 */
		components: function() {
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
                    style: {
                        classes: 'qtip-green qtip-shadow qtip-rounded'
                    },
                    position: {
                        my: 'bottom left',
                        at: 'top right'
                    }
                });
            });


			$j(document).on('click', ".add-component a", function(event) {
				event.preventDefault();
				var $this = $j(this);

				/* Polymorphic key
				 */
				var rel = $j(this).attr('rel').split(' ');

				/* In eportfolio it sends as a pair: definitionId and componentInstanceId
				 */
				if (rel.length > 1) {
					var data = {
						add : rel[0],
						from: rel[1]
					};
				}
				else {
					var data = {
						add : rel[0]
					};
				}

				data[setupCustomise.key] = setupCustomise.id;

				$j.ajax({
				 type: "POST",
					url: setupCustomise.ajaxURL,
					data: data,
					success: function(data){
						$j(data).hide().prependTo('#container .left').css('opacity','0').slideDown(400,function(){
							setupCustomise.emptyColumnCheck([$j('div.top'), $j('div.right'), $j('div.left')/*, $j('div.bottom')*/]);
							$j(this).animate({opacity:1}, 300);
							/*
							* When a new component is added in we need it to
							* be able to get the colours that have been changed before
							* the component is added.
							* 1. Colour is changed
							* 2. "add" is clicked on any component
							* 3. Apply the colour change to this new component's DOM elements.
							* */
							var newComponent = $j(this);

							newComponent.find('h2').css({
								'background-color': $j('#left_head_bg').css('background-color'),
								'color': $j('#left_head_font').css('background-color')
							});

							newComponent.find('.text, .listing').css({
								'background-color': $j('#left_bg_primary').css('background-color')
							});

							newComponent.css({
								'color': $j('#left_font').css('background-color')
							});

							newComponent.find('p').css({
								'color': $j('#left_font').css('background-color')
							});

							newComponent.find('.dark').css({
								'background-color' : $j('#left_bg_secondary').css('background-color')
							});

							newComponent.find('a').css({
								'color' : $j('#left_link').css('background-color')
							});

						});
					},
					dataType: "html"
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
		},

		colours: function() {
			// Each colorpicker can represent colors for multiple sections.
			$j('input[data-colorpicker]').each(function () {
				$j(this).spectrum({
					showPalette: false,
					showSelectionPalette: false,
					showButtons: false,
					showAlpha: false,
					showInitial: true,
					showInput: true,
					clickoutFiresChange: true,
					preferredFormat: "hex",
					replacerClassName: 'colorBox',
					allowEmpty: true,
					change: onColorChange,
					move: onColorChange
				})
			});

			// trigger the change event for whatever columns are selected
			// This will load colors for whatever's selected
			var self = this;
			self.loadColorpickerColors();

			function onColorChange(colorObject) {
				if (colorObject instanceof tinycolor) {
					// Change the colors for the selected items
					var color = '#'+colorObject.toHex();
					var id    = this.id;
					self.getSelectedColumns().forEach(function(column) {
						var colorId = column+'_'+id;
						pickers[colorId] = color;
						setupCustomise.updateColor(colorId, color)
					});
				}
			}

			$j('#restore').click(function() {
				resetColours(['.top', '.left' ,'.right'], defaults);
				// copy values from defaults into pickers
				pickers = $j.extend({}, pickers, defaults);
				self.loadColorpickerColors();
			});

			function resetColours(area, colours) {

				colours = colours || defaults;

				$j.each(area, function(index, value) {

					var areaClass = value,
					areaVariable = value.substr(1);

					$j(areaClass).find('h2').css({
						"color": colours[areaVariable+'_head_font'],
						"background-color": colours[areaVariable+'_head_bg']
					});

					$j(areaClass).find('p, h3, .listing p.small').css({
						"color": colours[areaVariable+'_font']
					});

					$j(areaClass).find('a, .listing p.small a').css({
						'color': colours[areaVariable+'_link']
					});

					$j(areaClass).find('.text, .listing').css({
						'background': colours[areaVariable+'_bg_primary']
					});

					$j(areaClass).find('.dark').css({
						'background':  colours[areaVariable+'_bg_secondary']
					});

				});
				return false;
			}

			$j('#cancel').click(function() {
				resetColours(['.top', '.left' ,'.right'], original);
				// copy values from original into pickers
				pickers = $j.extend({}, pickers, original);
				self.loadColorpickerColors();
			});


			$j('#saveColour').click(function () {
				var data = {
					colors: pickers
				};

				data[setupCustomise.key] = setupCustomise.id;

			   $j.post(setupCustomise.ajaxURL, data, function() {
					$j('.saved-colours').slideDown(150).delay(2000).slideUp(150);
			   });
			});
		},

		columnWidths: function() {
			$j("#slider" ).slider({
				'min': 30,
				'max':70,
				value: setupCustomise.leftColumnWidth,
				slide: function(event, ui) {
					$j('.left').css('width', (ui.value-.5)+'%')
					$j('.right').css('width', (100 - ui.value-.5)+'%')

				},
				create: function(event, ui){
					$j('.ui-slider-handle').append('<span></span>');
				},
				'stop':function(event, ui) {
					var data = {
						leftColWidth: ui.value - 0.5,
						rightColWidth: 99.5 - ui.value
					};

					data[setupCustomise.key] = setupCustomise.id;

					$j.post(setupCustomise.ajaxURL, data,
						function() {
							$j('.saved-layout').hide().slideDown(150).delay(2000).slideUp(150);
			   		})
				}
			});

		},

		/**
		 * Sets up the column selectors.
		 */
		columnSelectors: function() {
			var self = this;
			$j('[data-column-selector]').click(function(event) {
				event.preventDefault();
				// Don't allow deselect of the only column selected
				var element  = $(this);
				var deselect = element.data('interaction-state') === 'selected';
				if (!(deselect && self.getSelectedColumns().length <= 1)) {
					element
						.toggleClass('selected', !deselect)
						.data('interaction-state', deselect ? '' : 'selected');
					self.loadColorpickerColors();
				}
			});
		},

		/**
		 * Loads picked colors for selected columns into colorpickers.
		 * If multiple columns are selected, and their colors are different,
		 * the colorpicker will be blank.
		 */
		loadColorpickerColors: function() {
			var columns = this.getSelectedColumns();
			$j('input[data-colorpicker]').each(function() {
				var id     = this.id;
				var colors = columns.map(function(column) {
					var thisId    = column + '_' + id;
					var thisColor = pickers[thisId];
					return thisColor;
				});
				// If this isn't the first instance of color in colors:
				// filter it out (thus getting unique array)
				var uniqueColors = colors.filter(function(color, index) {
					return colors.indexOf(color) === index;
				});

				var element = $(this);
				if (uniqueColors.length === 1) {
					element.spectrum('set', uniqueColors.pop());
				} else {
					element.spectrum('set', null);
				}
			});
		},

		/**
		 * Gets the columns that are currently selected.
		 *
		 * @returns array
		 */
		getSelectedColumns: function() {
			return $j('[data-column-selector]')
				.filter(function() {
					return $(this).data('interaction-state') === 'selected';
				})
				.map(function() { return this.id; })
				.toArray();
		}
	},

	sortComponents: function() {

		$j('div.homepage').sortable({
			cursor: 'move',
			distance: 0,
			dropOnEmpty: true,
			handle: 'h2',
			helper: 'clone',
			items: '.box:not(".permanent")',
			placeholder: 'marker',
			tolerance: 'pointer',
			zIndex: 2000,
			start: function() {
				$j('.marker').text('This is the new position of your component');

				/* Allow tinys to work in sortable context
				 */
				$j(this).find('.mceEditor').each(function(){
					tinyMCE.execCommand( 'mceRemoveControl', false, $j(this).attr('id') );
				});
			},
			stop: function() {
				$j(this).find('.mceEditor').each(function(){
					tinyMCE.execCommand( 'mceAddControl', true, $j(this).attr('id') );
					$j(this).sortable("refresh");
				});
				var left = '';
				var right = '';
				var top = '';
                var bottom = '';
				$j('.homepage div.left').children('div.box').each(function () {

					/* Two components in ePorfolio have no id's (My Profile, Teacher Notes)
					 * and always appear in the same positions. So we do a check to see if
					 * an id is set. If it isn't, and we don't do the check, the script breaks
					 * and components cannot be relocated successfully
					 */
					if ($j(this).attr('id') != undefined) {
						left += $j(this).attr('id').substring(9) + ',';
					}
				});

				$j('.homepage div.right').children('div.box').each(function () {
					if ($j(this).attr('id') != undefined) {
						right += $j(this).attr('id').substring(9) + ',';
					}
				});

				$j('.homepage div.top').children('div.box').each(function () {
					if ($j(this).attr('id') != undefined) {
						top += $j(this).attr('id').substring(9) + ',';
					}
				});

//                $j('.homepage div.bottom').children('div.box').each(function () {
//                    if ($j(this).attr('id') != undefined) {
//                        top += $j(this).attr('id').substring(9) + ',';
//                    }
//                });

				var data = {
					type: 'components',
					column1: left,
					column2: right,
					column3: top,
                    column4: bottom
				}

				data[setupCustomise.key] = setupCustomise.id;

				$j.post(setupCustomise.ajaxURL, data);

				setupCustomise.emptyColumnCheck([$j('.homepage div.top'), $j('.homepage div.right'), $j('.homepage div.left')/*,  $j('.homepage div.bottom')*/])

				// Reapply colors as well.
				$.each(pickers, function(element, color) {
					setupCustomise.updateColor(element, color)
				})
			},
			sort: function (event, ui) {
				var leftOffset = $j('.homepage div.left').offset();
				var topOffset = $j('.homepage div.top').offset();
//                var bottomOffset = $j('.homepage div.bottom').offset();

				if (event.pageX < (leftOffset.left + $j('.homepage div.left').width()) &&
					event.pageY > (leftOffset.top + $j('.homepage div.left').height())) {
					// left bottom
					ui.placeholder.appendTo($j('.homepage div.left'));
				}
				else if (event.pageX > (leftOffset.left + $j('.homepage div.left').width()) &&
						 event.pageY > ($j('.homepage div.right').offset().top + $j('.homepage div.right').height())) {
					// right bottom
					ui.placeholder.appendTo($j('.homepage div.right'))

				} else if (event.pageX < (topOffset.left + $j('.homepage div.top').width()) &&
						   event.pageY < (topOffset.top + $j('.homepage div.top').height())) {
					// top bottom
					if ($j('.homepage div.top').find('.box').length == 0) {
						ui.placeholder.appendTo($j('.homepage div.top'));
					}
                }
//                else if (event.pageX < (bottomOffset.left + $j('.homepage div.bottom').width()) &&
//                    event.pageY < (bottomOffset.top + $j('.homepage div.bottom').height())) {
//                    // top bottom
//                    if ($j('.homepage div.bottom').find('.box').length == 0) {
//                        ui.placeholder.appendTo($j('.homepage div.bottom'));
//                    }
//                }
				$j('.cal-bubble').hide();

			}
		});
	},

	activeComponents: function() {

		$j('.box h2').css('cursor', 'move');
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

		$j("a.edit").mouseenter(function() {
			var $this = $j(this);

			if(!$this.children('em').data('pinned')) {
				$this.children('em').addClass('show-it');
			}

        }).mouseleave(function() {
            var $this = $j(this);

            if(!$this.children('em').data('pinned')) {
                $this.children('em').removeClass('show-it' );
            }

		});
		$j(document).on('click', "a.edit", function(event) {
			var $this = $j(this);
			event.preventDefault();

			if ($this.siblings('.editPanel.delete-it').is(':visible')) {
				$this.siblings('.editPanel.delete-it').slideUp();
			}

			$this.toggleClass('active');
			$this.siblings('.editPanel.settings.hide').slideToggle("fast");
			$this.children('em').data('pinned', !$this.children('em').data('pinned'));

		});

		$j(document).on('click', '.close-delete', function(event){
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

		$j(document).on('click', '.delete-accept', function(event){
			event.preventDefault();
			//update with an ajax call here.
			$j.post(setupCustomise.ajaxURL, $j(this).closest('form').serialize());

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

		$j(document).on('click','.delete-reject',function(event) {
			event.preventDefault();
			$j(this).closest('.delete-it').slideUp();
		});
	},

	/**
	 * Updates colors for the given element
	 * @param element Element to update
	 * @param color   Color to update to
	 */
	updateColor: function(element, color) {
		switch (element) {
			case 'top_bg_primary':
				return $j('.top .text, .top .listing, .top .information-list li').css('background-color', color);
				break;
			case 'top_bg_secondary':
				return $j('.top .dark').css('background-color', color);
				break;
			case 'top_head_font':
				return $j('.top h2').css('color', color);
				break;
			case 'top_head_bg':
				return $j('.top h2').css('background-color', color)
				break;
			case 'top_font':
				return $j('.top, .top p').css('color', color)
				break;
			case 'top_link':
				return $j('.top a').css('color', color);
				break;
			case 'left_bg_primary':
				return $j('.left .text, .left .listing, .left .information-list li').css('background-color', color);
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
				return $j('.right .text, .right .listing, .right .information-list li').css('background-color', color);
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
};