/**
 * Checkbox greenerizer plugin. What does greenerize mean anyway?
 */
(function($) {
$.fn.greenerize = function() {
    "use strict";

    this.find(':checkbox').each(function () {
        if ($(this).is(':checked')) {
            $(this).parent().addClass('active-checkbox');
        }
    });

    this.find(':checkbox').click(function () {
        if ($(this).parent().hasClass('active-checkbox')) {
            $(this).parent().removeClass('active-checkbox')
            if ($(this).closest('.checkboxes').find('.select_all').is(':checked')) {
                $(this).closest('.checkboxes').find('.select_all').prop('checked', false).parent().removeClass('active-checkbox');
            }
        }
        else {
            $(this).parent().addClass('active-checkbox');
        }
    });

    this.find('.select_all').change(function(){
        if ($(this).is(':checked')){
            $(this).closest('.checkboxes').find(':checkbox').not('.select_all').prop('checked', true).parent().addClass('active-checkbox');
        } else {
            $(this).closest('.checkboxes').find(':checkbox').not('.select_all').prop('checked', false).parent().removeClass('active-checkbox');
        }
    });
    return this;
}
})(jQuery);
