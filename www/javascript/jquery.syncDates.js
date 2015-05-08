/**
 * Syncs dates with each other - ensures that a start date is not after an end
 * date.
 *
 * Options:
 * * startDate, endDate: selectors for the start and end date input. If either
 *                       is left out, the DOM node in 'this' is used instead.
 * * dateFormat:         a momentJS date format to expect dates in
 */
(function($) {
    $.fn.syncDates = function(options) {
        // Format: use momentJS format if it's there, otherwise d/m/Y
        var dateFormat = 'DD/MM/YYYY';
        if ('momentJs' in dateDisplayFormats) {
            dateFormat = dateDisplayFormats.momentJs;
        }
        var defaults = {
            dateFormat: dateFormat,
            startDate:  null,
            endDate:    null
        };
        var settings = $.extend({}, defaults, options);

        // Set up startDate and endDate
        if (settings.startDate) {
            settings.startDate = $(settings.startDate);
        }
        else {
            settings.startDate = this;
        }
        if (settings.endDate) {
            settings.endDate = $(settings.endDate);
        }
        else {
            settings.endDate = this;
        }

        // Set up startDate and endDate change events
        var changeInputs = function(thisInput) {
            var startDate = moment(settings.startDate.val(), settings.dateFormat);
            var endDate   = moment(settings.endDate.val(), settings.dateFormat);
            if (startDate.isValid() && endDate.isValid() && endDate.valueOf() < startDate.valueOf()) {
                if (thisInput.is(settings.startDate)) {
                    settings.endDate.val(settings.startDate.val()).change();
                }
                else {
                    settings.startDate.val(settings.endDate.val()).change();
                }
            }
        }
        settings.startDate.bind('change.syncDates', function() {
            changeInputs($(this))
        });
        settings.endDate.bind('change.syncDates', function() {
            changeInputs($(this))
        });
        return this;
    }
})(jQuery);
