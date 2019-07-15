(function($, FullCalendarBubble) {
    "use strict";

    // Resource booking variant of the bubble.
    var resourceFullCalendarBubble = function(fullCalendar, event, taskTypeId) {
        // Inherit from the base calendar bubble
        FullCalendarBubble.call(this, fullCalendar, event, taskTypeId);
    };


    // Inherit methods
    resourceFullCalendarBubble.prototype = Object.create(FullCalendarBubble.prototype);


    resourceFullCalendarBubble.prototype.display = function () {
        FullCalendarBubble.prototype.display.call(this);
    };

    /**
     * Render the resource booking's calendar bubble.
     *
     * @param bubbleElement A jQuery object pointing to the bubble's DOM node.
     * @param data          The data to render into the element.
     * @return {*}
     */
    resourceFullCalendarBubble.prototype.renderBubble = function (bubbleElement, data) {
        FullCalendarBubble.prototype.renderBubble.call(this, bubbleElement, data);

        // Do stuff with related bookings
        var $relatedBookings = bubbleElement.find('ul.relatedBookings').empty();
        if (data.relatedBookings && data.relatedBookings.length > 0) {
            $relatedBookings.prepend('<br /><h3>Related Bookings</h3>');
            data.relatedBookings.forEach(function(v) {
                $relatedBookings.append(
                    $('<li />').html('('+v.status.name+') <a href="'+v.href+'">'+v.title+'</a>')
                );
            });
        }
        // delete data.relatedBookings;
        return bubbleElement;
    };

    // expose the plugin in the window the plugin
    window.ResourceFullCalendarBubble = resourceFullCalendarBubble;
})(jQuery, FullCalendarBubble);
