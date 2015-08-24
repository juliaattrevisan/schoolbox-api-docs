/**
 * Generic datepicker sync setup, based on data attributes
 *
 * Options:
 * * startElement: sync with this start date
 * * endElement:   sync with this end date
 */
(function($){
    $(function(){
        $('[data-datepicker-sync]').each(function() {
            var element = $(this);
            var options = {};

            var startElement = element.data('datepicker-sync-startdate');
            if (startElement) {
                var start = $('#'+startElement);
                if (start.length) {
                    options.startDate = start;
                }
            }
            var endElement = element.data('datepicker-sync-enddate');
            if (endElement) {
                var end = $('#'+endElement);
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
