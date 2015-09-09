/**
 * Select all/none checkboxes within a fieldset.
 *
 * Options:
 * * select-all: the element that contains the fields to select. Defaults to the closest fieldset.
 */
(function($){
    $(function(){
        $('[data-select-all]').each(function() {
            var selectAll  = $(this);
            var container  = selectAll.data('selectAll') || 'fieldset';
            var checkboxes = selectAll.closest(container).find(':checkbox').not(selectAll);

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
