/**
 * Automatically apply a plugin to a predefined data attribute.
 * This saves you from having to instantiate plugins manually.
 *
 * Generic datepicker sync setup, based on data attributes
 *
 * Usage:
 * <input name="publish_date" id="publish_date" data-datepicker data-datepicker-sync data-datepicker-sync-enddate="open_date" />
 * <input name="open_date" id="open_date" data-datepicker />
 *
 * Options:
 * * startdate: A selector identifying a date this date isn't before.
 * * enddate:   A selector identifying a date this date isn't after.
 *
 * Requires:
 * * syncDates
 */
(function($){
    $(function(){
        $('[data-datepicker-sync]').each(function() {
            var element = $(this);
            var options = {};

            var startElement = element.data('datepicker-sync-startdate');
            if (startElement) {
                var start = $(startElement);
                if (start.length) {
                    options.startDate = start;
                }
            }
            var endElement = element.data('datepicker-sync-enddate');
            if (endElement) {
                var end = $(endElement);
                if (end.length) {
                    options.endDate = end;
                }
            }
            if (options.startDate || options.endDate) {
                element.syncDates(options)
            }
        });
    })
})(jQuery);
