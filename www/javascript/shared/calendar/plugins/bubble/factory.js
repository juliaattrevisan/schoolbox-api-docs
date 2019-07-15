(function() {
    "use strict";

    // Factory for making bubbles.
    // Types of bubble:
    // * Standard
    // * Resource Booking
    // * Attendance



    /**
     * Calendar bubble factory.
     *
     * Figure out which kind of bubble to show, and how to show it.
     *
     * @param fullCalendar The FullCalendar in which this bubble shows
     * @param taskTypeId   The ID of the task type within the calendar?
     */
    var fullCalendarBubbleFactory = function(fullCalendar, taskTypeId) {
        this.fullCalendar = fullCalendar;
        this.taskTypeId   = taskTypeId;
    };


    /**
     * Build a bubble.
     *
     * @param event A FullCalendar event object
     * @return object A bubble object
     */
    fullCalendarBubbleFactory.prototype.build = function(event) {
        // The type of bubble depends on the type of event.
        // * Standard event (inc. timetable, vCAL)
        // * Bookings
        // * Assessments
        var data = event.extendedProps.data || {};


        // Does this have a booking?
        if (data.booking) {
            return new ResourceFullCalendarBubble(this.fullCalendar, event, this.taskTypeId);
        }


        // Otherwise...
        return new FullCalendarBubble(this.fullCalendar, event, this.taskTypeId);
    };


    /**
     * Build a bubble for a form for a new event.
     *
     * @param date   A date object
     * @param allDay Is the event to be created as an all-day event?
     * @return object A bubble object
     */
    fullCalendarBubbleFactory.prototype.buildForNewEvent = function(date, allDay) {
        return new FullCalendarBubble(
            this.fullCalendar,
            {
                allDay: allDay,
                start:  date
            },
            this.taskTypeId
        );
    };


    // expose the plugin in the window the plugin
    window.FullCalendarBubbleFactory = fullCalendarBubbleFactory;

})();
