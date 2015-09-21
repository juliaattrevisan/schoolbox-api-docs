/**
 * Setup for a timetable day selector.
 *
 * Expects moment.js to exist and be loaded.
 */
(function($) {
    "use strict";

    var TimetableDaySelector = function(element, settings) {
        var self     = this
        self.element = element
        var defaults = {
            dateFormat: 'DD/MM/YYYY',
            // Lists of campus, and its day counts and periods
            campusList: {},

            // Selectors for campus/day/period selector
            campusSelector:    'select[data-role="campusSelector"]',
            daySelector:       'select[data-role="daySelector"]',
            periodSelector:    'select[data-role="periodSelector"]',
            startTimeSelector: 'input[data-role="startTimeSelector"]',
            endTimeSelector:   'input[data-role="endTimeSelector"]',

            // Selectors for buttons
            useDateButton:       'a[data-interaction="useDate"]',
            previousDateButton:  'a[data-interaction="previousDate"]',
            nextDateButton:      'a[data-interaction="nextDate"]',
            pickStartTimeButton: 'a[data-interaction="pickStartTime"]',
            pickEndTimeButton:   'a[data-interaction="pickEndTime"]',

            // Selectors for display of date, start and end times
            dateDisplay:      'time[data-role="selectedDate"]',
            startTimeDisplay: 'time[data-role="selectedTimeStart"]',
            endTimeDisplay:   'time[data-role="selectedTimeEnd"]',

            // AJAX options for changing date
            getChangeDateAjax: function(campus, day, date, previous) {
                return {
                    data: {
                        campus:   campus,
                        day:      day,
                        previous: (previous ? 0 : 1),
                        date:     date
                    }
                }
            }
        }

        self.options = $.extend({}, defaults, settings)

        // Interface elements
        self.campusSelector      = element.find(self.options.campusSelector)
        self.daySelector         = element.find(self.options.daySelector)
        self.periodSelector      = element.find(self.options.periodSelector)
        self.startTimeSelector   = element.find(self.options.startTimeSelector)
        self.endTimeSelector     = element.find(self.options.endTimeSelector)
        self.previousDateButton  = element.find(self.options.previousDateButton)
        self.nextDateButton      = element.find(self.options.nextDateButton)
        self.useDateButton       = element.find(self.options.useDateButton)
        self.pickStartTimeButton = element.find(self.options.pickStartTimeButton)
        self.pickEndTimeButton   = element.find(self.options.pickEndTimeButton)
        self.dateDisplay         = element.find(self.options.dateDisplay)
        self.startTimeDisplay    = element.find(self.options.startTimeDisplay)
        self.endTimeDisplay      = element.find(self.options.endTimeDisplay)

        // Generic selector for all buttons
        self.buttons = $()
            .add(self.previousDateButton)
            .add(self.nextDateButton)
            .add(self.useDateButton)
            .add(self.pickStartTimeButton)
            .add(self.pickEndTimeButton)
        self.buttons.hide()

        /**
         * Event for when campus changes: change the list of available periods
         * and days, based on what is in self.options.campusList for the
         * selected campus.
         */
        self.campusSelector
            .on('change', function() {
                var campusId = $(this).val()

                // Repopulate campus days list, attempting to preserve last selected
                var daySelected = self.daySelector.val()
                self.daySelector.find('option[value]').remove()

                var campusDays = null
                if (campusId in self.options.campusList) {
                    campusDays = self.options.campusList[campusId].days
                }
                if (campusDays) {
                    self.daySelector.prop('disabled', false);
                    campusDays.forEach(function(day) {
                        self.daySelector
                            .append(
                            $('<option />')
                                .attr('label', day.name)
                                .attr('value', day.number)
                                .text(day.name)
                        )
                    });
                    if (daySelected !== null) {
                        self.daySelector.val(daySelected)
                    }
                }
                else {
                    self.daySelector.prop('disabled', true)
                }
                self.daySelector.trigger('change')
            })
            .trigger('change')

        /**
         * Event for when the day changes - get the next available date for this
         * timetable day.
         */
        self.daySelector.on('change', function() {
            self.changeDate(self.campusSelector.val(), self.daySelector.val())
        })

        /**
         * Event for when the period changes - fills in the start and end time.
         */
        self.periodSelector.on('change', function() {
            var selected  = $(this).find(':selected')
            var startTime = moment(selected.data('start'), 'H:mm')
            var endTime   = moment(selected.data('end'),   'H:mm')
            if (startTime.isValid()) {
                self.startTimeDisplay
                    .attr('datetime', startTime.format('HH:mm'))
                    .text(startTime.format('h:mm A'));
                self.startTimeSelector.prop('disabled', false);
                self.triggerSelectDateEvent()
            }
            else {
                self.startTimeDisplay
                    .removeAttr('datetime')
                    .text('');
                self.startTimeSelector.prop('disabled', true);
            }
            if (endTime.isValid()) {
                self.endTimeDisplay
                    .attr('datetime', endTime.format('HH:mm'))
                    .text(endTime.format('h:mm A'));
                self.endTimeSelector.prop('disabled', false);
                self.triggerSelectDateEvent()
            }
            else {
                self.endTimeDisplay
                    .removeAttr('datetime')
                    .text('');
                self.endTimeSelector.prop('disabled', true);
            }

            if (startTime.isValid() && endTime.isValid()) {
                $('[data-date-paginator]').removeClass('hide');
                $('[data-date-message]').addClass('hide');
            }
        });

        /**
         * Event when the next date button is clicked - skips ahead to the next
         * available date for the given timetable day.
         */
        self.nextDateButton.on('click', function(event) {
            event.preventDefault()
            var date = moment(self.dateDisplay.attr('datetime')).add('d', 1)
            if (date.isValid()) {
                self.changeDate(
                    self.campusSelector.val(),
                    self.daySelector.val(),
                    date.format('YYYY-MM-DD'),
                    false
                )
            }
        })

        /**
         * Event when the previous date button is clicked - drops back to the
         * previous available date for the given timetable day.
         */
        self.previousDateButton.on('click', function(event) {
            event.preventDefault()
            var date = moment(self.dateDisplay.attr('datetime'))
            if (date.isValid()) {
                self.changeDate(
                    self.campusSelector.val(),
                    self.daySelector.val(),
                    date.format('YYYY-MM-DD'),
                    true
                )
            }
        })

        /**
         * Event when use date button is clicked - triggers an event on the
         * base element.
         */
        self.useDateButton.on('click', function(event) {
            event.preventDefault()
            self.element.trigger(
                'useDate.timetableDaySelector',
                [
                    self.dateDisplay.attr('datetime'),
                    self.startTimeDisplay.attr('datetime'),
                    self.endTimeDisplay.attr('datetime'),
                ]
            )
        });

        /**
         * Event when pick start time button is clicked.
         */
        self.pickStartTimeButton.on('click', function(event) {
            event.preventDefault();
            self.element.trigger(
                'pickTime.timetableDaySelector',
                [self.dateDisplay.attr('datetime'), self.startTimeDisplay.attr('datetime')]
            );
        });

        /**
         * Event when pick end time button is clicked.
         */
        self.pickEndTimeButton.on('click', function(event) {
            event.preventDefault();
            self.element.trigger(
                'pickTime.timetableDaySelector',
                [self.dateDisplay.attr('datetime'), self.endTimeDisplay.attr('datetime')]
            );
        });
    };

    /**
     * Changes the date displayed on the timetable date selector.
     *
     * @param campus
     * @param day
     * @param date
     * @param previous
     */
    TimetableDaySelector.prototype.changeDate = function(campus, day, date, previous) {
        var self = this
        self.buttons.prop('disabled', true).addClass('disabled')
        self.dateDisplay
            .removeAttr('datetime')
            .text('Loading date...')
            .removeClass('error')

        $.ajax(self.options.getChangeDateAjax(campus, day, date, previous))
        .done(function(data) {
            var date = moment(data.date)
            self.dateDisplay
                .attr('datetime', date.format('YYYY-MM-DD'))
                .text(date.format(self.options.dateFormat))
            self.buttons.prop('disabled', false).removeClass('disabled').show()
            self.triggerSelectDateEvent()

            // Populate periods if they're provided.
            if (data.periods) {
                var periodSelected = self.periodSelector.val()
                self.periodSelector.find('option[value]').remove()
                self.periodSelector.prop('disabled', false)
                data.periods.forEach(function(period, index) {
                    self.periodSelector
                        .append(
                        $('<option />')
                            .attr('label', period.desc)
                            .attr('value', index)
                            .data('start', period.start)
                            .data('end',   period.end)
                            .text(period.desc)
                    )
                })
                if (periodSelected !== null) {
                    self.periodSelector.val(periodSelected)
                }
                self.periodSelector.trigger('change')
            }
            else {
                self.periodSelector.prop('disabled', true)
            }
        })
        .fail(function(data) {
            self.dateDisplay
                .text(data.responseJSON.error)
                .addClass('error')
            self.buttons.hide()
        })
    }

    /**
     * Triggers a select date event on the element.
     */
    TimetableDaySelector.prototype.triggerSelectDateEvent = function() {
        this.element.trigger(
            'selectDate.timetableDaySelector',
            [
                this.dateDisplay.attr('datetime'),
                this.startTimeDisplay.attr('datetime'),
                this.endTimeDisplay.attr('datetime')
            ]
        )
    };

    /**
     * Sets up the timetable day selector.
     *
     * @param  method The method to call, defaults to init
     * @param  params Whatever params to pass to method
     * @return this
     */
    $.fn.timetableDaySelector = function(method, params) {
        if (!(typeof method === 'string')) {
            params = method
            method = 'init'
        }

        switch (method) {
            case 'init':
                return this.each(function() {
                    var element = $(this)
                    element.data('timetableDaySelector', new TimetableDaySelector(element, params));
                })
            default:
                return this
        }
    }

    // Possible events:
    // changeCampus/changeDay/changePeriod
    // selectDay

    // Possible methods:
    // getCampus/getDay/getPeriod
    // getDate
    // getStartTime
    // getEndTime

}(jQuery));
