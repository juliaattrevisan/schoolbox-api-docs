/**
 * Checkbox greenerizer plugin. What does greenerize mean anyway?
 *
 * Options:
 * * applyClasses: defaults to true. If false, doesn't apply classes.
 *
 * Events:
 * * greenerizeChange: triggers when the select all checkbox is changed.
 *   Params:
 *   * element: the select all checkbox
 *   * checkboxes: the checkboxes that were affected by the change
 */
(function($) {
$.fn.greenerize = function(options) {
    "use strict";

    var defaults = {
        applyClasses: true,
        triggerChange: false
    };
    var settings = $.extend({}, defaults, options);
    var self     = this;

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
        var element    = $(this);
        var checkboxes = element.closest('.checkboxes').find(':checkbox').not('.select_all')
        if (element.is(':checked')) {
            checkboxes.prop('checked', true);
            self.trigger('greenerizeChange', [element, checkboxes]);
            if (settings.applyClasses) {
                checkboxes.parent().addClass('active-checkbox');
            }
        } else {
            checkboxes.prop('checked', false);
            self.trigger('greenerizeChange', [element, checkboxes]);
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
