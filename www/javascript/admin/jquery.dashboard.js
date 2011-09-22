(function($) {
    $(document).ready(function () {

        $j.fn.qtip.defaults.position.corner = {
            target: 'topLeft',
            tooltip: 'bottomLeft'
        }

        $j.fn.qtip.defaults.style = {
            tip: 'bottomLeft',
            name: 'green'
        }

        $j('h2 span').each(function() {
            activeContent = $j(this).siblings('em').html();
            $j(this).qtip({
                content: activeContent,
                api: {
                        // Retrieve the content when tooltip is first rendered
                         onRender: function()
                         {
                            var self = this;
                         }
                     }
            });
        });

        $('#definition-palette .component').draggable({
            helper: 'clone',
            connectToSortable: '#dashboard-layout .component-holder'
        });

        $('#dashboard-layout .component-holder').sortable({
            helper: 'clone',
            connectWith: '#dashboard-layout .component-holder',
            receive: function (event, ui) {
            },
            update: function (event, ui) {
                if ( ui.sender != null ) {
                    console.log(ui.helper, ui.item, ui.placeholder, ui.sender, ui);
                }
            },
            placeholder: 'marker',
            items: '.component',
            cursor: 'move',
            stop: function(event, ui) {
                if (!(ui.item.find('.delete-this').length > 0)) {
                ui.item.append('<a href="#" class="delete-this">x</a>')
                }
                console.log(ui.item);
            }
			
        });

        $('.delete-this').live('click',function() {
            $(this).parent().slideUp(100,function(){
                $(this).remove()
            })
            return false;
        })

        $('#group-select').change(function () {
            var $groupId = $(this).val();

            $.get('/adminv2/dashboard/get-dashboard/'+$groupId, function ($layout) {
                $('#dashboard-layout').fadeOut(function() {
                    $(this).empty().append($layout);
                    $(this).fadeIn();
                });

              $('#group-select').change(function () {
                    var $groupId = $(this).val();

                    $.get('/adminv2/dashboard/get-dashboard/'+$groupId, function ($layout) {
                       $('#dashboard-layout').fadeOut(function() {
                            $(this).empty().append($layout);
                            $(this).fadeIn();
                        });

                        $('#dashboard-layout .layout-position').sortable({
                            connectWith: '#dashboard-layout .component-holder',
                            helper: 'clone'
                        });
                    });
                })

                $('#dashboard-layout div.layout-position').sortable({
                    connectWith: '#dashboard-layout .component-holder'
                });
            });
        })
    });
})(jQuery);
