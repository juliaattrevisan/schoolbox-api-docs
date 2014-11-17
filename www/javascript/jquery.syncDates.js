/**
 * Syncs dates with each other - ensures that a start date is not after an end
 * date.
 *
 * Options:
 * * startDate, endDate: selectors for the start and end date input. If either
 *                       is left out, the DOM node in 'this' is used instead.
 * * converter:          a callback used to convert the value of startDate and
 *                       endDate into Date objects.
 *                       Defaults to working with dates in d/m/y format.
 */
(function($) {
    $.fn.syncDates = function(options) {
        var defaults = {
            startDate: null,
            endDate:   null,
            converter: function(dateString) {
                var parts = dateString.split('/')
                return new Date(parts[2], Number(parts[1])-1, parts[0], 0, 0, 0)
            }
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
            var startDate = settings.converter(settings.startDate.val());
            var endDate   = settings.converter(settings.endDate.val());
            if (endDate < startDate) {
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
