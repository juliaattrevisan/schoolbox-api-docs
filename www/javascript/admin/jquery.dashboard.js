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

        function initializeLayout () {
            $('#dashboard-layout .component-holder').sortable({
                helper: 'clone',
                connectWith: '#dashboard-layout .component-holder',
                update: function (event, ui) {
                    var $instanceId = $(ui.item).attr('id').match(/instance\-id\-(\d+)/);

                    if ( $instanceId != null ) {
                        $instanceId = $instanceId[1];
                    }

                    var $definitionId = $(ui.item).attr('class').match(/definition\-id\-(\d+)/)[1];

                    var $groupId = $('#group-select').val();

                    var $position = $(ui.item).parent().parent().attr('id').match(/position\-(\w+)/)[1];

                    var $sequence = $(ui.item).index() + 1;

                    var $component = {
                        definitionId: $definitionId,
                        groupId: $groupId,
                        position: $position,
                        sequence: $sequence
                    };

                    if ( $instanceId != null ) {
                        $component.instanceId = $instanceId;
                    }

                    $.post('/adminv2/dashboard/save-instance', $component, function ($instance) {
                        $(ui.item).attr('id', 'instance-id-'+$instance.id);

                        refreshPalette();
                    });
                },
                placeholder: 'marker',
                items: '.component',
                cursor: 'move',
                stop: function(event, ui) {
                    if (!(ui.item.find('.delete-this').length > 0)) {
                        ui.item.append('<a href="#" class="delete-this">x</a>')
                    }
                }

            });

            $('#definition-palette .component').draggable({
                helper: 'clone',
                connectToSortable: '#dashboard-layout .component-holder'
            });

            refreshPalette();
        }

        function refreshPalette () {
            $('#definition-palette .component').each( function () {
                var $definitionElement = $(this);

                var $definitionIdClass = $definitionElement.attr('class').match(/(definition\-id\-\d+)/)[1];

                if ( !$('#dashboard-layout .component').hasClass($definitionIdClass) ) {
                    $definitionElement
                        .draggable('enable')
                        .removeClass('disabled');
                }
                else {
                    $definitionElement
                        .draggable('disable')
                        .addClass('disabled');
                }
            } );
        }

        initializeLayout();

        $('.delete-this').live('click',function() {
            var $instanceId = $(this).parent().attr('id').match(/^instance\-id\-(\d+)$/)[1];
            console.log($(this).parent());

            $(this).parent().slideUp(100,function(){
                $(this).remove();
                refreshPalette();
            })

            $.post('/adminv2/dashboard/remove-instance', {instanceId: $instanceId}, function () {
                
            });

            return false;
        })

        $('#group-select').change(function () {
            var $groupId = $(this).val();

            $.get('/adminv2/dashboard/get-dashboard/'+$groupId, function ($layout) {
                $('#dashboard-layout').fadeOut(function() {
                    $(this).empty().append($layout);

                    $(this).fadeIn();

                    initializeLayout();
                });
            });
        });
    });
})(jQuery);
