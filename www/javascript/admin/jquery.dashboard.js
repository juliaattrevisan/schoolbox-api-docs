(function($) {
    $(document).ready(function () {

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
            cursor: 'move'
			
        });

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
