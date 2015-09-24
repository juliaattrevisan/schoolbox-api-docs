/**
 * Automatically apply a plugin to a predefined data attribute.
 * This saves you from having to instantiate plugins manually.
 *
 * Generic timetable picker setup, based on data attributes
 *
 * Usage:
 * <input type="text" name="date" data-datepicker />
 * <input type="text" name="hour" data-timepicker="hour" />
 * <input type="text" name="minute" data-timepicker="minute" />
 * <select name="period">
 *     <option value="am">AM</option>
 *     <option value="pm">PM</option>
 * </select>
 * <a href="#"
 *    data-timetablepicker
 *    data-timetablepicker-date-field="publish_date"
 *    data-timetablepicker-hour-field="publish_hour"
 *    data-timetablepicker-minute-field="publish_minute"
 *    data-timetablepicker-period-field="publish_period"
 *    data-timetablepicker-timetable-selector="#timetableSelectorDialog">
 *
 * Options:
 * * timetablepicker-date-field,
 *   timetablepicker-hour-field,
 *   timetablepicker-minute-field,
 *   timetablepicker-period-field:       selectors for inputs to update
 *   timetablepicker-timetable-selector: A modal that has already been set up with a timetable selector
 *
 * Requires:
 * * timetableSelector
 * * dateDisplayFormats
 * * momentJS
 */
(function($){
    $(function(){
        $('[data-timetablepicker]').on('click', function(event) {
            event.preventDefault();
            var data        = $(this).data();
            var dateField   = $(data['timetablepickerDateField']);
            var hourField   = $(data['timetablepickerHourField']);
            var minuteField = $(data['timetablepickerMinuteField']);
            var periodField = $(data['timetablepickerPeriodField']);

            // Several timetable pickers need to use the same modal.
            // Attach/detach events on modal open/close
            var timetableModal = $(data['timetablepickerTimetableSelector']);
            var closeButton    = timetableModal.find('[data-action="close"]');
            var useButton      = timetableModal.find('[data-action="useTime"]');

            var unbindEvents = function() {
                closeButton.off('click');
                useButton.off('click');
            };

            closeButton.on('click', function(event) {
                event.preventDefault();
                timetableModal.foundation('reveal', 'close');
                unbindEvents();
            });
            useButton.on('click', function(event) {
                event.preventDefault();
                var timetableSelector = timetableModal.find('[data-interaction="timetableSelector"]');
                var self  = timetableSelector.data('timetableDaySelector'),
                    radio = timetableSelector.find('input:checked');
                dateField.val(moment(self.dateDisplay.attr('datetime')).format(dateDisplayFormats.momentJs));
                if (radio.val()) {
                    var timeDisplay = radio.val() + 'TimeDisplay';
                    var time        = self[timeDisplay].attr('datetime');
                    if (time) {
                        var timeObject = moment(time, 'H:mm');
                        hourField.val(timeObject.format('h'));
                        minuteField.val(timeObject.format('mm'));
                        periodField.val(timeObject.format('a'));
                    }
                }
                timetableModal.foundation('reveal', 'close');
                unbindEvents();
            });
            timetableModal
                .foundation('reveal', 'open')
                .on('close.fndtn.reveal', function() {
                    unbindEvents();
                });
        });
    })
})(jQuery);
