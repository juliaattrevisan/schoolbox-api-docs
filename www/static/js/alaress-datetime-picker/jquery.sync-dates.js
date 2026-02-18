(function ($) {
    /**
     * Syncs dates with each other - ensures that a start date is not after an end
     * date.
     *
     * Options:
     * * startDate, endDate: selectors for the start and end date input. If either
     *                       is left out, the DOM node in 'this' is used instead.
     * * dateFormat:         a dayjs date format to expect dates in
     */
    $.fn.syncDates = function (options) {
        // Format: use dayjs format if it's there, otherwise d/m/Y
        var dateFormat = "DD/MM/YYYY";
        var element = this;

        if ("dayjs" in dateDisplayFormats) {
            dateFormat = dateDisplayFormats.dayjs;
        }

        var defaults = {
            dateFormat: dateFormat,
            startDate: null,
            endDate: null,
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

        settings.startDate.on("change.syncDates", function () {
            settings.startDate.syncDatesNow(settings);
        });

        settings.endDate.on("change.syncDates", function () {
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
     * * dateFormat:         a dayjs date format to expect dates in
     */
    $.fn.syncDatesNow = function (options) {
        var defaults = {
            dateFormat: "DD/MM/YYYY",
            startDate: null,
            endDate: null,
        };

        var settings = $.extend({}, defaults, options);

        if (settings.startDate) {
            settings.startDate = $(settings.startDate);

            var startDate = dayjs(settings.startDate.val(), settings.dateFormat, true);
            var endDate = dayjs($(this).val(), settings.dateFormat, true);

            if (startDate.isValid() && endDate.isValid() && startDate.isAfter(endDate)) {
                settings.startDate.val($(this).val()).trigger("change");
            }
        }

        if (settings.endDate) {
            settings.endDate = $(settings.endDate);

            startDate = dayjs($(this).val(), settings.dateFormat, true);
            endDate = dayjs(settings.endDate.val(), settings.dateFormat, true);

            if (startDate.isValid() && endDate.isValid() && startDate.isAfter(endDate)) {
                settings.endDate.val($(this).val()).trigger("change");
            }
        }
    };
})(jQuery);
