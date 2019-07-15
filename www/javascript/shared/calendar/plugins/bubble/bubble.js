(function($, FullCalendarMoment) {
    "use strict";


    /**
     * Calendar bubble plugin: simply show a calendar event bubble.
     *
     * @param fullCalendar The FullCalendar in which this bubble shows
     * @param event        A FullCalendar event object
     * @param taskTypeId   The ID of the task type within the calendar?
     */
    var fullCalendarBubble = function(fullCalendar, event, taskTypeId) {
        this.fullCalendar = fullCalendar;
        this.event        = event;
        this.taskTypeId   = taskTypeId;
    };


    /**
     * Wrapper around FullCalendar's Moment plugin.
     *
     * @param date
     * @return object date as a Moment object
     */
    fullCalendarBubble.prototype.toMoment = function(date) {
        return FullCalendarMoment.toMoment(date, this.fullCalendar);
    };


    /**
     * Actually render the bubble we've created.
     */
    fullCalendarBubble.prototype.display = function() {
        // setup data to be displayed
        var data = $.extend(true, {}, this.event.extendedProps.data);


        // Resource booking variant only:
        // Prefix booking status for bookings
        if (data.booking) {
            data.bookingStatus = data.booking.status.name;
            $('[data-event-modal-field="bookingStatus"]').show();

            data.note = this.event.title
        }
        else {
            data.note = this.event.title;
            $('[data-event-modal-field="bookingStatus"]').hide();
        }


        // Normalize note and title
        data.title = data.note;
        data.date  = this.determineDateSpan(this.event.start, this.event.end, this.event.allDay);


        // All variants:
        // Links to Modify and Delete screens
        if (this.event.editable) {
            var url = '';
            if (data.custom && data.custom.modifyLink && data.custom.modifyLink.length) {
                url = data.custom.modifyLink;
                data.deleteLink = '';
            }
            else {
                url = '/calendar/modify.php?eventID=' + data.meta.eventId + '&returnTo=calendar';
                var deleteUrl = '/calendar/modifyForm.php?action=Delete&id=' + data.meta.eventId + '&returnTo=calendar';
                data.deleteLink = '<a class="button icon-delete" data-interaction="delete" href="' + deleteUrl + '">Delete</a>';
            }

            data.modifyLink = '<a class="button icon-modify" href="' + url + '">Modify</a>';
        }
        else {
            data.modifyLink = '';
            data.deleteLink = '';
        }


        // Group events:
        // have a go to homepage link
        // (All of these fall under the banner of links on the bubble. Put them in a buildLinks function)
        data.homepageLink = '';
        if (data.links && data.links.category && data.links.category.id > 0) {
            data.homepageLink =
                $('<a class="button icon-home">Homepage</a>')
                    .attr('href', '/homepage/' + String(data.links.category.id));
        }


        // Assessment variant only:
        // Submission History Link
        data.moreDetailsLink = '';
        try {
            if(data.custom.moreDetailsLink.length > 0) {
                data.moreDetailsLink =
                    $('<a class="button icon-due-work">View More Details</a>')
                        .attr('href', data.custom.moreDetailsLink);
            }
        }
        catch (e) {}


        // Standard variant only:
        // Attendance info
        if ('attendance' in data) {
            $.each(data.attendance, function(k, v) {
                // replace String.prototype.capitalise (WTF?)
                data['attendance' + k.charAt(0).toUpperCase() + k.substring(1)] = v
            });

            // Attendance List butonye
            if (data.attendanceListButton) {
                data.attendanceIcon = '';
                data.attendanceListButton = '<a class="button icon-group" href="/calendar/attendance/list/'
                    + data.meta.eventId + '">Attendees</a>'
            }
            // Attend/Unattend butonye
            if (data.attendanceRequestButton) {
                data.attendanceRequestButton = '<a class="button icon-reply" href="#" data-attendance-request>' + data.attendanceRequestButton.toString() + '</a>'
            }
        }


        // Resource booking variant only: render all of this stuff
        data.bookingActions = [];
        data.relatedBookings = [];
        if (data.booking) {
            // Booking butonyes (if available)
            if (data.booking.buttons) {
                $.each(data.booking.buttons, function() {
                    if (this.href) {
                        data.bookingActions.push(
                            $('<a class="button" />')
                                .data('seriesId', data.meta.seriesId)
                                .addClass(this.class)
                                .attr('data-booking-button', 'booking-'+this.action)
                                .attr('href', this.href)
                                .text(this.label)
                                .prepend('<span />')
                        );

                        // Sigh. Hack to insert whitespace to mimic standard button HTML.
                        // (A pure CSS margin solution would require the parent element to have font-size=0)
                        data.bookingActions.push(' ');
                    }
                })
            }

            // Booking reviewer (if available)
            if (data.booking.reviewer) {
                data.reviewer   = data.booking.reviewer.name;
                data.reviewerId = data.booking.reviewer.id;
            }

            // Related bookings
            data.relatedBookings = data.booking.related;
        }

        data.start = this.event.start;
        data.end   = this.event.end;
        if ('placeholder' in data) {
            data.placeholder = true;
        }

        // var doDisplay = self.bubble.display('edit', data, jsEvent, this);
        //
        // if (doDisplay === false) {
        //     // if explicit false is given, proceed no further
        //     return;
        // }


        // Standard event only:
        // Hide event types in the bubble
        data.isTask = Number(data.eventTypeId) === this.taskTypeId;
        // bubble.find('#editTask').toggleClass('hide', !isTask);
        // bubble.find('#editEvent').toggleClass('hide', isTask);


        // Finally build the DOM for the bubble
        // (The actual DOM element needs to be configurable)
        var bubbleElement = $('#calendar-bubble');
        this.renderBubble(bubbleElement, data).foundation('reveal', 'open');
    };


    /**
     * Determines a user-readable date span.
     *
     * @param startDate The event's start date
     * @param endDate   The event's end date
     * @param allDay    Is the event an all-day event or not?
     * @return {string}
     */
    fullCalendarBubble.prototype.determineDateSpan = function (startDate, endDate, allDay) {
        var format      = allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma',
            momentStart = this.toMoment(startDate),
            start       = momentStart.format(format),
            momentEnd   = null,
            end         = null;

        if (endDate != null) {
            momentEnd = this.toMoment(endDate);
            if (this.event.allDay) {
                end = momentEnd.subtract(1, 'd').format(format);
            } else {
                end = momentEnd.format(format);
            }
        }

        if (momentEnd && !momentStart.isSame(momentEnd)) {
            // Start and end aren't the same - stitch them together.
            if (!this.event.allDay && momentStart.isSame(momentEnd, 'day')) {
                // Between two times on the same day - show the timespan but only one date
                return start + " – " + momentEnd.format('h:mma');
            } else {
                // Between two different days - show the whole timespan
                return start + " – " + end;
            }
        } else {
            // Start and end are the same - just show the start
            return start;
        }
    };


    /**
     * Render the calendar bubble.
     *
     * @param bubbleElement A jQuery object pointing to the bubble's DOM node.
     * @param data          The data to render into the element.
     * @return {*}
     */
    fullCalendarBubble.prototype.renderBubble = function (bubbleElement, data) {
        return bubbleElement;
    };


    // expose the plugin in the window the plugin
    window.FullCalendarBubble = fullCalendarBubble;

})(jQuery, FullCalendarMoment);
