/**
 * Checkbox greenerizer plugin. What does greenerize mean anyway?
 *
 * Options:
 * * applyClasses: defaults to true. If false, doesn't apply classes.
 */
(function($) {
$.fn.greenerize = function(options) {
    "use strict";

    var defaults = {
        applyClasses: true,
        triggerChange: false
    };
    var settings = $.merge({}, defaults, options);

    if (settings.applyClasses) {
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
    }

    this.find('.select_all').change(function(){
        var checkboxes = $(this).closest('.checkboxes').find(':checkbox').not('.select_all')
        if ($(this).is(':checked')) {
            checkboxes.prop('checked', true);
            if (settings.applyClasses) {
                checkboxes.parent().addClass('active-checkbox');
            }
        } else {
            checkboxes.prop('checked', false);
            if (settings.applyClasses) {
                checkboxes.parent().removeClass('active-checkbox');
            }
        }
        if (settings.triggerChange) {
            checkboxes.each(function() {
                $(this).change();
            });
        }
    });
    return this;
}
})(jQuery);
