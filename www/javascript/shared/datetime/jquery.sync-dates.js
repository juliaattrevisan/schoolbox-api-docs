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
        };

        settings.startDate.bind('change.syncDates', function() {
            changeInputs($(this))
        });

        settings.endDate.bind('change.syncDates', function() {
            changeInputs($(this))
        });

        return this;
    };

    /**
     * Syncs datetimes with each other - ensures that a start datetime is not after an end
     * datetime.
     *
     * Options:
     * dateFormat:         a momentJS date format to expect dates in
     * start: the start datetime as a string
     * end: the end datetime as a string
     *
     */
    $.fn.syncDatetimes = function(options) {

        // Format: use momentJS format if it's there, otherwise d/m/Y
        var dateFormat = 'DD/MM/YYYY h:mm a';

        var defaults = {
            dateFormat: dateFormat,
            startDate: null,
            startHour: null,
            startMinute: null,
            startPeriod: null,
            endDate: null,
            endHour: null,
            endMinute: null,
            endPeriod: null
        };

        var settings = $.extend({}, defaults, options);

        // Set up startDate and endDate change events

        /* thisInput is the input element that you just modified
         * which triggered the datetime synchronisation
         */
        var changeInputs = function(thisInput) {
            var start = settings.startDate.val() + ' ' + settings.startHour.val() + ':' + settings.startMinute.val() + ' ' + settings.startPeriod.val().toLowerCase();
            var end = settings.endDate.val() + ' ' + settings.endHour.val() + ':' + settings.endMinute.val() + ' ' + settings.endPeriod.val().toLowerCase();

            var startDatetime = moment(start, settings.dateFormat);
            var endDatetime   = moment(end, settings.dateFormat);

            /* If both dates are valid and end date occurs before start
             */
            if (startDatetime.isValid() && endDatetime.isValid()) {

                /* If thisInput is a Start* input, alter the End* inputs
                 * Otherwise, if it's an End* input, alter the Start inputs
                 */
                if (endDatetime.valueOf() < startDatetime.valueOf()) {
                    if (isStartElement(thisInput)) {
                        settings.endDate.val(settings.startDate.val());
                        settings.endHour.val(settings.startHour.val());
                        settings.endMinute.val(settings.startMinute.val());
                        settings.endPeriod.val(settings.startPeriod.val());
                    }
                    else {
                        settings.startDate.val(settings.endDate.val());
                        settings.startHour.val(settings.endHour.val());
                        settings.startMinute.val(settings.endMinute.val());
                        settings.startPeriod.val(settings.endPeriod.val());
                    }
                }
            }
        };

        settings.startDate.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.startHour.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.startMinute.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.startPeriod.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.endDate.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.endHour.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.endMinute.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        settings.endPeriod.bind('change.syncDates', function() {
            changeInputs($(this));
        });

        return this;

        /**
         * Determine if thisInput is one of the Start datetime elements
         * or one of the End datetime elements
         *
         * @param thisInput
         */
        function isStartElement(thisInput) {
            var elements = {
                start: [settings.startDate, settings.startHour, settings.startMinute, settings.startPeriod],
                end: [settings.endDate, settings.endHour, settings.endMinute, settings.endPeriod]
            };

            for (var i = 0; i < elements.start.length; i++) {
                if (thisInput.is(elements.start[i])) {
                    return true;
                }
            }

            return false;
        }
    }
})(jQuery);
