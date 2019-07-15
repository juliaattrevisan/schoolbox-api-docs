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

        // Figure out subclassing in JS

        // Does this have a booking?

        // Can I modify it?

        // Does it have a homepage link?

        // Is it an assessment (i.e. does it have a more details link)?

        // Does it support attendance?


        // Return some kind of bubble
        return new ResourceFullCalendarBubble(this.fullCalendar, event, this.taskTypeId);
    };


    // expose the plugin in the window the plugin
    window.FullCalendarBubbleFactory = fullCalendarBubbleFactory;

})();
