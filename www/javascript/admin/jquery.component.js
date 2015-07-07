setupCustomise = {

    ajaxURL: '',
    id: '',
    key: 'cid',
    leftColumnWidth: '',

    emptyColumnCheck: function(target) {
        for (i=0; i < target.length; i++ ) {
            var column = $(target[i]);
            if (column.find('.box').length == 0) {
                column.addClass('empty-column');
                if ($('#tabs-homepage').is(':visible')) {
                    $('.empty-column').each(function() {
                        $(this).show();
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
            $('#tabs-homepage').tabs();
            $('#tabs-sub').tabs();
            $('.customise').click(function(event) {
                $('#tabs-homepage, .empty-column').slideToggle();
                $(this).children('span').toggle();
                $('h2.hide').each(function () {
                    $(this).toggle();
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
            $('.add-component strong').each(function() {
                var howManyInstances = $(this).children('em').html();
                if (howManyInstances == 0) {
                    howManyInstances = '<strong>no</strong> instances '
                } else if (howManyInstances == 1)  {
                    howManyInstances = '<strong>1</strong> instance '
                } else {
                    howManyInstances = '<strong>' + howManyInstances + '</strong> instances '
                }

                $(this).qtip({
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


            $(document).on('click', ".add-component a", function(event) {
                event.preventDefault();
                var $this = $(this);

                /* Polymorphic key
                 */
                var data = {
                    add : $(this).attr('rel')
                };

                data[setupCustomise.key] = setupCustomise.id;

                $.ajax({
                    type: "POST",
                    url: setupCustomise.ajaxURL,
                    data: data,
                    success: function(data){
                        $(data).hide().prependTo('#container .left').css('opacity','0').slideDown(400,function(){
                            setupCustomise.emptyColumnCheck([$('div.top'), $('div.right'), $('div.left')]);
                            $(this).animate({opacity:1}, 300);
                            /*
                             * When a new component is added in we need it to
                             * be able to get the colours that have been changed before
                             * the component is added.
                             * 1. Colour is changed
                             * 2. "add" is clicked on any component
                             * 3. Apply the colour change to this new component's DOM elements.
                             * */
                            var newComponent = $(this);

                            newComponent.find('h2').css({
                                'background-color': $('#left_head_bg').css('background-color'),
                                'color': $('#left_head_font').css('background-color')
                            });

                            newComponent.find('.text, .listing').css({
                                'background-color': $('#left_bg_primary').css('background-color')
                            });

                            newComponent.css({
                                'color': $('#left_font').css('background-color')
                            });

                            newComponent.find('p').css({
                                'color': $('#left_font').css('background-color')
                            });

                            newComponent.find('.dark').css({
                                'background-color' : $('#left_bg_secondary').css('background-color')
                            });

                            newComponent.find('a').css({
                                'color' : $('#left_link').css('background-color')
                            });

                        });
                    },
                    dataType: "html"
                });

                if($this.parent().hasClass('one-only')) {
                    $this.parent().addClass('disabled');
                } else if ($('.how-many').html() == '') {
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
                        $(this).delay(2000).fadeOut();
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

        $('div.homepage').sortable({
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
                $('.marker').text('This is the new position of your component');
            },
            stop: function () {
                var left = '';
                var right = '';
                var top = '';
                $('.homepage div.left').children('div.box').each(function () {

                    /* Two components in ePorfolio have no id's (My Profile, Teacher Notes)
                     * and always appear in the same positions. So we do a check to see if
                     * an id is set. If it isn't, and we don't do the check, the script breaks
                     * and components cannot be relocated successfully
                     */
                    if ($(this).attr('id') != undefined) {
                        left += $(this).attr('id').substring(9) + ',';
                    }
                });

                $('.homepage div.right').children('div.box').each(function () {
                    if ($(this).attr('id') != undefined) {
                        right += $(this).attr('id').substring(9) + ',';
                    }
                });

                $('.homepage div.top').children('div.box').each(function () {
                    if ($(this).attr('id') != undefined) {
                        top += $(this).attr('id').substring(9) + ',';
                    }
                });

                var data = {
                    type: 'components',
                    column1: left,
                    column2: right,
                    column3: top
                }

                data[setupCustomise.key] = setupCustomise.id;

                $.post(setupCustomise.ajaxURL, data);

                setupCustomise.emptyColumnCheck([$('.homepage div.top'), $('.homepage div.right'), $('.homepage div.left')])
            },
            sort: function (event, ui) {
                var leftOffset = $('.homepage div.left').offset();
                var topOffset = $('.homepage div.top').offset();

                if (event.pageX < (leftOffset.left + $('.homepage div.left').width()) &&
                    event.pageY > (leftOffset.top + $('.homepage div.left').height())) {
                    // left bottom
                    ui.placeholder.appendTo($('.homepage div.left'));
                }
                else if (event.pageX > (leftOffset.left + $('.homepage div.left').width()) &&
                    event.pageY > ($('.homepage div.right').offset().top + $('.homepage div.right').height())) {
                    // right bottom
                    ui.placeholder.appendTo($('.homepage div.right'))

                } else if (event.pageX < (topOffset.left + $('.homepage div.top').width()) &&
                    event.pageY < (topOffset.top + $('.homepage div.top').height())) {
                    // top bottom
                    if ($('.homepage div.top').find('.box').length == 0) {
                        ui.placeholder.appendTo($('.homepage div.top'));
                    }
                }
                $('.cal-bubble').hide();

            }
        });
    },

    activeComponents: function() {

        $('.box h2').css('cursor', 'move');
        $(".box h2").mousemove(function(e){
            $("#tooltip").show();
            $("#tooltip").css({
                top: (e.pageY -8) + "px",
                left: (e.pageX + 20) + "px"
            });
        });
        $(".box h2").mouseout(function(e){
            $("#tooltip").hide();
        });

        $("a.config-this").mouseenter(function() {
            var $this = $(this);

            if(!$this.children('em').data('pinned')) {
                $this.children('em').addClass('show-it');
            }

        }).mouseleave(function() {
            var $this = $(this);

            if(!$this.children('em').data('pinned')) {
                $this.children('em').removeClass('show-it' );
            }

        });
        $(document).on('click', 'a.config-this', function(event) {
            var $this = $(this);
            event.preventDefault();

            if ($this.siblings('.editPanel.delete-it').is(':visible')) {
                $this.siblings('.editPanel.delete-it').slideUp();
            }

            $this.toggleClass('active');

            var sibs;

            if((sibs = $this.siblings('.editPanel.settings')).length) {
                sibs.slideToggle("fast");
            }
            else if((sibs = $this.parent('span').siblings('.editPanel.settings')).length) {
                sibs.slideToggle("fast");
            }

            $this.children('em').data('pinned', !$this.children('em').data('pinned'));

        });

        $(document).on('click', '.delete-this', function(event){
            event.preventDefault();
            var $this = $(this);

            if ($this.siblings('.editPanel.settings').is(':visible')) {
                $this.siblings('.editPanel.settings').slideUp();
                $this.siblings('a.config-this').children('em').data('pinned', !$this.siblings('a.config-this').children('em').data('pinned'));
                $this.siblings('a.config-this').removeClass('active');
                $this.siblings('a.config-this').children('em').removeClass('show-it');
            }
            $this.siblings('.editPanel.delete-it').slideToggle("fast");
        });

        $(document).on('click', '.delete-accept', function(event) {

            $this = homepageControl;

            // console.log($this);

            $(document).on('click', '.delete-this', function() {
                var $instanceId = $(this).parent().attr('id').match(/^instance\-id\-(\d+)$/)[1];

                $(this).parent().slideUp(100,function() {
                    $(this).remove();
                    $this.refreshPalette();
                });

                $.post('/adminv2/homepage/remove-instance', {instanceId: $instanceId}, function () {

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

        $(document).on('click', '.delete-reject', function(event) {
            event.preventDefault();
            $(this).closest('.delete-it').slideUp();
        });
    }
};