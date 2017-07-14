(function($) {


    /**
     * Syncs dates with each other - ensures that a start date is not after an end
     * date.
     *
     * Options:
     * * startDate, endDate: selectors for the start and end date input. If either
     *                       is left out, the DOM node in 'this' is used instead.
     * * dateFormat:         a momentJS date format to expect dates in
     */
    $.fn.syncDates = function(options) {
        // Format: use momentJS format if it's there, otherwise d/m/Y
        var dateFormat = 'DD/MM/YYYY';
        var element = this;

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
        } else {
            settings.startDate = element;
        }

        if (settings.endDate) {
            settings.endDate = $(settings.endDate);
        } else {
            settings.endDate = element;
        }

        settings.startDate.bind('change.syncDates', function() {
            settings.startDate.syncDatesNow(settings);
        });

        settings.endDate.bind('change.syncDates', function() {
            settings.endDate.syncDatesNow(settings);
        });

        return element;
    };


    /**
     * Syncs dates on function call
     *
     * Options:
     * * startDate, endDate: selectors for the start and end date input. If either
     *                       is left out, the DOM node in 'this' is used instead.
     * * dateFormat:         a momentJS date format to expect dates in
     */
    $.fn.syncDatesNow = function(options) {

        var defaults = {
            dateFormat: 'DD/MM/YYYY',
            startDate:  null,
            endDate:    null
        };

        var settings = $.extend({}, defaults, options);

        if (settings.startDate) {
            settings.startDate = $(settings.startDate);

            var startDate = moment(settings.startDate.val(), settings.dateFormat, true);
            var endDate = moment($(this).val(), settings.dateFormat, true);

            if (startDate.isValid() && endDate.isValid() && startDate.isAfter(endDate)) {
                settings.startDate.val($(this).val()).change();
            }
        }

        if (settings.endDate) {
            settings.endDate = $(settings.endDate);

            startDate = moment($(this).val(), settings.dateFormat, true);
            endDate = moment(settings.endDate.val(), settings.dateFormat, true);

            if (startDate.isValid() && endDate.isValid() && startDate.isAfter(endDate)) {
                settings.endDate.val($(this).val()).change();
            }
        }

    };

})(jQuery);
