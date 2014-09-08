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
                var data = {
                    add : $j(this).attr('rel')
                };

                data[setupCustomise.key] = setupCustomise.id;

                $j.ajax({
                    type: "POST",
                    url: setupCustomise.ajaxURL,
                    data: data,
                    success: function(data){
                        $j(data).hide().prependTo('#container .left').css('opacity','0').slideDown(400,function(){
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

        colours: function() {}
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
            stop: function () {
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

        $j("a.config-this").mouseenter(function() {
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
        $j(document).on('click', 'a.config-this', function(event) {
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

        $j(document).on('click', '.delete-this', function(event){
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

        $j(document).on('click', '.delete-accept', function(event) {

            $this = homepageControl;

            // console.log($this);

            $j(document).on('click', '.delete-this', function() {
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

        $j(document).on('click', '.delete-reject', function(event) {
            event.preventDefault();
            $j(this).closest('.delete-it').slideUp();
        });
    }
};