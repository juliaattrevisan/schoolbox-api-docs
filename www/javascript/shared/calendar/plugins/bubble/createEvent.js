(function($, FullCalendarViewEventBubble) {
    "use strict";

    /**
     * Create event bubble.
     *
     * @param fullCalendar   The FullCalendar in which this bubble shows
     * @param event          A FullCalendar event object
     * @param taskTypeId     The ID of the task type within the calendar?
     * @param $bubbleDomNode a jQuery object pointing to the bubble's DOM node
     */
    var bubble = function(fullCalendar, event, taskTypeId, $bubbleDomNode) {
        FullCalendarViewEventBubble.call(this, fullCalendar, event, taskTypeId, $bubbleDomNode);
    };


    // Inherit methods
    bubble.prototype = Object.create(FullCalendarViewEventBubble.prototype);


    // expose the plugin to the window
    window.FullCalendarCreateEventBubble = bubble;
})(jQuery, FullCalendarViewEventBubble);
