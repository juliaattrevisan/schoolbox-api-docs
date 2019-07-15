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
        // Finally build the DOM for the bubble
        // (The actual DOM element needs to be configurable)
        this.renderBubble($('#calendar-bubble'), this.formatData())
            .foundation('reveal', 'open');
    };


    /**
     * Massage data from the event into a format which can be rendered in
     * renderBubble.
     *
     * @return object
     */
    fullCalendarBubble.prototype.formatData = function() {

        // setup data to be displayed
        var data = {};
        if (this.event.extendedProps && this.event.extendedProps.data) {
            data = $.extend(true, {}, this.event.extendedProps.data);
        }


        // Normalize note and title
        data.title = data.note = this.event.title;
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


        // All variants:
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
        if (data.custom && data.custom.moreDetailsLink && data.custom.moreDetailsLink.length > 0) {
            data.moreDetailsLink =
                $('<a class="button icon-due-work">View More Details</a>')
                    .attr('href', data.custom.moreDetailsLink);
        }


        // Standard variant only:
        // Attendance info
        if (data.attendance) {
            // Attendance List button
            if (data.attendance.listButton) {
                data.attendance.icon = '';
                data.attendance.listButton = '<a class="button icon-group" href="/calendar/attendance/list/'
                    + data.meta.eventId + '">Attendees</a>'
            }
            // Attend/Unattend button
            if (data.attendance.requestButton) {
                data.attendance.requestButton = '<a class="button icon-reply" href="#" data-attendance-request>'
                    + data.attendance.requestButton.toString() + '</a>'
            }
        }

        // // Start and end of event?
        // data.start = this.event.start;
        // data.end   = this.event.end;
        // if ('placeholder' in data) {
        //     data.placeholder = true;
        // }


        // Standard event only:
        // Hide task/non-task data
        data.isTask = Number(data.eventTypeId) === this.taskTypeId;
        return data;
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

        // The only reference to suppress I see is in the old calendar:
        // it has to do with some ancient work whose purpose I no longer know

        // // check that this isn't being suppressed?
        // if (this.calendar.suppress === true) {
        //     this.calendar.suppress = false;
        //     return;
        // }

        // Modal fields: may be revealed later?
        bubbleElement.find('[data-event-modal-field]')
            .hide()
            .addClass('hide');


        // Show the date and title at least
        bubbleElement.find('[data-event-modal-field="displayDate"]')
            .show()
            .removeClass('hide')
            .text(data.date);
        bubbleElement.find('[data-event-modal-field="title"]')
            .show()
            .removeClass('hide')
            .text(data.title);


        // Hide "Posted By" if there is no author
        // (This happens on subscribed vcals for example)
        var showAuthor = data.authorId && Number(data.authorId) !== -1;
        bubbleElement.find('[data-event-modal-field="post-by"]')
            .toggle(showAuthor)
            .toggleClass('hide', !showAuthor);


        // Hide reviewed by as well
        var showReviewer = data.authorId && Number(data.authorId) !== -1;
        bubbleElement.find('[data-event-modal-field="reviewedBy"]')
            .toggle(showReviewer)
            .toggleClass('hide', !showReviewer);


        // Show/hide location?
        var showLocation = data.location && data.location.length > 0;
        bubbleElement.find('[data-event-modal-field="location"]')
            .toggle(showLocation)
            .toggleClass('hide', !showLocation);


        // Task display (or not)
        var showTask = data.isTask;
        bubbleElement.find('#editTask')
            .toggle(showTask)
            .toggleClass('hide', !showTask);
        bubbleElement.find('#editEvent')
            .toggle(!showTask)
            .toggleClass('hide', showTask);


        // // Fill in form inputs?
        // this.fillForm(method+'Event', data);
        // this.fillForm(method+'Task', data);

        // onOpen is used by the MGGS PBL custom work:
        // see library/Custom/Mggs/Learning/Upcoming/templates/upcoming.twig
        // // There's some "onOpen" callback?
        // // Is this required? Would I ever NOT open a bubble on click?
        // if (this.calendar.options.bubble.onOpen) {
        //     return this.calendar.options.bubble.onOpen.call(this, method, data);
        // }

        return bubbleElement;
    };


    // expose the plugin in the window the plugin
    window.FullCalendarBubble = fullCalendarBubble;

})(jQuery, FullCalendarMoment);
