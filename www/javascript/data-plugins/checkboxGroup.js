/**
 * Checkbox group - a group of checkboxes of which no more than one may be selected.
 *
 * Options:
 */
(function($){
    $(function(){
        $(document).on('change.checkboxGroup', 'input[type="checkbox"][data-checkbox-group]', function() {
            var element = $(this);
            var group   = element.data('checkboxGroup');
            var others  = $('input[type="checkbox"][data-checkbox-group="'+group+'"]:checked').not(element);
            if (others.length) {
                others.prop('checked', false).trigger('checked');
            }
        });
    })
})(jQuery);
