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
     */
    var fullCalendarBubbleFactory = function(fullCalendar) {
        this.fullCalendar = fullCalendar;
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

        return new FullCalendarBubble(this.fullCalendar, event, 11);
    };


    // expose the plugin in the window the plugin
    window.FullCalendarBubbleFactory = fullCalendarBubbleFactory;

})();
