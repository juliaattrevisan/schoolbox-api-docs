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
            $j('.customise').click(function(event) {
                $j('#tabs-homepage, .empty-column').slideToggle();
                $j(this).children('span').toggle();
                $j('h2.hide').each(function () {
                    $j(this).toggle();
                });
                return false;
            });

            this.colours(setupCustomise.id);
            this.components();
            this.columnWidths();
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


            $j(".add-component a").live('click', function(event) {
                event.preventDefault();
                var $this = $j(this);

                /* Polymorphic key
                 */
                var data = {
                    add : $j(this).attr('rel')
                };

                data[setupCustomise.key] = setupCustomise.id;

                $j.ajax({
                    type: "POST",
                    url: setupCustomise.ajaxURL,
                    data: data,
                    success: function(data){
                        var target = $j('.left')[0];
                        if (target) {
                            var new_item = $j(data);
                            var new_id   = new_item.filter('.box.clearfix').attr('id');
                            new_item.hide().css('opacity','0');
                            var new_html = $j('<div/>').append(new_item.clone()).html();
                            target.innerHTML = new_html + target.innerHTML;
                            $j('.left').find('#'+new_id).slideDown(400, function() {

                                setupCustomise.emptyColumnCheck([$j('div.top'), $j('div.right'), $j('div.left')]);
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
                        }
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
            $j('div.colorBox').each(function () {
                $j(this).css('backgroundColor', pickers[$j(this).attr('id')]);
            });

            var is_colorpicker_present = ($j('#picker').length > 0);
            var colorpicker            = null;
            if (is_colorpicker_present) {
                colorpicker = $j.farbtastic('#picker');
                colorpicker.setColor("#b6b6ff");
            }
            else {
                return;
            }

            $j('#head_font').parent().addClass('active-box');
            var instantColour = new RGBColor($j('#left_head_font').css('background-color'));
            var initialColour = instantColour.toHex();
            checkTextColour();
            $j('#currentColour').css('background-color',initialColour);
            $j('#currentColour').val(initialColour);

            colorpicker.linkTo(onColorChange);

            $j('#currentColour').keyup(function() {
                colorpicker.setColor($j(this).val());
            });

            function h2d(h) {return parseInt(h,16);}

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

                    case 'top_bg_primary':
                        return $j('.top .text, .top .listing').css('background-color', color);
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
                        return $j('.left .text, .left .listing').css('background-color', color);
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
                        return $j('.right .text, .right .listing').css('background-color', color);
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

                var color = new RGBColor($j(this).children('.colorBox').css('background-color'));

                f.setColor(color.toHex());
                checkTextColour()
                return false;
            });

            $j("#leftRight").click(function () {
                var rightBox;

                $j('div.leftColumn div.colorBox').each(function () {
                    var str = $j(this).attr('id');
                    var newstr = str.replace(/^.*left_(.*).*$/m, '$1');
                    rightBox = $j('#' + 'right_'+newstr);
                    rightBox.css({
                        'background-color' : $j(this).css('backgroundColor')
                    });
                    rightBox.next().val($j(this).next().val());
                });

                $j('.right .text, .right .listing').css('background-color', $j('.left .text').css('background-color'));
                $j('.right .dark').css('background-color', $j('.left .dark').css('background-color'));
                $j('.right, .right p').css('color', $j('.left').css('color'));
                $j('.right h2').css({
                    'background-color': $j('.left h2').css('background-color'),
                    'color':  $j('.left h2').css('color')
                });
                $j('.right, .right p').css('color', $j('.left, .left p').css('color'));
                $j('.right a').css('color', $j('.left a').css('color'));

                //send off the pickers correctly.
                pickers.right_head_font = pickers.left_head_font;
                pickers.right_head_bg = pickers.left_head_bg;
                pickers.right_font = pickers.left_font;
                pickers.right_bg_primary = pickers.left_bg_primary;
                pickers.right_bg_secondary = pickers.left_bg_secondary;
                pickers.right_link = pickers.left_link;
                pickers.right_hover = pickers.left_hover;

            });

            $j('#restore').click(function() {
                $j('.colorBox').each(function () {
                    $j(this).css('background-color', defaults[$j(this).attr('id')]);
                });

                resetColours(['.top', '.left' ,'.right']);

                pickers = defaults;


            });

            function resetColours(area) {

                $j.each(area, function(index, value) {

                    var areaClass = value,
                        areaVariable = value.substr(1);

                    $j(areaClass).find('h2').css({
                        "color": defaults[areaVariable+'_head_font'],
                        "background-color": defaults[areaVariable+'_head_bg']
                    });

                    $j(areaClass).find('p, h3, .listing p.small').css({
                        "color": defaults[areaVariable+'_font']
                    });

                    $j(areaClass).find('a, .listing p.small a').css({
                        'color': defaults[areaVariable+'_link']
                    });

                    $j(areaClass).find('.text, .listing').css({
                        'background': defaults[areaVariable+'_bg_primary']
                    });

                    $j(areaClass).find('.dark').css({
                        'background':  defaults[areaVariable+'_bg_secondary']
                    });

                });
                return false;
            }

            $j('#cancel').click(function() {
                $j('.colorBox').each(function () {
                    $j(this).css('background-color', original[$j(this).attr('id')]);
                });

                $j('.text, .dark, h2:not(.hide), a, p').each(function () {
                    $j(this).removeAttr("style");
                });

                pickers = original;

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
            },
            update: function () {
                var left = '';
                var right = '';
                var top = '';
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

                var data = {
                    type: 'components',
                    column1: left,
                    column2: right,
                    column3: top
                }

                data[setupCustomise.key] = setupCustomise.id;

                $j.post(setupCustomise.ajaxURL, data);

                setupCustomise.emptyColumnCheck([$j('.homepage div.top'), $j('.homepage div.right'), $j('.homepage div.left')])
            },
            sort: function (event, ui) {
                var leftOffset = $j('.homepage div.left').offset();
                var topOffset = $j('.homepage div.top').offset();

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

        $j("a.config-this").hover(function() {
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

            var sibs;

            if((sibs = $this.siblings('.editPanel.settings.hide')).length) {
                sibs.slideToggle("fast");
            }
            else if((sibs = $this.parent('span').siblings('.editPanel.settings.hide')).length) {
                sibs.slideToggle("fast");
            }

            $this.children('em').data('pinned', !$this.children('em').data('pinned'));

        });

        $j('.delete-this').live('click', function(event){
            event.preventDefault();
            var $this = $j(this);

            if ($this.siblings('.editPanel.settings').is(':visible')) {
                $this.siblings('.editPanel.settings').slideUp();
                $this.siblings('a.config-this').children('em').data('pinned', !$this.siblings('a.config-this').children('em').data('pinned'));
                $this.siblings('a.config-this').removeClass('active');
                $this.siblings('a.config-this').children('em').removeClass('show-it');
            }
            $this.siblings('.editPanel.delete-it.hide').slideToggle("fast");
        });

        $j('.delete-accept').live('click',function(event) {

            $this = homepageControl;

            // console.log($this);

            $j('.delete-this').live('click',function() {
                var $instanceId = $j(this).parent().attr('id').match(/^instance\-id\-(\d+)$/)[1];

                $j(this).parent().slideUp(100,function() {
                    $j(this).remove();
                    $this.refreshPalette();
                });

                $j.post('/adminv2/homepage/remove-instance', {instanceId: $instanceId}, function () {

                });

                return false;
            });



            /*
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
            */

        });

        $j('.delete-reject').live('click',function(event) {
            event.preventDefault();
            $j(this).closest('.delete-it').slideUp();
        });
    }
};