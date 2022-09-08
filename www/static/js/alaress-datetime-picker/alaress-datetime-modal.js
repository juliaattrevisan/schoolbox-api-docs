/*
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

// Picker settings

// Build datepicker options here... again...
$.fn.alaressDateTimeModal = function (twigVars) {

    var $picker = $('#' + twigVars.element); // The input
    var $modal = $('#adtp-modal-' + twigVars.id);
    var pickerOptions = {
        // Flatpickr related
        enableTime: adtp.getValue($picker, 'data-datepicker-time'),
        enable: adtp.getValue($picker, 'data-datepicker-range'), // enabled date range, in the form "YYYY-MM-DD,YYYY-MM-DD"

        // All the rest
        altFormat: adtp.getValue($picker, 'data-datepicker-format'), // false|date|datetime
        allDaySwitch: adtp.getValue($picker, 'data-datepicker-alldayswitch'), // Show All Day switch
        allDayInput: adtp.getValue($picker, 'data-datepicker-alldayinput'),
        type: adtp.getValue($picker, 'data-datepicker-call'), // Ajax call type: events, duework or nothing
        endDay: adtp.getValue($picker, 'data-datepicker-endofday'), // Should allday change time to 23:59:59
        endPeriod: adtp.getValue($picker, 'data-datepicker-endofperiod') // Should allday change time to 23:59:59
    };
    var dictMostLoaded = 'Heaviest Workload';
    var dictTop5Loaded = '5 Heaviest Workloads';
    var dictNoWorkToday = 'Your students have nothing due this day';
    var heatCache = {};
    // Initial input value as dayjs object
    var dayjsDate = dayjs(twigVars.date);

    // Timetable picker
    var $byDate      = $('#byDate-' + twigVars.id),
        $byTimetable = $('#byTimetable-' + twigVars.id);

    /* Every time you switch between Select By Date and Select By Timetable
     * do all the stuff here: resize calendar height and shut any accordions
     * which may have opened
     */
    $byDate.on('toggled', function() {
        resizeCalendars();
        shutOpenedAccordions($modal);
    });

    $byTimetable.on('toggled', function() {
        resizeCalendars();
        shutOpenedAccordions($modal);
    });


    /**
     * Shut any opened accordions when viewing student workloads
     * Used when changing between the tabs and works in conjunction
     * with the collapsible event rebind
     *
     * @param $modal
     */
    function shutOpenedAccordions($modal) {
        $.each($modal.find('[data-collapse-state="uncollapsed"]'), function(index, element) {
            $.fn.executeCollapsableSnapShut($(element));
        });
    }


    // Timetable elements
    var timetable = {
        campus: $byTimetable.find('[data-adtp-tt-campus]'),
        term: $byTimetable.find('[data-adtp-tt-term]'),
        periods: $byTimetable.find('[data-adtp-tt-period]'), // there may be multiple period selectors, one per each campus
        submit: $byTimetable.find('[data-adtp-tt-submit]')
    };

    var fullCalendars = {};

    // Listen for the modal open
    $('#adtp-modal-wrapper-' + twigVars.id).on('opened.fndtn.reveal', function () {
        resetCalendar();
        adtp.setCalendarAllDay(isInputAllDay(), twigVars.id);
        if (twigVars.timetable) {
            resetTimetablePicker();
            setTimetableAllDay();
        }
    });

    if (twigVars.timetable) {

        // Converting JS date to database short formatbyDate-
        var initialDate = dayjsDate.format(adtp.dayjsFormats.databaseShort);

        // Set initial value to the timetable picker
        setTimetablePickerValue(initialDate);
        resetTimetablePicker();
        renderTimetableAllDay();

        // Listen for changes to campus
        timetable.campus.change(function () {

            resetCycles($('#byTimetable-' + twigVars.id).find('[data-adtp-tt-submit]').attr('data-timetable-value'));
            revealTimetable();
            revealByCampus();
            buildCycles();

        });

        // Listen for changes to term
        timetable.term.change(function () {
            buildCycles();
        });

        // Attach week cycle functionality to prev & next buttons
        $byTimetable.find('[data-tt-next]').click(function () {
            changeCycle($(this), false);
        });
        $byTimetable.find('[data-tt-prev]').click(function () {
            changeCycle($(this), true);
        });

        // Selecting a day
        $byTimetable.on('click', '[data-tt-day]:not(.disabled)', function () {

            setTimetablePickerValue($(this).attr('data-date'));
            resetDays();
            requestDataByDate($(this).attr('data-date'), 'timetable');

        });

        // trigger the pre-loaded day
        $byTimetable.find('[data-tt-day].active').trigger('click');


        // Catch period select change
        $byTimetable.find('[data-adtp-tt-period]').change(function () {
            updateTimeControls($(this));
        });

    }

    // If input value gets changed from outside, reset the timetable picker

    $picker.change(function () {
        if (isInputValueValid()) {
            resetCalendar();
            if (twigVars.timetable) {
                resetTimetablePicker();
            }
        }
    });

    // Setup calendar
    var newDate, latestDate;

    // Accommodating the "start of the week" SB config
    flatpickr.localize(flatpickr.l10ns.default);
    flatpickr.l10ns.default.firstDayOfWeek = twigVars.weekStartDay;

    var flatPickrConf = {
        defaultDate: twigVars.date,
        inline: true,
        altInput: false,
        allowInput: true,
        disableMobile: true,
        minuteIncrement: 1,
        enableTime: pickerOptions.enableTime,

        onReady: function (selectedDates, dateStr, instance) {
            renderAllDaySwitch();
            renderHeatMapHelp();

            if (pickerOptions.allDaySwitch === true) {
                if ($('#hideTime' + twigVars.id).is(':checked')) {
                    adtp.setCalendarAllDay(true, twigVars.id);
                }
                else {
                    adtp.setCalendarAllDay(false, twigVars.id);
                }

                $('#hideTime' + twigVars.id).on('click', function () {
                    if ($('#hideTime' + twigVars.id).is(':checked')) {
                        adtp.setCalendarAllDay(true, twigVars.id);
                    }
                    else {
                        adtp.setCalendarAllDay(false, twigVars.id);
                    }
                });
            }

            if (isDueWorkType(twigVars.type) || isEventType(twigVars.type)) {
                requestDataByDate(dayjsDate.format(adtp.dayjsFormats.databaseShort), 'calendar');
            }

            if (isDueWorkType(twigVars.type)) {
                var mo = instance.currentMonth + 1,
                    yr = instance.currentYear;

                requestHeat(mo, yr, function () {
                    applyHeatEverywhere(instance);
                });
            }


            // The size of something could have changed: reflow
            resizeCalendars();
        },
        onChange: function (selectedDates, dateStr, instance) {

            // Put the display date onto the calendar div so we can capture it when the modal is closed
            if (dateStr.length > 0) {
                var dateSplit = dateStr.split(' ');
                $('#pickerCalendar' + twigVars.id)
                    .attr('data-date', dateSplit[0])
                    .attr('data-time', dateSplit[1]);

                // Check if selected date has changed and don't send an ajax on time change
                var selectedObj = new Date(selectedDates);
                newDate = selectedObj.getFullYear() + '-' + (selectedObj.getMonth() + 1) + '-' + selectedObj.getDate();
                if (newDate !== latestDate) {
                    requestDataByDate(dateSplit[0], 'calendar');
                }
                latestDate = newDate;
            }

            // Unhide the event calendar (in the case it was hidden via expanding due work)
            $('#calendarResponse' + twigVars.id + '-events').removeClass('hide');
            resizeCalendars();
        },
        onMonthChange: function (selectedDates, dateStr, instance) {
            if (isDueWorkType(twigVars.type)) {
                var mo = instance.currentMonth + 1,
                    yr = instance.currentYear;-

                requestHeat(mo, yr, function () {
                    applyHeatEverywhere(instance);
                });
            }
            resizeCalendars();
        },
        onYearChange: function (selectedDates, dateStr, instance) {
            if (isDueWorkType(twigVars.type)) {
                var mo = instance.currentMonth + 1,
                    yr = instance.currentYear;

                requestHeat(mo, yr, function () {
                    applyHeatEverywhere(instance);
                });
            }
            resizeCalendars();
        },
        onValueUpdate: function (selectedDates, dateStr, instance) {
            if (isDueWorkType(twigVars.type)) {
                var mo = instance.currentMonth + 1,
                    yr = instance.currentYear;

                requestHeat(mo, yr, function () {
                    applyHeatEverywhere(instance);
                });
            }
            resizeCalendars();
        }

    };

    // only apply this if actually specified
    if (pickerOptions.enable) {
        var range = pickerOptions.enable.split(',');

        flatPickrConf.enable = [{
            from: range[0],
            to: range[1]
        }];
    }

    window.pickerInstance[twigVars.id] = flatpickr("#pickerCalendar" + twigVars.id, flatPickrConf);

    /**
     * Show the all day checkbox
     */
    function renderAllDaySwitch() {

        var $timeDiv = $('#pickerCalendar' + twigVars.id).closest('#adtp-modal-' + twigVars.id).find('.flatpickr-time');
        var html = '';
        var checked = '';

        /* Find out what the current all-day setting is and apply that
         */
        if ($('#' + pickerOptions.allDayInput).val() == 1) {
            checked = 'checked';
        }

        // Build an All Day switch if it's been set in the options
        if (pickerOptions.allDaySwitch === true) {
            html += '<div class="time-switch" data-adtp-calendar-allday><input id="hideTime' + twigVars.id + '" type="checkbox" value="1" ' + checked + '>' +
                '<label for="hideTime' + twigVars.id + '" class="checkbox-label">All Day</label></div>' +
                '</div>';
        }

        $timeDiv.after(html);
    }

    /**
     * Is the input value all day, or not
     */
    function isInputAllDay() {

        var allDay;

        // Hide time picker if allday hidden field is set to 1
        if (pickerOptions.allDayInput !== false && $('#' + pickerOptions.allDayInput).val() == 1) {
            allDay = true;
        }
        else if (pickerOptions.allDayInput !== false && $('#' + pickerOptions.allDayInput).val() == 0) {
            allDay = false;
        }
        else if (hasTime(dayjs($picker.val(), dateDisplayFormats.dayjsDatetime)) === false && pickerOptions.allDaySwitch) {
            allDay = true;
        }
        else {
            allDay = false;
        }

        return allDay;
    }

    /**
     * Build cycles dynamically for all campuses and selected term
     */
    function buildCycles() {

        var daysInCycle = timetable.campus.find(':selected').attr('data-days'),
            termDuration = timetable.term.find(':selected').attr('data-duration'),
            selectedTerm = timetable.term.find(':selected').val(),
            termStart = timetable.term.find(':selected').attr('data-start-date'),
            termEnd = timetable.term.find(':selected').attr('data-end-date');

        // Don't try to divide by zero
        if ((daysInCycle !== undefined && daysInCycle > 0) && (termDuration !== undefined && termDuration > 0)) {

            var optionsHtml = '';
            var activeDate = dayjs(getTimetablePickerValue(), adtp.dayjsFormats.databaseShort);

            $.each(twigVars.timetableVars, function (campus, terms) {
                $.each(terms.cycles[selectedTerm], function (cycle, value) {

                    var valueKeys = Object.keys(value);
                    var startDate = dayjs(value[valueKeys[0]]);
                    var endDate = dayjs(value[valueKeys[valueKeys.length - 1]]);
                    var hideClass = 'hide';

                    if (cycle == 1 && !activeDate.isBetween(termStart, termEnd, 'day', '[]')) {
                        // if this date is outside the bounds of the term, display the first week
                        hideClass = '';

                    } else if (activeDate.isBetween(startDate, endDate, 'day', '[]')) {
                        // show this week if the selected date is in this week
                        hideClass = '';
                    }

                    optionsHtml += '<div class="adtp-tt-week ' + hideClass + '" data-adtp-tt-cycle="' + cycle + '" data-cycle-start="' + startDate.format(adtp.dayjsFormats.databaseShort) + '" data-cycle-end="' + endDate.format(adtp.dayjsFormats.databaseShort) + '" data-parent-campus="' + campus + '" data-parent-term="' + selectedTerm + '">';
                    optionsHtml += '<div class="adtp-tt-week-number"><span class="adtp-tt-week-bold">Cycle</span> ' + cycle + '</div>';
                    optionsHtml += '<div class="adtp-tt-week-date">' + startDate.format('MMM D') + ' - ' + endDate.format('MMM D') + '</div>';
                    optionsHtml += '</div>';

                });
            });

            $byTimetable.find('[data-tt-weeks]').html(optionsHtml);

            /**
             * Reveal one cycle of all just been built
             */
            resetCycles();

            // For duework requests, trigger a fetch for any heat data that might be absent
            if (isDueWorkType(twigVars.type)) {

                var monthsToFetch = interpolateMonths(dayjs(termStart), dayjs(termEnd));

                for (var year in monthsToFetch) {
                    for (var monthIndex in monthsToFetch[year]) {
                        requestHeat(monthsToFetch[year][monthIndex], year, applyHeatToTimetable);
                    }
                }

            }

            revealTimetable();

        }
        else {
            noTimetableData();
        }

        // The size of something could have changed: reflow
        resizeCalendars();
    }

    /**
     * Update selected term
     */
    function resetTerms() {

        var activeDate = dayjs(getTimetablePickerValue(), adtp.dayjsFormats.databaseShort);
        // Hide all terms
        timetable.term.val([]);

        timetable.term.find('option').each(function () {
            var termStart = dayjs($(this).attr('data-start-date'), adtp.dayjsFormats.databaseShort),
                termEnd = dayjs($(this).attr('data-end-date'), adtp.dayjsFormats.databaseShort);

            if (activeDate.isBetween(termStart, termEnd, 'day', '[]')) {
                $(this).prop('selected', true);
            }
        });

        /**
         * Build cycles for the active term
         */
        buildCycles();

    }

    /**
     * Reveal cycle
     */
    function resetCycles() {

        var activeDate = dayjs(getTimetablePickerValue(), adtp.dayjsFormats.databaseShort),
            $selectedCampus = timetable.campus.find(':selected'),
            $selectedTerm = timetable.term.find(':selected'),
            termStart = dayjs($selectedTerm.attr('data-start-date'), adtp.dayjsFormats.databaseShort),
            termEnd = dayjs($selectedTerm.attr('data-end-date'), adtp.dayjsFormats.databaseShort);

        // Hide all cycles
        $byTimetable.find('[data-adtp-tt-cycle]').addClass('hide');

        // If the input date falls into the term, check which cycle to select. Otherwise go with the first one.
        if (activeDate.isBetween(termStart, termEnd, 'day', '[]')) {
            $byTimetable.find('[data-parent-campus="campus_' + $selectedCampus.val() + '"]').each(function () {
                $(this).addClass('hide');
                var cycleStart = dayjs($(this).attr('data-cycle-start'), adtp.dayjsFormats.databaseShort),
                    cycleEnd = dayjs($(this).attr('data-cycle-end'), adtp.dayjsFormats.databaseShort);

                if (activeDate.isBetween(cycleStart, cycleEnd, 'day', '[]')) {
                    $(this).removeClass('hide');
                }
            });
        }
        else {
            $('[data-parent-campus="campus_' + $selectedCampus.val() + '"][data-adtp-tt-cycle= 1]').removeClass('hide');
        }

        /**
         * Populate dates for the active cycle
         */
        addDatesToDays();

    }

    /**
     * Hide/display campus related input
     */
    function revealByCampus() {

        var selectedCampus = timetable.campus.find(':selected').val();

        // Show the correct days for the selected campus, hide others
        $byTimetable.find('[data-tt-day]').each(function () {
            if ($(this).attr('data-campus') == selectedCampus) {
                $(this).removeClass('hide');
            } else {
                $(this).addClass('hide');
            }
        });

        // Show the correct period dropdown for the selected campus, hide others
        $byTimetable.find('[data-adtp-tt-period]').each(function () {
            if ($(this).attr('data-campus') == selectedCampus) {
                $(this).removeClass('hide');
            } else {
                $(this).addClass('hide');
            }
        });

        // Update time radios to correct times
        updateTimeControls($byTimetable.find('[data-adtp-tt-period][data-campus=' + selectedCampus + ']'));

        // Reset the selected radio back to the first one
        $byTimetable.find('[data-adtp-tt-period-start]').prop('checked', true);

    }

    /**
     * Show the timetable picker
     */
    function revealTimetable() {

        // Hide any error
        $byTimetable.find('[data-adtp-tt-error]').addClass('hide');

        // Show the timetable picker
        $byTimetable.find('[data-adtp-tt-picker]').removeClass('hide');

        if (pickerOptions.enableTime) {
            $('[data-adtp-tt-time-controls]').removeClass('hide');
        } else {
            $('[data-adtp-tt-time-controls]').addClass('hide');
        }

        // Show the update button
        timetable.submit.removeClass('hide');

    }


    /**
     * Show an error for no timetable data found
     */
    function noTimetableData() {

        // Hide the timetable picker
        $byTimetable.find('[data-adtp-tt-picker]').addClass('hide');

        // Hide the update button
        timetable.submit.addClass('hide');

        // Show the error
        $byTimetable.find('[data-adtp-tt-error]').removeClass('hide');
    }

    /**
     * Reset the timetable day selectors
     * Only show selected day as active
     *
     * @param date {string}
     */
    function resetDays() {

        // Remove selected on all days for a blank state
        $byTimetable.find('[data-tt-day].selected').removeClass('selected');

        var activeDate = dayjs(getTimetablePickerValue(), adtp.dayjsFormats.databaseShort);
        $byTimetable.find('[data-date=' + activeDate.format(adtp.dayjsFormats.databaseShort) + ']').addClass('selected');
        $byTimetable.find('[data-tt-selected-date]').text(activeDate.format(adtp.dayjsFormats.nice));

    }

    /**
     * If the field has a time of 12am or 11:59pm, we consider this to not have time if all day is enabled
     * Check if a dayjs date object has time by this definition
     *
     * @param dayjsDate {object}
     * @returns {boolean}
     */
    function hasTime(dayjsDate) {
        return ((pickerOptions.endDay === false && dayjsDate.format('HH:mm') !== '00:00') || (pickerOptions.endDay === true && dayjsDate.format('HH:mm') !== '23:59'));
    }

    /**
     * Get event data based on date
     *
     * @param date (database short format)
     * @param target (calendar | timetable)
     */
    function requestDataByDate(date, target) {
        var urlDate = encodeURIComponent(date);

        setRequestDate(date, target);

        if (isDueWorkType(twigVars.type)) { // Fetch student assessments

            loadingResponse(target);

            var folder = $('*[name = "folderId"]').val();

            $.get((twigVars.routeLookup.getDueByDate).replace(/{folderId}/g, folder).replace(/{date}/g, urlDate))

                .done(function (urlDate) {
                    appendResponse(urlDate, 'duework', target);
                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });

            // Pull in events as well
            $.get((twigVars.routeLookup.getByDate).replace(/_date_/g, urlDate))
                .done(function (urlDate) {
                    appendResponse(urlDate, 'events', target);
                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });
        }
        else if (isEventType(twigVars.type)) { // Fetch events

            loadingResponse(target);

            $.get((twigVars.routeLookup.getByDate).replace(/_date_/g, urlDate))
                .done(function (urlDate) {
                    appendResponse(urlDate, 'events', target);
                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });
        }
    }

    /**
     * Show loader in response div
     *
     * @param target
     */
    function loadingResponse(target) {

        $('#' + target + 'Response' + twigVars.id + '-duework').html('');
        $('#' + target + 'Response' + twigVars.id + '-events').html('');
        $('#' + target + 'Response' + twigVars.id + ' .loader').removeClass('hide');

    }


    /**
     * Prepare and append response HTML
     *
     * @param data
     * @param template
     * @param target
     */
    function appendResponse(data, template, target) {

        var $pickerResponse = $('#' + target + 'Response' + twigVars.id); // The event container
        var $pickerResponseDueWork = $('#' + target + 'Response' + twigVars.id + '-duework');
        var $pickerResponseEvents = $('#' + target + 'Response' + twigVars.id + '-events');
        var html = '';

        // Clear out any old response display
        if (template == 'duework') {
            $pickerResponseDueWork.html('');
        } else {
            $pickerResponseEvents.html('');
        }

        // Hide the loader
        $('#' + target + 'Response' + twigVars.id + ' .loader').addClass('hide');

        if (template == 'duework') {

            html = '<h2 class="mini">' + twigVars.locale.dueWorkProper + ' (<span data-due-work-title>' + dictMostLoaded + '</span>)</h2>';

            if (data.students === undefined) {
                html += 'Error loading data';
            }
            else if (data.students.length == 0) {
                html += '<p>' + dictNoWorkToday + '</p>';
                $pickerResponseDueWork.addClass('meta');
            }
            else {
                $pickerResponse.removeClass('meta');

                // Show most loaded student
                html += '<div class="top-due-work" data-adtp-top-due>';
                html += '<div class="compact card small-12">';
                html += '<img src="/portrait.php?id=' + data.students[0].id + '&amp;size=square80">';
                html += '<h3>' + data.students[0].fullName + '</h3>';
                html += '<p class="meta">' + data.students[0].assessmentsDue + ' due' + '</p>';
                html += '</div>';
                html += '<div class="icon-next" data-show-full-due="due-work-list-' + target + '-' + dayjs(data.date).format('YYYY-m-d') + '" title="Show all students and work due"></div>';
                html += '</div>';

                // Create a full list of students, hidden by default
                html += '<div id="due-work-list-' + target + '-' + dayjs(data.date).format('YYYY-m-d') + '" class="row hide">';
                html += '<div class="small-12 medium-12 columns">';
                html += '<ul class="small-12 information-list expandable-card">';

                html += '<li><a data-hide-full-due="due-work-list-' + target + '-' + dayjs(data.date).format('YYYY-m-d') + '"><span class="compact card small-12 icon-previous">Back</span></a></li>';

                for (var i = 0; i < data.students.length; i++) {

                    html += '<li>';
                    html += '<div>';
                    html += '<div class="expander" data-collapser="collap-' + i + '"></div>';
                    html += '<div class="card compact small-12">';
                    html += '<img src="/portrait.php?id=' + data.students[i].id + '&amp;size=square80">';
                    html += '<h3>' + data.students[i].fullName + '</h3>';
                    html += '<p class="meta">' + data.students[i].assessmentsDue + ' due' + '</p>';
                    html += '</div>';
                    html += '</div>';

                    html += '<div class="small-12 column hide" data-collapse-state="collapsed" data-collapsable="collap-' + i + '">';
                    html += '<table class="assessment-list">';
                    for (var k = 0; k < data.students[i].assessmentList.length; k++) {
                        html += '<tr><td>';
                        if (data.students[i].assessmentList[k]['duetime'] !== '11:59pm') {
                            html += data.students[i].assessmentList[k]['duetime'];
                        }
                        html += '</td><td>' + data.students[i].assessmentList[k]['context'] + ': ' + data.students[i].assessmentList[k]['title'] + '</td>' +
                            '</tr>';
                    }
                    html += '</table>';

                    html += '</div>';
                    html += '</li>';
                }

                html += '</ul>';
                html += '</div>';
                html += '</div>';

            }

            $pickerResponseDueWork.append(html);

            $pickerResponse.find('[data-show-full-due]').click(function () {
                var listDiv = $(this).data('show-full-due');
                $(this).parents('[data-adtp-top-due]').addClass('hide');
                $('#' + listDiv).removeClass('hide');
                $pickerResponseEvents.addClass('hide');
                $('[data-due-work-title]').text(dictTop5Loaded);
            });

            $pickerResponse.find('[data-hide-full-due]').click(function () {
                var listDiv = $(this).data('hide-full-due');
                $pickerResponseDueWork.find('[data-adtp-top-due]').removeClass('hide');
                $('#' + listDiv).addClass('hide');
                $pickerResponseEvents.removeClass('hide');
                $('[data-due-work-title]').text(dictMostLoaded);
            });
        }
        else if (template == 'events') {

            var events = [];

            html = '<h2 class="mini" data-adtp-calendar-title>Your Calendar</h2>';

            if (data.appointments !== undefined && data.appointments.length !== 0) {
                for (var i = 0; i < data.appointments.length; i++) {
                    events.push({
                        id: i,
                        start: data.appointments[i].start,
                        end: data.appointments[i].end,
                        title: data.appointments[i].title,
                        allDay: data.appointments[i].allDay
                    });
                }
            }

            html += '<div data-fullcalendar></div>';
            $pickerResponseEvents.append(html);
            var $calendarDiv = $pickerResponseEvents.find('div[data-fullcalendar]');

            // Kill off the fullcalendar for this target, and rebuild it
            if (fullCalendars[target]) {
                fullCalendars[target].destroy();
            }
            var calendar = new FullCalendar.Calendar(
                $calendarDiv[0],
                {
                    plugins: ['list'],
                    header: false,
                    eventLimit: true,
                    scrollTime: dateDisplayFormats.calendarStartTime,
                    views: {
                        day: {
                            eventLimit: 4
                        }
                    },
                    defaultView: 'listDay',
                    events: events,
                    eventColor: '#def',
                    defaultDate: data.date,
                }
            );
            fullCalendars[target] = calendar;
            calendar.render();
        }

        $modal.find('[data-collapser]').unbind().collapsable();
    }


    /**
     * Update the size of every calendar to match its container.
     *
     * (Each calendar is configured to have an automatic height)
     */
    function resizeCalendars() {
        Object.keys(fullCalendars).forEach(function (key) {
            fullCalendars[key].updateSize();
        })
    }


    /**
     * Week/cycle previous & next functionality
     *
     * @param $button
     * @param prev
     */
    function changeCycle($button, prev) {

        var $currentWeek = $button.closest('[data-tt-week-selector]').find('[data-adtp-tt-cycle]:visible'),
            // Scroll only through the cycles that belong to the same campus
            currentCampus = $currentWeek.attr('data-parent-campus');

        if ((prev && $currentWeek.prev().length && $currentWeek.prev().attr('data-parent-campus') === currentCampus)
            || (!prev && $currentWeek.next().length && $currentWeek.next().attr('data-parent-campus') === currentCampus)) {

            // Hide the current cycle title
            $currentWeek.addClass('hide');

            // Show the next/prev title
            var $newCycle;
            if (prev) {
                $newCycle = $currentWeek.prev();
            } else {
                $newCycle = $currentWeek.next();
            }
            $newCycle.removeClass('hide');

            /**
             * Populate dates for the active cycle
             */
            addDatesToDays();
            applyHeatToTimetable();

        }
    }

    /**
     * Add date data attributes to day selectors on timetable picker
     */
    function addDatesToDays() {

        iterateVisibleTimetableDays(
            function (dayMoment) {
                $(this).attr('data-date', dayMoment.format(adtp.dayjsFormats.databaseShort)).removeClass('disabled');
            },
            function () {
                $(this).removeAttr('data-date').addClass('disabled');
            }
        );

        /**
         * Check any of the days need to be selected
         */
        resetDays();
    }

    /**
     * Iterate the timetable days and apply the given callbacks to them accordingly
     *
     * @param {Function} ifDate
     * @param {Function} ifNotDate
     */
    function iterateVisibleTimetableDays(ifDate, ifNotDate) {

        // Change the date data attributes on each day
        var cycleNumber = $byTimetable.find('[data-adtp-tt-cycle]').not('.hide').attr('data-adtp-tt-cycle') || 1;
        var selectedCampus = 'campus_' + timetable.campus.find(':selected').val();
        var selectedTerm = timetable.term.find(':selected').val();

        $byTimetable.find('[data-tt-days] > div').each(function () {

            var dayNumber = $(this).attr('data-tt-day');

            // Verify every key is there
            if (selectedCampus in twigVars.timetableVars
                && 'cycles' in twigVars.timetableVars[selectedCampus]
                && selectedTerm in twigVars.timetableVars[selectedCampus].cycles
                && cycleNumber in twigVars.timetableVars[selectedCampus].cycles[selectedTerm]
                && dayNumber in twigVars.timetableVars[selectedCampus].cycles[selectedTerm][cycleNumber]
            ) {
                var dayMoment = dayjs(twigVars.timetableVars[selectedCampus].cycles[selectedTerm][cycleNumber][dayNumber]);

                if (ifDate) {
                    ifDate.call(this, dayMoment);
                }

            } else if (ifNotDate) {
                ifNotDate.call(this);
            }
        });
    }

    /**
     * If the input supports All Day, add this option to the period selector
    */
    function renderTimetableAllDay() {

            timetable.periods.each(function () {
                if (pickerOptions.allDaySwitch === true) {
                    $(this).prepend('<option value="allday" selected>All Day</option>');
                }
                updateTimeControls($(this));
            });

    }

    /**
     * Select or deselect timetable allDay value
     */
    function setTimetableAllDay() {

        isInputAllDay() ? selectTimetableAllDay() : deselectTimetableAllDay();

    }

    /**
     * Select 'allday' in the period dropdown for all campuses
     */
    function selectTimetableAllDay() {

        $byTimetable.find('[data-adtp-tt-period]').val('allday').trigger('change');

    }

    /**
     * Deselect 'allday' in the period dropdown for all campuses
     */
    function deselectTimetableAllDay () {

        var $periodList = $byTimetable.find('[data-adtp-tt-period]'),
            activePeriods = $periodList.find('option:selected');

        // Selected periods for all the campuses, no matter hidden or not
        if (activePeriods.length > 0) {
            activePeriods.each(function () {
                // If the value is allday, deselect it and select the period that's coming next
                if ($(this).val() == 'allday') {
                    $(this).prop('selected', false);
                    $(this).next().prop('selected', true);
                    $(this).parent().trigger('change');
                }
            });
        }
        // If nothing is selected, select the first option
        // TODO: this behaviour will change when tt gets synced by time
        else {
            $periodList.val($periodList.find('option:first').val());
        }
    }

    /**
     * Update the time radios based on what period is selected
     *
     * @param periodSelect
     */
    function updateTimeControls($periodSelect) {

        // If all day - hide radios
        if ($periodSelect.val() == 'allday') {
            $periodSelect.parents('div').find('[data-adtp-tt-time]').addClass('hide');
        } else {

            // Show time radios
            $periodSelect.parents('div').find('[data-adtp-tt-time]').removeClass('hide');

            // Fill in period start and end times based on what period was selected
            var periodStart = $periodSelect.find('option:selected').data('start-time'),
                periodEnd = $periodSelect.find('option:selected').data('end-time');

            $byTimetable.find('[data-adtp-tt-time] [data-start-time]').text(periodStart);
            $byTimetable.find('[data-adtp-tt-time] [data-end-time]').text(periodEnd);

        }
    }

    /**
     * Whichever dates are visible in the timetable, apply heat to them
     */
    function applyHeatToTimetable() {
        iterateVisibleTimetableDays(function (dayMoment) {
            var yr = dayMoment.year();
            var mo = dayMoment.month() + 1;
            var day = dayMoment.date();

            applyHeatToDay($(this), yr, mo, day);
        });
    }

    /**
     * Adds heatmap to a month view of flatpickr
     *
     * @param {flatpickr} instance
     */
    function applyHeatToCalendar(instance) {
        // loop through all days and set a heatmap height
        $(instance.days.childNodes).each(function () {
            var dt = $(this)[0].dateObj;

            var day = dt.getDate(),
                mo = dt.getMonth() + 1,
                yr = dt.getUTCFullYear();

            applyHeatToDay($(this), yr, mo, day);
        });
    }

    /**
     * Ergh... the fact that this function even exists.....
     *
     * @param {flatpickr} instance
     */
    function applyHeatEverywhere(instance) {
        applyHeatToCalendar(instance);
        applyHeatToTimetable();
    }

    /**
     * What it says on the tin.
     *
     * @param {jQuery}  element
     * @param {Integer} yr
     * @param {Integer} mo
     * @param {Integer} day
     */
    function applyHeatToDay(element, yr, mo, day) {
        let date          = String(yr).padStart(4, '0') + '-' + String(mo).padStart(2, '0') + '-' + String(day).padStart(2, '0'),
            lastMonth     = (mo === 1) ? 12 : mo - 1,
            lastMonthYear = (mo === 1) ? yr - 1 : yr,
            nextMonth     = (mo === 12) ? 1 : mo + 1,
            nextMonthYear = (mo === 12) ? yr + 1 : yr;

        let cache = null;
        if (yr in heatCache && mo in heatCache[yr] && date in heatCache[yr][mo]) {
            cache = heatCache[yr][mo][date];
        } else if (lastMonthYear in heatCache && lastMonth in heatCache[lastMonthYear] && date in heatCache[lastMonthYear][lastMonth]) {
            cache = heatCache[lastMonthYear][lastMonth][date];
        } else if (nextMonthYear in heatCache && nextMonth in heatCache[nextMonthYear] && date in heatCache[nextMonthYear][nextMonth]) {
            cache = heatCache[nextMonthYear][nextMonth][date];
        }
        if (cache) {
            element.attr('data-heat', parseInt(cache / 20));
        } else {
            element.attr('data-heat', '');
        }
    }

    /**
     * Places a request for heat from the server, then caches the response. On repeat requests, cached values
     * will be returned immediately.
     *
     * @param {Integer} mo
     * @param {Integer} yr
     * @param {Callable} onSuccess
     */
    function requestHeat(mo, yr, onSuccess) {
        var folderId = $('*[name = "folderId"]').val();

        // cast to ints
        yr = parseInt(yr);
        mo = parseInt(mo);

        fetchHeat(yr, mo, folderId, onSuccess);
    }

    /**
     * Retrieve heat data from they server, then apply it according to the onSuccess callback
     *
     * @param {Integer} yr
     * @param {Integer} mo
     * @param {Integer} folderId
     * @param {Callable} onSuccess
     */
    function fetchHeat(yr, mo, folderId, onSuccess) {
        if ('undefined' === typeof heatCache[yr]) {
            heatCache[yr] = {};
        }

        if ('undefined' === typeof heatCache[yr][mo]) {
            // you're the first here - do the work of warming the cache
            var promise;

            heatCache[yr][mo] = promise = $.get((twigVars.routeLookup.getHeat
                .replace(/{folderId}/g, folderId))
                .replace(/{year}/g, yr)
                .replace(/{month}/g, mo));

            promise.fail(function () {
                console.error(arguments);

            }).then(function (data) {

                // prepare for promise fulfilment
                heatCache[yr][mo] = {};

                for (var day in data) {
                    heatCache[yr][mo][day] = data[day];
                }
            });

            // do this async the first time (cold cache)
            promise.then(onSuccess);

        } else if ('then' in heatCache[yr][mo]) {
            // the cache is still cold, but someone else is handling it: append our desired callback
            heatCache[yr][mo].then(onSuccess);
        } else {
            // apply callback synchronously if the cache is hot
            onSuccess();
        }

    }

    /**
     * Append a tool tip regarding heatmap
     */
    function renderHeatMapHelp() {

        // TODO allow this to be injected by construction
        var tt = 'The "heat" of a day represents the largest number of assessments any of your students has due that day, capped at 5.';

        var $monthDiv = $modal.find('.flatpickr-current-month');
        $monthDiv.append($('<a class="icon-help" />').attr('title', tt));
    }

    /**
     * Return a collection of years & months for given start and end dates (usually term start and end)
     *
     * @param startMoment
     * @param endMoment
     * @returns {{}}
     */
    function interpolateMonths(startMoment, endMoment) {
        var i,
            monthsToFetch = {},
            startYear = startMoment.year(),
            startMonth = startMoment.month() + 1,
            endYear = endMoment.year(),
            endMonth = endMoment.month() + 1
            ;

        // these are likely the same, but not a guarantee
        monthsToFetch[startYear] = [];
        monthsToFetch[endYear] = [];

        if (endMonth < startMonth) {
            // compensate for year-wrapping
            for (i = startMonth; i <= 12; ++i) {
                monthsToFetch[startYear].push(i);
            }
            for (i = 1; i <= endMonth; ++i) {
                monthsToFetch[endYear].push(i);
            }

        } else {
            // the simple case
            for (i = startMonth; i <= endMonth; ++i) {
                monthsToFetch[startYear].push(i);
            }
        }

        return monthsToFetch;
    }

    /**
     * Set value to the timetable picker
     *
     * @param date (database short format)
     */
    function setTimetablePickerValue(date) {

        var $button = $byTimetable.find('[data-timetable-value]');
        $button.attr('data-timetable-value', date);

    }

    /**
     * Get timetable picker value
     */
    function getTimetablePickerValue() {

        var $button = $byTimetable.find('[data-timetable-value]');
        return $button.attr('data-timetable-value');

    }

    /**
     * Set data request date to the response container
     *
     * @param {string} date (database short format)
     * @param {string} target (calendar | timetable)
     */
    function setRequestDate(date, target) {

        var $response = $('#' + target + 'Response' + twigVars.id);
        $response.attr('data-adtp-response-date', date);

    }

    /**
     * Get date for the latest data request
     *
     * @param {string} target (calendar | timetable)
     */
    function getRequestDate(target) {

        var $response = $('#' + target + 'Response' + twigVars.id);
        return $response.attr('data-adtp-response-date');

    }

    /**
     * Visualise the timetable for a given date
     */
    function resetTimetablePicker() {

        if (isDueWorkType(twigVars.type) || isEventType(twigVars.type)) {
            updateTimetableResponse();
        }
        resetTerms();

    }

    /**
     * Reset Calendar picker for the currently active date
     */
    function resetCalendar() {

        if (isInputValueValid()) {

            // Create a dayjs object from the input value
            var activeDate = dayjs(getInputValue(), [dateDisplayFormats.dayjsDatetime, dateDisplayFormats.dayjs]);

            // Set date on flatpickr to the input date
            window.pickerInstance[twigVars.id].setDate(activeDate.toISOString());

            // If this datepicker shows due work or events - update the response
            if (isDueWorkType(twigVars.type) || isEventType(twigVars.type)) {
                updateCalendarResponse();
            }
        }
    }

    /**
     * Update requested data for the currently active date (in calendar picker)
     */
    function updateCalendarResponse() {

        var activeDate = dayjs(getInputValue(), dateDisplayFormats.dayjs),
            requestDate = dayjs(getRequestDate('calendar'), adtp.dayjsFormats.databaseShort);
        if (activeDate.isValid() && requestDate.diff(activeDate)) {
            requestDataByDate(activeDate.format(adtp.dayjsFormats.databaseShort), 'calendar');
        }

    }

    /**
     * Update requested data for the currently active date (in timetable picker)
     */
    function updateTimetableResponse() {

        var activeDate = dayjs(getInputValue(), dateDisplayFormats.dayjs),
            timetableDate = dayjs(getTimetablePickerValue(), adtp.dayjsFormats.databaseShort),
            requestDate = dayjs(getRequestDate('timetable'), adtp.dayjsFormats.databaseShort);

        // The picker value has been changed externally
        if (activeDate.isValid() && timetableDate.diff(activeDate)) {
            // Set new value
            setTimetablePickerValue(activeDate.format(adtp.dayjsFormats.databaseShort));
            // And request data for the new date
            requestDataByDate(activeDate.format(adtp.dayjsFormats.databaseShort), 'timetable');
        }
        // Some data has already been requested, check if the data request needs to be updated
        else if (requestDate.isValid() && requestDate.diff(timetableDate)) {
            requestDataByDate(timetableDate.format(adtp.dayjsFormats.databaseShort), 'timetable');
        }
        else {
            // Request data for the current timetable value
            requestDataByDate(timetableDate.format(adtp.dayjsFormats.databaseShort), 'timetable');
        }

    }

    /**
     * Check if this is a due work datepicker
     * @param {string} type - The datepicker type (usually 'event' or 'duework')
     */
    function isDueWorkType(type) {
        return type === 'duework';
    }

    /**
     * Check if this is an event datepicker
     * @param {string} type - The datepicker type (usually 'event' or 'duework')
     */
    function isEventType(type) {
        return type === 'event';
    }

    /**
     * Get input field value
     */
    function getInputValue() {

        return $picker.val();

    }

    /**
     * Check if the current value of the input field is valid
     */
    function isInputValueValid() {

        return dayjs(getInputValue(), dateDisplayFormats.dayjs).isValid();

    }
};
