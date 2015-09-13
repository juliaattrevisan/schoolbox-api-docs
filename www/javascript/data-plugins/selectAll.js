/**
 * Select all/none checkboxes within a fieldset.
 *
 * Options:
 * * select-all: the element that contains the fields to select. Defaults to the closest fieldset.
 * * select-all-for-group: if set, ignores the container setting and acts as select all for any
 *                         checkbox with matching `data-select-all-group`.
 */
(function($){
    $(function(){
        $('[data-select-all]').each(function() {
            var selectAll  = $(this);
            var data       = selectAll.data();
            var checkboxes = $();
            if ('selectAllForGroup' in data) {
                checkboxes = $('input[type="checkbox"][data-select-all-group="'+data.selectAllForGroup+'"]')
            } else {
                checkboxes = selectAll.closest(data.selectAll || 'fieldset').find(':checkbox').not(selectAll);
            }

            // When the select all changes, update the checkboxes this is a select all for
            selectAll.on('change.selectAll', function() {
                checkboxes.prop('checked', selectAll.prop('checked'));
            });

            // When this changes, update its select all?
            checkboxes.on('change.selectAll', function() {
                selectAll.prop('checked', checkboxes.length === checkboxes.filter(':checked').length);
            });
        });
    })
})(jQuery);
