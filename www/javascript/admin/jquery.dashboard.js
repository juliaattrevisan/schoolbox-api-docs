(function($) {
    $(document).ready(function () {
        $('#definition-palette li').draggable({
            helper: 'clone',
            connectToSortable: '#dashboard-layout div.layout-position'
        });

        $('#dashboard-layout div.layout-position').sortable({
            helper: 'clone',
            connectWith: '#dashboard-layout div.layout-position',
            receive: function (event, ui) {
            },
            update: function (event, ui) {
                if ( ui.sender != null ) {
                    console.log(ui.helper, ui.item, ui.placeholder, ui.sender, ui);
                }
            }
        });

        $('#group-select').change(function () {
            var $groupId = $(this).val();

            $.get('/adminv2/dashboard/get-dashboard/'+$groupId, function ($layout) {
                $('#dashboard-layout').empty().append($layout);

                $('#group-select').change(function () {
                    var $groupId = $(this).val();

                    $.get('/adminv2/dashboard/get-dashboard/'+$groupId, function ($layout) {
                        $('#dashboard-layout').empty().append($layout);

                        $('#dashboard-layout div.layout-position').sortable({
                            connectWith: '#dashboard-layout div.layout-position'
                        });
                    });
                })

                $('#dashboard-layout div.layout-position').sortable({
                    connectWith: '#dashboard-layout div.layout-position'
                });
            });
        })

    });
})(jQuery);
