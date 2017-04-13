/*
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

// Picker settings

// Build datepicker options here... again...
$.fn.alaressDateTimeModal = function (twigVars) {

    var $picker = $('#' + twigVars.element),
        pickerOptions = {
        // Flatpickr related
        enableTime: getValue($picker, 'data-datepicker-time'),

        // All the rest
        altFormat: getValue($picker, 'data-datepicker-format'), // false|date|datetime
        allDay: getValue($picker, 'data-datepicker-allday'), // Show All Day switch
        allDayExt: getValue($picker, 'data-datepicker-alldayext'),
        type: getValue($picker, 'data-datepicker-call'), // Ajax call type: events, duework or nothing
        endDay: getValue($picker, 'data-datepicker-endofday'), // Should allday change time to 23:59:59
        endPeriod: getValue($picker, 'data-datepicker-endofperiod') // Should allday change time to 23:59:59

    };

    function hasTime(value) {
        var v = moment(value, convertFormat(twigVars.displayFormat));
        if (pickerOptions.endDay === true && v.hours() == 23 && v.minutes() == 59 && (v.seconds() == 59 || v.second() == 0)) {
            return false;
        }
        if (v.hours() == 0 && v.minutes() == 0 && v.seconds() == 0) {
            return false;
        }
        return true;
    }

    function requestDataByDate(date, target) {
        if (twigVars.type == 'duework') {

            loadingResponse(target);

            var folder = $('*[name = "folderId"]').val();

            $.get((twigVars.routeLookup.getDueByDate).replace(/{folder}/g, folder).replace(/{date}/g, date))

                .done(function (data) {

                    appendResponse(data, 'duework', target);

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });
        }
        else if (twigVars.type == 'event') {

            // AJAX in appointments for the selected date

            loadingResponse(target);
            $.get((twigVars.routeLookup.getByDate).replace(/_date_/g, date))
                .done(function (data) {

                    appendResponse(data, 'events', target);

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified date');
                });
        }
        else {
            // Do nothing
        }
    }

    function requestDataByDay(element) {

        var day = element.attr('data-day');
        var period = element.attr('data-period');

        $('.period-selected').removeClass('period-selected');
        $('table').find('[data-selected=1]').attr('data-selected', 0);
        element.addClass('period-selected');
        element.attr('data-selected', 1);
        $('.day-' + day).addClass('period-selected');
        $('.description-' + period).addClass('period-selected');
        periodClicked = 'period' + day + '_' + period;

        var campus = timetable.campus.val(),
            term = timetable.term.val(),
            cycle = timetable.cycle.val();

        if (twigVars.type == 'duework') {

            // Can the assessment form have dates and be called outside the class page?
            var folder = $('*[name = "folderId"]').val();

            loadingResponse('timetable');

            $.get((twigVars.routeLookup.getByTimetableDay).replace(/{campusId}/g, campus).replace(/{termId}/g, term).replace(/{cycle}/g, cycle).replace(/{day}/g, day).replace(/{folder}/g, folder))
                .done(function (data) {

                    appendResponse(data, 'duework', 'timetable');

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified period');
                });
        }
        else if (twigVars.type == 'event') {
            // Or fetch events

            loadingResponse('timetable');
            $.get((twigVars.routeLookup.getEventsByTimetableDay).replace(/{campusId}/g, campus).replace(/{termId}/g, term).replace(/{cycle}/g, cycle).replace(/{day}/g, day))
                .done(function (data) {

                    appendResponse(data, 'events', 'timetable');

                })
                .fail(function () {
                    console.log('Failed to retrieve appointments for specified period');
                });
        }
        else {
            // Fetch a timetable day date
            loadingResponse('timetable');
            $.get((twigVars.routeLookup.getTimetableDate).replace(/{campusId}/g, campus).replace(/{termId}/g, term).replace(/{cycle}/g, cycle).replace(/{day}/g, day))
                .done(function (data) {
                    appendResponse(data, '', 'timetable');
                })
                .fail(function () {
                    console.log('Timetable day to date conversion failed');
                });
        }
    }

    // Show loader
    function loadingResponse(target) {
        var pickerResponse = $('#' + target + 'Response' + twigVars.id);
        pickerResponse.html('');
        pickerResponse.append('<img src="/images/foundation/preloader.gif" alt="preloader.gif" style="text-align:center;margin:auto;display: block;">');

        if (target == 'timetable') {
            timetable.submit.addClass('disabled');
        }
    }

    // Prepare and append response HTML
    function appendResponse(data, template, target) {

        var pickerResponse = $('#' + target + 'Response' + twigVars.id);

        // Clear out any old response display
        pickerResponse.html('');
        var html = '';

        // Ajax returned date
        displayDate = moment(data.date, convertFormat(twigVars.displayFormat)).format(convertFormat('F d, Y'));

        if (target == 'timetable') {
            timetable.submit.removeClass('disabled');
            timetable.submit.attr('data-timetable-value', data.date);

            var $byTimetable = $('#byTimetable-' + twigVars.id),
                periodSelected = $byTimetable.find('table').find('[data-selected=1]').attr('data-period');

            $('.timetableDate' + twigVars.id)
                .css('display', 'block')
                .find('.displayDate').empty().append(displayDate);

            if (periodSelected) {
                var periodStart = $byTimetable.find('[data-period-number=' + periodSelected + ']').attr('data-period-start'),
                    periodEnd = $byTimetable.find('[data-period-number=' + periodSelected + ']').attr('data-period-end');

                $((pickerOptions.endPeriod ? '#periodEnd' : '#periodStart') + twigVars.id).prop('checked', true);

                $('label[for="periodStart' + twigVars.id + '"]').empty().append(periodStart);
                $('label[for="periodEnd' + twigVars.id + '"]').empty().append(periodEnd);
                $('#periodStart' + twigVars.id).closest('div').removeClass('hide');
            }
        }

        if (template == 'duework') {

            if (data.students === undefined) {
                html = 'Error loading data';
            }
            else if (data.students.length == 0) {
                html += 'Nothing due on ' + displayDate + '';
                pickerResponse.addClass('meta');
            }
            else {

                pickerResponse.removeClass('meta');

                // Populate with results for the selected date

                html += '<div class="row">';
                html += '<div class="small-12 medium-12 columns">';
                html += '<h2>Work due on ' + displayDate + '</h2>';
                html += '<ul class="small-12 information-list expandable-card">';
                for (var i = 0; i < data.students.length; i++) {
                    html += '<li>';
                    html += '<div>';
                    html += '<div class="expander icon-down-arrow" data-collapser="collap-' + i + '"></div>';
                    html += '<div class="card small-12">';
                    html += '<a href="/search/user/' + data.students[i].id + '">';
                    html += '<img src="/portrait.php?userId=' + data.students[i].externalId + '&amp;size=square80">';
                    html += '<h3>' + data.students[i].firstname + ' ' + data.students[i].lastname + '</h3>';
                    html += '<p class="meta">' + data.students[i].assessmentsDue + ' due' + '</p>';
                    html += '</a>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="small-12 column" data-collapse-state="collapsed" data-collapsable="collap-' + i + '" style="display: none;">';
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
        }
        else if (template == 'events') {
            if (data.appointments === undefined) {
                html = 'Error loading data';
            }
            else if (data.appointments.length == 0) {
                html += 'No events found for ' + displayDate;
                pickerResponse.addClass('meta');
            }
            else {
                pickerResponse.removeClass('meta');

                html += '<div class="row">';
                html += '<div class="small-12 medium-12 columns">';
                html += '<h2>Events on ' + displayDate + '</h2>';
                html += '<ul class="small-12 information-list card">';
                // Populate with appointments for the selected date
                for (var i = 0; i < data.appointments.length; i++) {
                    html += '<li>';
                    html += '<h3>' + data.appointments[i].title + '</h3>';
                    html += '<p class="meta">' + data.appointments[i].displayDateTime + '</p>';
                }
                html += '</ul>';
                html += '</div>';
                html += '</div>';
            }
        }
        else {
            // nope
            html = '';
        }

        pickerResponse.append(html);
        if (template == 'duework' && data.students.length > 0) {
            $('[data-collapser]').collapsable();
        }
    }

    function setPickerSize(reset) {
        var addClass;

        if (reset || twigVars.timetable === false) {
            addClass = 'pickerNarrow'
        }
        else {
            var daysInCycle = timetable.campus.find(':selected').attr('data-days');
            addClass = daysInCycle > 7 ? 'pickerWide' : 'pickerNarrow';
        }

        $('#picker' + twigVars.id)
            .removeClass(addClass == 'pickerNarrow' ? 'pickerWide' : 'pickerNarrow')
            .addClass(addClass);

    }

    // Timetable picker
    if (twigVars.timetable) {

        // Timetable elements
        var $byTimetable = $('#byTimetable-' + twigVars.id),
            timetable = {
                campus: $byTimetable.find('select[name*="campus"]'),
                term: $byTimetable.find('select[name*="term"]'),
                cycle: $byTimetable.find('select[name*="cycle"]'),
                submit: $byTimetable.find('.timetable-submit')
            };

        // Build cycles dynamically
        function buildCycles() {

            var daysInCycle = timetable.campus.find(':selected').attr('data-days'),
                termDuration = timetable.term.find(':selected').attr('data-duration');

            // Don't try to divide by zero
            if ((daysInCycle !== undefined && daysInCycle > 0) && (termDuration !== undefined && termDuration > 0)) {
                var optionsHtml = '';

                //Select cycle number on first load
                var cycleSelected = parseInt(twigVars.timetableDay.daysUntilDate / daysInCycle);
                for (var i = 1; i <= (termDuration / daysInCycle); i++) {
                    var selected = '';

                    if (cycleSelected == i) {
                        selected = 'selected';
                    }
                    optionsHtml += '<option value="' + i + '" ' + selected + '>Week ' + i + '</option>';
                }
                timetable.cycle.empty().append(optionsHtml);

                // Increase space to accommodate a table
                var $byTimetable = $('#byTimetable-' + twigVars.id);
                if (daysInCycle > 7) {
                    setPickerSize(false);
                    $byTimetable.find('#timetable' + twigVars.id).closest('.columns').removeClass('medium-6').addClass('medium-7');
                    $byTimetable.find('#timetableResponse' + twigVars.id).closest('.columns').removeClass('medium-6').addClass('medium-5');
                }
                // Back to normal
                else {
                    setPickerSize(true);
                    $byTimetable.find('#timetable' + twigVars.id).closest('.columns').removeClass('medium-7').addClass('medium-6');
                    $byTimetable.find('#timetableResponse' + twigVars.id).closest('.columns').removeClass('medium-5').addClass('medium-6');
                }
            }
            else {
                noTimetableData();
            }
        }

        // Highlight day and period
        function showPeriod() {
            var daysInCycle = timetable.campus.find(':selected').attr('data-days');
            if (daysInCycle !== undefined && daysInCycle > 0) {
                var isWeekend = twigVars.timetableDay.isWeekend;
                // Highlight day if it doesn't fall on a weekend
                if (isWeekend == 0) {
                    var period = 0;
                    // Get day of the cycle
                    var cycleDay = twigVars.timetableDay.daysUntilDate % daysInCycle;
                    if (cycleDay == 0) {
                        cycleDay = daysInCycle;
                    }

                    var $byTimetable = $('#byTimetable-' + twigVars.id);
                    $byTimetable.find('.day-' + cycleDay).addClass('period-selected');
                    $byTimetable.find('.period-description').each(function () {

                        var s = moment($(this).attr('data-period-start'), 'h:mma').format('HHmm'),
                            e = moment($(this).attr('data-period-end'), 'h:mma').format('HHmm'),
                            t = moment(twigVars.date).format('HHmm');

                        if ((t >= s) && (t < e)) {
                            $(this).addClass('period-selected');
                            period = $(this).attr('data-period-number');
                        }

                        if ((cycleDay > 0) && (period > 0)) {
                            $('#byTimetable-' + twigVars.id).find('.period-' + cycleDay + '-' + period).addClass('period-selected');
                        }
                    });
                }
            }
            else {
                noTimetableData();
            }
        }

        // Reveal Timetable
        function revealTimetable() {
            var campus = timetable.campus.find(':selected').val();
            $('.timetablePicker' + twigVars.id).css('display', 'none');
            $('#timetable' + twigVars.id + '-' + campus).css('display', 'table');
        }

        function noTimetableData() {
            var html = '<div data-alert="" class="alert-box alert">Not enough data to build a timetable <br /> for the selected campus and term.<a href="" class="close">×</a></div>';

            timetable.cycle.empty().css('display', 'none');
            $('.timetablePicker' + twigVars.id).empty().css('display', 'none');
            timetable.submit.css('display', 'none');

            $('#timetable' + twigVars.id).append(html);
        }

        function resetTimetable() {
            var timetable = $('#byTimetable-' + twigVars.id);

            timetable.find('.period-selected').removeClass('period-selected');
            timetable.find('table').find('[data-selected=1]').attr('data-selected', 0);

            $('.timetableDate' + twigVars.id).css('display', 'none');

            timetable.submit.addClass('disabled');
        }

        buildCycles();
        showPeriod();
        revealTimetable();
        // On first load request data by date for the timetable
        requestDataByDate(encodeURIComponent(twigVars.date.getDate() + '/' + (twigVars.date.getMonth() + 1) + '/' + twigVars.date.getFullYear()), 'timetable');


        // Update cycles on Campus or Term change
        timetable.campus.change(function () {
            buildCycles();
            // Clear selected
            resetTimetable();
            revealTimetable();
        });

        timetable.term.change(function () {
            buildCycles();
            resetTimetable();
        });

        timetable.cycle.change(function () {
            resetTimetable();
        });

        var timetableAppointments = $('#timetableAppointments' + twigVars.id);

        // Timetable picker behaviour
        $('table.timetablePicker').mouseout(function () {
            $('.period-hover').removeClass('period-hover');
        });

        $('.has-period').hover(function () {

            $('.period-hover').removeClass('period-hover');
            var day = $(this).attr('data-day');
            var period = $(this).attr('data-period');
            $('.day-' + day).addClass('period-hover');
            $('.description-' + period).addClass('period-hover');

        });

        // Loading events on timetable picker click
        var displayDate = moment().format(convertFormat(twigVars.displayFormat)); //today
        $('.has-period').click(function () {

            requestDataByDay($(this));
        });
    }

    // Setup calendar
    var newDate, latestDate;

    function renderAllday(instance) {
        var timeDiv = $('#pickerCalendar' + twigVars.id).closest('#picker' + twigVars.id).find('.flatpickr-time');

        var html = '';

        // Build an All Day switch if it's been set in the options
        if (pickerOptions.allDay === true) {
            html += '<div class="time-switch"><input id="hideTime' + twigVars.id + '" type="checkbox" value="1" checked>' +
                '<label for="hideTime' + twigVars.id + '" class="checkbox-label">All Day</label></div>' +
                '</div>';
        }

        //html += '<div class="small-6 columns"></div></div>';

        timeDiv.after(html);

    }

    // Accommodating the "start of the week" SB config
    Flatpickr.localize(Flatpickr.l10ns.default);
    Flatpickr.l10ns.default.firstDayOfWeek = twigVars.weekStartDay;

    window.pickerInstance[twigVars.id] = flatpickr("#pickerCalendar" + twigVars.id, {

        defaultDate: twigVars.date,
        dateFormat: pickerOptions.enableTime ? twigVars.displayFormat : twigVars.displayFormatDate,
        inline: true,
        altInput: false,
        allowInput: true,
        disableMobile: true,
        minuteIncrement: 1,
        enableTime: pickerOptions.enableTime,

        onReady: function (selectedDates, dateStr, instance) {
            renderAllday(instance);

            // Hide time picker if allday hidden field is set to 1
            if (pickerOptions.allDayExt !== false && $('#' + pickerOptions.allDayExt).val() == 1) {
                checkAllday(true, twigVars.id);
            }
            else if (pickerOptions.allDayExt !== false && $('#' + pickerOptions.allDayExt).val() == 0) {
                checkAllday(false, twigVars.id);
            }
            else if (hasTime(instance.input.value) === false) {
                checkAllday(true, twigVars.id);
            }
            else {
                checkAllday(false, twigVars.id);
            }

            if (pickerOptions.allDay === true) {
                $('#hideTime' + twigVars.id).click(function () {
                    if ($('#hideTime' + twigVars.id).is(':checked')) {
                        checkAllday(true, twigVars.id);
                    }
                    else {
                        checkAllday(false, twigVars.id);
                    }
                });
            }

            requestDataByDate(encodeURIComponent(twigVars.date.getDate() + '/' + (twigVars.date.getMonth() + 1) + '/' + twigVars.date.getFullYear()), 'calendar');
        },
        onChange: function (selectedDates, dateStr, instance) {

            // Put the display date onto the calendar div so we can capture it when the modal is closed
            var dateSplit = dateStr.split(' ');
            $('#pickerCalendar' + twigVars.id)
                .attr('data-date', dateSplit[0])
                .attr('data-time', dateSplit[1]);

            // Check if selected date has changed and don't send an ajax on time change
            var selectedObj = new Date(selectedDates);
            newDate = selectedObj.getDate() + '/' + (selectedObj.getMonth() + 1) + '/' + selectedObj.getFullYear();
            if (newDate !== latestDate) {

                requestDataByDate(encodeURIComponent(dateSplit[0]), 'calendar');
            }
            latestDate = newDate;
        }

    });

    // Resize modal on changing tabs
    $('a[href="#byDate-' + twigVars.id + '"]').click(function () {
        setPickerSize(true);
    });
    $('a[href="#byTimetable-' + twigVars.id + '"]').click(function () {
        setPickerSize(false);
    });
};
