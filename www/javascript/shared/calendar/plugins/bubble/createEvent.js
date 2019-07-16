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


    /**
     * Extends the base event bubble's formatData to include allDay and date
     * fields.
     */
    bubble.prototype.formatData = function() {
        var data    = FullCalendarViewEventBubble.prototype.formatData.call(this);
        data.allDay = this.event.allDay;
        data.date   = this.toMoment(this.event.start).format(
            data.allDay ? 'DD-MM-YYYY' : 'DD-MM-YYYY HH:mm'
        );
        return data;
    };


    /**
     * Render the create event bubble.
     *
     * @param bubbleElement A jQuery object pointing to the bubble's DOM node.
     * @param data          The data to render into the element.
     * @return {*}
     */
    bubble.prototype.renderBubble = function (bubbleElement, data) {
        var element = FullCalendarViewEventBubble.prototype.renderBubble.call(this, bubbleElement, data),
            self    = this;


        // Add event listeners
        // Should detach all of these listeners when the window gets closed

        var toggleFormState = function (form) {
            var trimmedTitle = form.find(':input[name="title"]').val().replace(/^\s+|\s+$/g, '');
            var submitButton = form.find(':submit');
            submitButton.prop('disabled', trimmedTitle === '');
        };


        // Control submit button
        var submitButton = bubbleElement.find(':submit');
        bubbleElement.on('change, keyup', ':input', function () {
            toggleFormState($(this).closest('form'));
        });
        bubbleElement.on('submit', 'form', function (event) {
            event.preventDefault();
            submitButton.prop('disabled', true);

            // The old implementation created a placeholder here.
            // I won't bother for now, you don't even see it

            // And submit
            $.ajax({
                url:    this.action,
                data:   $(this).serializeArray(),
                method: this.method,
                dataType: 'json',
            }).done(function (data) {
                // Add this event to the calendar
                self.fullCalendar.addEvent(data);
            }).always(function () {
                bubbleElement.foundation('reveal', 'close');
            });
        });


        // Listener for tab switching:
        // When you switch panels, preserve some data
        bubbleElement.find('[data-tab]').on('toggled', function (event, tab) {

            // Copy title between event and task panel
            // Then determine if it should be enabled or not
            // (All other fields should already be in both panels)
            var eventTitle = bubbleElement.find('#createEventPanel :input[name="title"]');
            var taskTitle  = bubbleElement.find('#createTaskPanel :input[name="title"]');
            // Srsly?
            var activeTab  = bubbleElement.find('.tabs-content .content.active');
            if (activeTab.attr('id') === 'createTaskPanel') {
                taskTitle.val(eventTitle.val());
                // cp('event', 'task', ['date', 'displayDate', 'title', 'allDay'])
            } else {
                eventTitle.val(taskTitle.val());
                // cp('task', 'event', ['date', 'displayDate', 'title', 'allDay'])
            }

            // Update button state when switching tabs
            toggleFormState(taskTitle.closest('form'));
            toggleFormState(eventTitle.closest('form'));
        });


        // click 'Edit Event' and push data through to main form page
        bubbleElement.on('click', '#edit-full', function() {
            var queryString      = $(this).closest('form').serialize();
            window.location.href = '/calendar/modify.php?from=inline&returnTo=calendar&' + queryString;
        });


        // // Attendance events only
        // /* Process attendances
        //  * NB May not be necessary to use custom getFormData();
        //  */
        // $('#editEvent').calendarAttendance({
        //     getFormData: function() {
        //         return {
        //             event: {
        //                 id: c.getForm('editEvent').find('input[name="eventId"]').val()
        //             }
        //         }
        //     }
        // });


        // // View event only:
        // // delete a task
        // c.getForm('editTask').on('click', '[data-interaction="delete"]', function(e) {
        //         // Do delete stuff
        //         var element = $(this);
        //         event.preventDefault();
        //         c.getForm('editTask').closest('[data-reveal]')
        //             .one('closed.fndtn.reveal', function() {
        //                     // confirmy-dialogy
        //                     element.confirmDialog({
        //                         text: 'Are you sure you wish to delete this task?'
        //                     });
        //                     c.bubble.hide();
        //             })
        //             .foundation('reveal', 'close');
        // });


        // // View event only:
        // // Dialog for use when clicking an item that is in a series
        // var updateSeriesDialog = $('#dialog-booking-bulk-update');
        // updateSeriesDialog.on('click', '[data-interaction="close"]', function(e) {
        //     e.preventDefault();
        //     updateSeriesDialog.foundation('reveal', 'close');
        //     updateSeriesDialog.html(updateSeriesDialog.html().replace(/booking/g, 'event'));
        // });


        // // View booking only:
        // // Copypasted code for updating booking in series
        // var editEventForm = $('#calendar-bubble').find('#editEvent');
        // editEventForm.on(
        //     'click',
        //     '[data-booking-button=booking-approve], [data-booking-button=booking-reject], [data-booking-button=booking-cancel]',
        //     function(event) {
        //         event.preventDefault()
        //         var href     = this.href
        //         var callback = function(data) {
        //             $.ajax(href, {data: data}).done(function() {
        //                 c.fullCalendar.refetchEvents()
        //             })
        //         }
        //         var seriesId = $(this).data('seriesId')
        //         if (seriesId) {
        //             // Remove all previous change status events, and reapply to this
        //             // dialog box.
        //             updateSeriesDialog.find('[data-booking-action-text]').text($(this).text().toLowerCase());
        //             updateSeriesDialog.html(updateSeriesDialog.html().replace(/event/g, 'booking'));
        //             updateSeriesDialog.foundation('reveal', 'open');
        //             updateSeriesDialog.off('click', '[data-efficacy="self"]');
        //             updateSeriesDialog.on('click', '[data-efficacy="self"]', function(e) {
        //                 e.preventDefault();
        //                 updateSeriesDialog.foundation('reveal', 'close');
        //                 callback({efficacy: 'self'});
        //             });
        //             updateSeriesDialog.off('click', '[data-efficacy="series"]');
        //             updateSeriesDialog.on('click', '[data-efficacy="series"]', function(e) {
        //                 e.preventDefault();
        //                 updateSeriesDialog.foundation('reveal', 'close');
        //                 updateSeriesDialog.html(updateSeriesDialog.html().replace(/booking/g, 'event'));
        //                 callback({efficacy: 'series', seriesId: seriesId});
        //             });
        //         }
        //         else {
        //             c.bubble.hide()
        //             callback()
        //         }
        //     }
        // );


        // // View event only:
        // // event for deleting a booking which might be in series
        // editEventForm.on(
        //     'click',
        //     '[data-interaction="delete"]',
        //     function(event) {
        //         // Do delete stuff
        //         var href    = this.href;
        //         var element = $(this);
        //         event.preventDefault();
        //         editEventForm.closest('[data-reveal]')
        //             .one('closed.fndtn.reveal', function() {
        //                 var eventId  = editEventForm.find('input[name="eventId"]').val();
        //                 var seriesId = editEventForm.find('input[name="seriesId"]').val();
        //                 if (seriesId) {
        //                     // triple treat!
        //                     updateSeriesDialog.find('[data-booking-action-text]').text('delete');
        //                     updateSeriesDialog.foundation('reveal', 'open');
        //                     updateSeriesDialog.off('click', '[data-efficacy="self"]');
        //                     updateSeriesDialog.on('click', '[data-efficacy="self"]', function(e) {
        //                         e.preventDefault();
        //                         updateSeriesDialog.foundation('reveal', 'close');
        //                         window.location.href = href + '&efficacy=self';
        //                     });
        //                     updateSeriesDialog.off('click', '[data-efficacy="series"]');
        //                     updateSeriesDialog.on('click', '[data-efficacy="series"]', function(e) {
        //                         e.preventDefault();
        //                         updateSeriesDialog.foundation('reveal', 'close');
        //                         window.location.href = href + '&efficacy=series&seriesId='+seriesId;
        //                     });
        //                 } else {
        //                     // confirmy-dialogy
        //                     element.confirmDialog({
        //                         text: 'Are you sure you wish to delete this event?',
        //                     });
        //                 }
        //                 c.bubble.hide();
        //             })
        //             .foundation('reveal', 'close');
        //
        //     }
        // );


        // // Base calendar:
        // // if a checkbox on the calendar is interacted, ajax
        // c.dom.on('change', 'input[type=checkbox]', function(e) {
        //
        //     var self = $(this)
        //     var ttl = self.siblings('.fc-title')
        //     if(self.prop('checked')) {
        //         ttl.addClass('strike')
        //     }
        //     else {
        //         ttl.removeClass('strike')
        //     }
        //     data = {
        //         eventId: self.attr('id').slice(1),
        //         completed: self.prop('checked') ? 1 : 0
        //     }
        //     c.cupdate(data)
        //     c.bubble.hide()
        //     c.suppressOne()
        //     e.stopPropagation()
        //     e.preventDefault()
        //     return false
        // })


        // // Filter:
        // // select all clicked
        // $('#calendar-controls').on('change','.select_all', function(e) {
        //     $(this).parent().find(':checkbox').prop('checked', $(this).prop('checked'));
        // });


        // // Triggers for calendar filter and export
        // $('#calendar').foundation('reveal','reflow'); // this should work but foundation is buggy
        // $('#fc-button-filter').click(function() { $('#calendar-controls').foundation('reveal','open') });
        // $('#fc-button-export').click(function() { $('#calendar-export').foundation('reveal','open') });

        return element;
    };


    // expose the plugin to the window
    window.FullCalendarCreateEventBubble = bubble;
})(jQuery, FullCalendarViewEventBubble);
