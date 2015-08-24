/**
 * Generic timepicker setup, based on data attributes
 */
(function($){
    $(function(){
        $('[data-timetablepicker]').on('click', function(event) {
            event.preventDefault();
            var form        = $(this).closest('form');
            var data        = $(this).data();
            var dateField   = form.find(':input[name="'+data['timetablepickerDateField']+'"]');
            var hourField   = form.find(':input[name="'+data['timetablepickerHourField']+'"]');
            var minuteField = form.find(':input[name="'+data['timetablepickerMinuteField']+'"]');
            var periodField = form.find(':input[name="'+data['timetablepickerPeriodField']+'"]');

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
