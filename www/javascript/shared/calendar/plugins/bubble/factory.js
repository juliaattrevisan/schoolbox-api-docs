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
     * @param fullCalendar       The FullCalendar in which this bubble shows
     * @param taskTypeId         The ID of the task type within the calendar?
     * @param $createEventBubble a jQuery object pointing to the create event
     *                           bubble's DOM node
     * @param $viewEventBubble   a jQuery object pointing to the view event
     *                           bubble's DOM node
     * @param $editTaskBubble    a jQuery object pointing to the edit task
     *                           bubble's DOM node
     */
    var fullCalendarBubbleFactory = function(
        fullCalendar,
        taskTypeId,
        $createEventBubble,
        $viewEventBubble,
        $editTaskBubble
    ) {
        this.fullCalendar       = fullCalendar;
        this.taskTypeId         = taskTypeId;
        this.$createEventBubble = $createEventBubble;
        this.$viewEventBubble   = $viewEventBubble;
        this.$editTaskBubble   = $editTaskBubble;
    };


    /**
     * Build a bubble for viewing an existing event.
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
            return new FullCalendarViewBookingBubble(this.fullCalendar, event, this.taskTypeId, this.$viewEventBubble);
        }


        // Is this a task?
        if (Number(data.eventTypeId) === this.taskTypeId) {
            // Show the edit task bubble
        }


        // Otherwise...
        return new FullCalendarViewEventBubble(this.fullCalendar, event, this.taskTypeId, this.$viewEventBubble);
    };


    /**
     * Build a bubble for a form for a new event.
     *
     * @param date   A date object
     * @param allDay Is the event to be created as an all-day event?
     * @return object A bubble object
     */
    fullCalendarBubbleFactory.prototype.buildForNewEvent = function(date, allDay) {
        // The create event bubble needs special handling
        return new FullCalendarViewEventBubble(
            this.fullCalendar,
            {
                allDay: allDay,
                start:  date
            },
            this.taskTypeId,
            this.$createEventBubble
        );
    };


    // expose the plugin in the window the plugin
    window.FullCalendarBubbleFactory = fullCalendarBubbleFactory;

})();
