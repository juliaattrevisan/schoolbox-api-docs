/*
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

// Picker settings

// Build datepicker options here... again...
$.fn.alaressDateTimeModal = function(twigVars) {

    var $picker = $('#' + twigVars.element);
    var pickerOptions = {
        // Flatpickr related
        enableTime: getValue($picker, 'data-datepicker-time'),

        // All the rest
        altFormat: getValue($picker, 'data-datepicker-format'), // false|date|datetime
        allDaySwitch: getValue($picker, 'data-datepicker-alldayswitch'), // Show All Day switch
        allDayInput: getValue($picker, 'data-datepicker-alldayinput'),
        type: getValue($picker, 'data-datepicker-call'), // Ajax call type: events, duework or nothing
        endDay: getValue($picker, 'data-datepicker-endofday'), // Should allday change time to 23:59:59
        endPeriod: getValue($picker, 'data-datepicker-endofperiod') // Should allday change time to 23:59:59
    };
    var dictMostLoaded  = 'Heaviest Workload';
    var dictTop5Loaded  = '5 Heaviest Workloads';
    var dictNoWorkToday = 'Your students have nothing due this day';

    var heatCache = {};

    // Timetable picker
    if (twigVars.timetable) {

        // Timetable elements
        var $byTimetable = $('#byTimetable-' + twigVars.id);
        var timetable = {
            campus: $byTimetable.find('[data-adtp-tt-campus]'),
            term: $byTimetable.find('[data-adtp-tt-term]'),
            submit: $byTimetable.find('[data-adtp-tt-submit]')
        };

        buildCycles();
        revealTimetable();
        resetTimetable();

        // On first load request data by date for the timetable
        requestDataByDate(encodeURIComponent(twigVars.date.getFullYear() + '-' + (twigVars.date.getMonth() + 1) + '-' + twigVars.date.getDate()), 'timetable');

        // Update the selected date
        var selectedDate = moment(twigVars.date).format('dddd, MMM D, Y');
        $byTimetable.find('[data-tt-selected-date]').text(selectedDate);

        // Listen for changes to campus
        timetable.campus.change(function () {
            buildCycles();
            resetTimetable();
            revealTimetable();
        });

        // Listen for changes to term
        timetable.term.change(function() {
            buildCycles();
            resetTimetable();
        });

        // Attach week cycle functionality to prev & next buttons
        $byTimetable.find('[data-tt-next]').click(function() {
            changeCycle($(this), false);
        });
        $byTimetable.find('[data-tt-prev]').click(function() {
            changeCycle($(this), true);
        });

        // Selecting a day
        $byTimetable.on('click', '[data-tt-day]:not(.disabled)', function() {

            var selectedDate = moment($(this).attr('data-date')).format('dddd, MMM D, Y');

            // Add selected class & remove others with the class
            $(this).parents('.day-picker').find('[data-tt-day]').removeClass('selected');
            $(this).addClass('selected');

            // Update the selected date
            $(this).closest('[data-adtp]').find('[data-tt-selected-date]').text(selectedDate);

            // Load events
            requestDataByDay($(this));

        });

        // Catch period select change
        $byTimetable.find('[data-tt-period]').change(function() {

            // If all day - hide radios
            if ($(this).val() == 'allday') {
                $(this).parents('div').find('[data-adtp-tt-time]').addClass('hide');
            } else {

                // Show time radios
                $(this).parents('div').find('[data-adtp-tt-time]').removeClass('hide');

                // Fill in period start and end times based on what period was selected
                var periodStart = $(this).find('option:selected').data('start-time'),
                    periodEnd = $(this).find('option:selected').data('end-time');

                $byTimetable.find('[data-adtp-tt-time] [data-start-time]').text(periodStart);
                $byTimetable.find('[data-adtp-tt-time] [data-end-time]').text(periodEnd);

            }
        });

    }


    // Setup calendar
    var newDate, latestDate;


    // Accommodating the "start of the week" SB config
    Flatpickr.localize(Flatpickr.l10ns.default);
    Flatpickr.l10ns.default.firstDayOfWeek = twigVars.weekStartDay;

    window.pickerInstance[twigVars.id] = flatpickr("#pickerCalendar" + twigVars.id, {
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

            // Hide time picker if allday hidden field is set to 1
            if (pickerOptions.allDayInput !== false && $('#' + pickerOptions.allDayInput).val() == 1) {
                checkAllDaySwitch(true, twigVars.id);
            }
            else if (pickerOptions.allDayInput !== false && $('#' + pickerOptions.allDayInput).val() == 0) {
                checkAllDaySwitch(false, twigVars.id);
            }
            else if (hasTime(instance.input.value) === false) {
                checkAllDaySwitch(true, twigVars.id);
            }
            else {
                checkAllDaySwitch(false, twigVars.id);
            }

            if (pickerOptions.allDaySwitch === true) {
                $('#hideTime' + twigVars.id).click(function () {
                    if ($('#hideTime' + twigVars.id).is(':checked')) {
                        checkAllDaySwitch(true, twigVars.id);
                    }
                    else {
                        checkAllDaySwitch(false, twigVars.id);
                    }
                });
            }

            // Fix Flatpickr display enableTime
            if (pickerOptions.enableTime) {
                $('#picker' + twigVars.id).find('.flatpickr-time').css('display', 'block');
            }

            requestDataByDate(encodeURIComponent(twigVars.date.getFullYear() + '-' + (twigVars.date.getMonth() + 1) + '-' + twigVars.date.getDate()), 'calendar');

            var mo = instance.currentMonth + 1,
                yr = instance.currentYear;

            requestHeat(mo, yr, function () {
                applyHeatEverywhere(instance);
            });
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
                    requestDataByDate(encodeURIComponent(dateSplit[0]), 'calendar');
                }
                latestDate = newDate;
            }
        },
        onMonthChange: function(selectedDates, dateStr, instance) {
            var mo = instance.currentMonth + 1,
                yr = instance.currentYear;

            requestHeat(mo, yr, function () {
                applyHeatEverywhere(instance);
            });
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            var mo = instance.currentMonth + 1,
                yr = instance.currentYear;

            requestHeat(mo, yr, function () {
                applyHeatEverywhere(instance);
            });
        },
        onValueUpdate: function(selectedDates, dateStr, instance) {
            var mo = instance.currentMonth + 1,
                yr = instance.currentYear;

            requestHeat(mo, yr, function () {
                applyHeatEverywhere(instance);
            });
        }

    });


    /**
     * Show the all day checkbox
     */
    function renderAllDaySwitch() {

        var $timeDiv = $('#pickerCalendar' + twigVars.id).closest('#picker' + twigVars.id).find('.flatpickr-time');
        var html = '';

        // Build an All Day switch if it's been set in the options
        if (pickerOptions.allDaySwitch === true) {
            html += '<div class="time-switch"><input id="hideTime' + twigVars.id + '" type="checkbox" value="1" checked>' +
                '<label for="hideTime' + twigVars.id + '" class="checkbox-label">All Day</label></div>' +
                '</div>';
        }

        $timeDiv.after(html);
    }


    /**
     * Build cycles dynamically
     */
    function buildCycles() {

        var selectedCampus = 'campus_' + timetable.campus.find(':selected').val(),
            daysInCycle = timetable.campus.find(':selected').attr('data-days'),
            termDuration = timetable.term.find(':selected').attr('data-duration'),
            selectedTerm = timetable.term.find(':selected').val(),
            termStart = timetable.term.find(':selected').attr('data-start-date'),
            termEnd = timetable.term.find(':selected').attr('data-end-date'),
            monthsToFetch = interpolateMonths(moment(termStart), moment(termEnd));

        // Don't try to divide by zero
        if ((daysInCycle !== undefined && daysInCycle > 0) && (termDuration !== undefined && termDuration > 0)) {

            var optionsHtml = '';

            $.each(twigVars.timetableVars[selectedCampus].cycles[selectedTerm], function(index, value) {

                var valueKeys = Object.keys(value);
                var startDate = moment(value[valueKeys[0]]);
                var endDate   = moment(value[valueKeys[valueKeys.length - 1]]);
                var hideClass = 'hide';

                if (index == 1 && !moment(twigVars.date).isBetween(termStart, termEnd, 'day', '[]')) {
                    // if this date is outside the bounds of the term, display the first week
                    hideClass = '';

                } else if (moment(twigVars.date).isBetween(startDate, endDate, 'day', '[]')) {
                    // show this week if the selected date is in this week
                    hideClass = '';
                }

                optionsHtml += '<div class="adtp-tt-week ' + hideClass + '" data-adtp-tt-cycle="' + index + '" data-cycle-start="' + startDate.format('YYYY-MM-DD') + '" data-cycle-end="' + endDate.format('YYYY-MM-DD') + '">';
                optionsHtml += '<div class="adtp-tt-week-number"><span class="adtp-tt-week-bold">Week</span> ' + index + '</div>';
                optionsHtml += '<div class="adtp-tt-week-date">' + startDate.format('MMM D') + ' - ' + endDate.format('MMM D') + '</div>';
                optionsHtml += '</div>';

            });

            $byTimetable.find('[data-tt-weeks]').html(optionsHtml);

            addDatesToDays();

            // trigger a fetch for any heat data that might be absent
            for (var year in monthsToFetch) {
                for (var monthIndex in monthsToFetch[year]) {
                    requestHeat(monthsToFetch[year][monthIndex], year, applyHeatToTimetable);
                }
            }

            revealTimetable();

        }
        else {
            noTimetableData();
        }
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
     */
    function resetTimetable() {

        var selectedDate = moment(timetable.submit.attr('data-timetable-value')).format('YYYY-MM-DD'); // The currently selected date

        // Remove selected on all days for a blank slate
        $byTimetable.find('[data-tt-day].selected').removeClass('selected');

        // Loop through cycle days and check if currently set date matches
        $byTimetable.find('[data-tt-day]').each(function() {
            if (selectedDate == $(this).attr('data-date')) {
                $(this).addClass('selected');
            }
        });
    }


    /**
     * Checks if time is 11:59pm
     *
     * @param value
     * @returns {boolean}
     */
    function hasTime(value) {
        var v = moment(value);
        if (pickerOptions.endDay === true && v.hours() == 23 && v.minutes() == 59 && (v.seconds() == 59 || v.second() == 0)) {
            return false;
        }
        if (v.hours() == 0 && v.minutes() == 0 && v.seconds() == 0) {
            return false;
        }
        return true;
    }


    /**
     * Get event data based on date
     *
     * @param date
     * @param target
     */
    function requestDataByDate(date, target) {

        if (twigVars.type == 'duework') { // Fetch student assessments

            loadingResponse(target);

            var folder = $('*[name = "folderId"]').val();

            $.get((twigVars.routeLookup.getDueByDate).replace(/{folderId}/g, folder).replace(/{date}/g, date))

                .done(function (data) {

                    appendResponse(data, 'duework', target);

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });

            // Pull in events as well
            $.get((twigVars.routeLookup.getByDate).replace(/_date_/g, date))
                .done(function (data) {

                    appendResponse(data, 'events', target);

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });
        }
        else if (twigVars.type == 'event') { // Fetch events

            loadingResponse(target);

            $.get((twigVars.routeLookup.getByDate).replace(/_date_/g, date))
                .done(function (data) {

                    appendResponse(data, 'events', target);

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });
        }
    }


    /**
     * Get event data based on timetable day
     *
     * @param element
     */
    function requestDataByDay(element) {

        var day = element.attr('data-tt-day'),
            date = element.attr('data-date');

        // Update the date data attr on the submit button
        timetable.submit.attr('data-timetable-value', date);

        if (twigVars.type == 'duework') { // Fetch student assessments

            var folder = $('*[name = "folderId"]').val();

            // Show loader
            loadingResponse('timetable');

            $.get((twigVars.routeLookup.getDueByDate).replace(/{date}/g, date).replace(/{folderId}/g, folder))
                .done(function (data) {
                    appendResponse(data, 'duework', 'timetable');
                })
                .fail(function () {
                    console.log('Failed to retrieve student work for specified day');
                });
        }
        else if (twigVars.type == 'event') { // Fetch events

            // Show loader
            loadingResponse('timetable');

            $.get((twigVars.routeLookup.getByDate).replace(/_date_/g, date))
                .done(function (data) {
                    appendResponse(data, 'events', 'timetable');
                })
                .fail(function () {
                    console.log('Failed to retrieve student work for specified day');
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
        var displayDate = moment(data.date).format('MMMM DD, YYYY'); // The selected date in a nice format
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

            html = '<h2 class="mini">Due Work (<span data-due-work-title>' + dictMostLoaded + '</span>)</h2>';

            if (data.students === undefined) {
                html = 'Error loading data';
            }
            else if (data.students.length == 0) {
                html += '<p class="no-margin">' + dictNoWorkToday + '</p>';
                $pickerResponseDueWork.addClass('meta');
            }
            else {
                $pickerResponse.removeClass('meta');

                // Show most loaded student
                html += '<div class="top-due-work">';
                html += '<div class="compact card small-12">';
                html += '<img src="/portrait.php?userId=' + data.students[0].externalId + '&amp;size=square80">';
                html += '<h3>' + data.students[0].firstname + ' ' + data.students[0].lastname + '</h3>';
                html += '<p class="meta">' + data.students[0].assessmentsDue + ' due' + '</p>';
                html += '</div>';
                html += '<div class="icon-next" data-show-full-due="due-work-list-' + moment(data.date).format('Y-m-d') + '" title="Show all students and work due"></div>';
                html += '</div>';

                // Create a full list of students, hidden by default
                html += '<div id="due-work-list-' + moment(data.date).format('Y-m-d') + '" class="row hide">';
                html += '<div class="small-12 medium-12 columns">';
                //html += '<h2>Work due on ' + displayDate + '</h2>';
                html += '<ul class="small-12 information-list expandable-card">';

                html += '<li><a data-hide-full-due="due-work-list-' + moment(data.date).format('Y-m-d') + '"><span class="compact card small-12 icon-previous">Back</span></a></li>';

                for (var i = 0; i < data.students.length; i++) {

                    html += '<li>';
                    html += '<div>';
                    html += '<div class="expander icon-down-arrow" data-collapser="collap-' + i + '"></div>';
                    html += '<div class="card compact small-12">';
                    html += '<img src="/portrait.php?userId=' + data.students[i].externalId + '&amp;size=square80">';
                    html += '<h3>' + data.students[i].firstname + ' ' + data.students[i].lastname + '</h3>';
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

            $('[data-show-full-due]').click(function() {
                var listDiv = $(this).data('show-full-due');
                $(this).parents('.top-due-work').addClass('hide');
                $('#' + listDiv).removeClass('hide');
                $pickerResponseEvents.addClass('hide');
                $('[data-due-work-title]').text(dictTop5Loaded);
            });

            $('[data-hide-full-due]').click(function() {
                var listDiv = $(this).data('hide-full-due');
                $pickerResponseDueWork.find('.top-due-work').removeClass('hide');
                $('#' + listDiv).addClass('hide');
                $pickerResponseEvents.removeClass('hide');
                $('[data-due-work-title]').text(dictMostLoaded);
            });
        }
        else if (template == 'events') {

            var events = [];

            html = '<h2 class="mini">Your Calendar</h2>';

            if (data.appointments === undefined) {
                //html = 'Error loading data';
            }
            else if (data.appointments.length == 0) {
                //html += 'No events found for ' + displayDate;
                //$pickerResponse.addClass('meta');
            }
            else {
                //$pickerResponse.removeClass('meta');

                // html += '<div class="row">';
                // html += '<div class="small-12 medium-12 columns">';
                // html += '<h2>Events on ' + displayDate + '</h2>';
                // html += '<ul class="small-12 information-list card">';

                // Populate with appointments for the selected date
                for (var i = 0; i < data.appointments.length; i++) {
                    // html += '<li>';
                    // html += '<h3>' + data.appointments[i].title + '</h3>';
                    // html += '<p class="meta">' + data.appointments[i].displayDateTime + '</p>';
                    // html += '</li>';
                    events.push({
                        id: i,
                        start: data.appointments[i].start,
                        end: data.appointments[i].end,
                        title: data.appointments[i].title,
                        allDay: data.appointments[i].allDay
                    });
                }

                // html += '</ul>';
                // html += '</div>';
                // html += '</div>';
            }

            html += '<div id="calendar-response-fc"></div>';
            $pickerResponseEvents.append(html);
            var $calendarDiv = $pickerResponseEvents.find('#calendar-response-fc');

            $calendarDiv.fullCalendar({
                header: false,
                eventLimit: true,
                views: {
                    agendaDay: {
                        eventLimit: 4
                    }
                },
                defaultView: 'agendaDay',
                events: events,
                height: 270,
                defaultDate: moment(data.date)
            });
        }


        $pickerResponse.append(html);
        if (template == 'duework' && data.students.length > 0) {
            $('#picker'+twigVars.id).find('[data-collapser]').collapsable();
        }
    }


    /**
     * Week/cycle previous & next functionality
     *
     * @param $button
     * @param prev
     */
    function changeCycle($button, prev) {

        var $currentWeek = $button.closest('[data-tt-week-selector]').find('[data-adtp-tt-cycle]:visible');

        if ( (prev && $currentWeek.prev().length) || (!prev && $currentWeek.next().length) ) {

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

            addDatesToDays();
            applyHeatToTimetable();
            resetTimetable();

        }
    }


    /**
     * Add date data attributes to day selectors on timetable picker
     */
    function addDatesToDays() {
        iterateVisibleTimetableDays(
            function (dayMoment) {
                $(this).attr('data-date', dayMoment.format('YYYY-MM-DD')).removeClass('disabled');
            },
            function () {
                $(this).removeAttr('data-date').addClass('disabled');
            }
        );
    }


    /**
     * Iterate the timetable days and apply the given callbacks to them accordingly
     *
     * @param {Function} ifDate
     * @param {Function} ifNotDate
     */
    function iterateVisibleTimetableDays(ifDate, ifNotDate) {
        // Change the date data attributes on each day
        var cycleNumber    = $byTimetable.find('[data-adtp-tt-cycle]').not('.hide').attr('data-adtp-tt-cycle') || 1;
        var selectedCampus = 'campus_' + timetable.campus.find(':selected').val();
        var selectedTerm   = timetable.term.find(':selected').val();

        $byTimetable.find('[data-tt-days] > div').each(function () {

            var dayNumber = $(this).attr('data-tt-day');

            if (dayNumber in twigVars.timetableVars[selectedCampus].cycles[selectedTerm][cycleNumber]) {
                var dayMoment = moment(twigVars.timetableVars[selectedCampus].cycles[selectedTerm][cycleNumber][dayNumber]);

                if (ifDate) {
                    ifDate.call(this, dayMoment);
                }

            } else if (ifNotDate) {
                ifNotDate.call(this);
            }
        });
    }


    /**
     * Whichever dates are visible in the timetable, apply heat to them
     */
    function applyHeatToTimetable() {
        iterateVisibleTimetableDays(function (dayMoment) {
            var yr  = dayMoment.year();
            var mo  = dayMoment.month() + 1;
            var day = dayMoment.date();

            applyHeatToDay($(this), yr, mo, day);
        });
    }


    /**
     * Adds heatmap to a month view of flatpickr
     *
     * @param {Flatpickr} instance
     */
    function applyHeatToCalendar(instance) {
        // loop through all days and set a heatmap height
        $(instance.days.childNodes).each(function() {
            var dt = $(this)[0].dateObj;

            var day = dt.getDate(),
                mo  = dt.getMonth() + 1,
                yr  = dt.getUTCFullYear();

            applyHeatToDay($(this), yr, mo, day);
        });
    }


    /**
     * Ergh... the fact that this function even exists.....
     *
     * @param {Flatpickr} instance
     */
    function applyHeatEverywhere(instance)
    {
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
        if (yr in heatCache && mo in heatCache[yr] && day in heatCache[yr][mo]) {
            element.attr('data-heat', parseInt(heatCache[yr][mo][day] / 20));
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

        fetchHeat(yr, mo, folderId, onSuccess);

        // TODO: very open to a better way of handling the month/year wraparound

        // also process previous month
        if (mo === 1) {
            fetchHeat(parseInt(yr) - 1, 12, folderId, onSuccess);
        } else {
            fetchHeat(yr, parseInt(mo) - 1, folderId, onSuccess);
        }

        // also also process next month
        if (mo === 12) {
            fetchHeat(parseInt(yr) + 1, 1, folderId, onSuccess);
        } else {
            fetchHeat(yr, parseInt(mo) + 1, folderId, onSuccess);
        }
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
            heatCache[yr][mo] = {};

            var promise = $.get((twigVars.routeLookup.getHeat
                .replace(/{folderId}/g, folderId))
                .replace(/{year}/g, yr)
                .replace(/{month}/g, mo));

            promise.done(function (data) {
                for (var day in data) {
                    heatCache[yr][mo][day] = data[day];
                }

                // do this async the first time (cold cache)
                onSuccess();
            }).fail(function () { console.error(arguments); });

        } else {
            // do it sync if the cache is hot
            onSuccess();
        }

    }


    /**
     * Append a tool tip regarding heatmap
     */
    function renderHeatMapHelp() {

        // TODO allow this to be injected by construction
        var tt = 'The "heat" of a day represents the largest number of assessments any of your students has due that day, capped at 5.';

        var $monthDiv = $('#picker' + twigVars.id).find('.flatpickr-current-month');
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
            startYear  = startMoment.year(),
            startMonth = startMoment.month() + 1,
            endYear    = endMoment.year(),
            endMonth   = endMoment.month() + 1
            ;

        // these are likely the same, but not a guarantee
        monthsToFetch[startYear] = [];
        monthsToFetch[endYear]   = [];

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

};
