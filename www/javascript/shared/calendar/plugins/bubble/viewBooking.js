(function($, FullCalendarBubble) {
    "use strict";

    // Resource booking variant of the bubble.
    var resourceFullCalendarBubble = function(fullCalendar, event, taskTypeId) {
        // Inherit from the base calendar bubble
        FullCalendarViewEventBubble.call(this, fullCalendar, event, taskTypeId);
    };


    // Inherit methods
    resourceFullCalendarBubble.prototype = Object.create(FullCalendarViewEventBubble.prototype);


    /**
     * formatData override: render resource booking actions
     */
    resourceFullCalendarBubble.prototype.formatData = function () {
        var data = FullCalendarViewEventBubble.prototype.formatData.call(this);


        // // Resource booking variant only:
        // // Prefix booking status for bookings
        // if (data.booking) {
        //     data.bookingStatus = data.booking.status.name;
        // }


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


        return data;
    };

    /**
     * Render the resource booking's calendar bubble.
     *
     * @param bubbleElement A jQuery object pointing to the bubble's DOM node.
     * @param data          The data to render into the element.
     * @return {*}
     */
    resourceFullCalendarBubble.prototype.renderBubble = function (bubbleElement, data) {
        FullCalendarViewEventBubble.prototype.renderBubble.call(this, bubbleElement, data);

        // Render status

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
    window.FullCalendarViewBookingBubble = resourceFullCalendarBubble;
})(jQuery, FullCalendarViewEventBubble);
