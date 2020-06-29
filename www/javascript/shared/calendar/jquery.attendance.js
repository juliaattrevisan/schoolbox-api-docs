(function() {

    /**
     * Event Attendance
     *
     * Everything to do with attendee lists and rsvps
     *
     * @param options
     */
    $.fn.attendance = function(options) {
        options = options || {};

        let config = init(options, $(this));

        /* Set up selectizes if you have perms
         */
        // if (config.canModify) {
        //
        //     /* Initial conditions
        //      */
        //     toggleCreateAttendance(config.available.attr('data-available'));
        //
        //     /* Selectize for adding/removing people
        //      */
        //     const selectize = config.createAttendanceSelector.selectizeSelector({
        //         ajax: {
        //             url: '/ajax/user.php',
        //             type: 'get',
        //             data: {groupID: 0, showCampus: true},
        //             dataType: 'json'
        //         },
        //         multiple: false
        //     });
        //
        //     setupOnAddAttendee(selectize);
        //     setupOnRemoveAttendee(selectize);
        // }


        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            let defaults = {
                element: element,

                /* Access perms for hiding/showing form elements
                 */
                canModify: false,

                /* Add a person
                 */
                createAttendanceForm: $('#createAttendanceForm'),

                /* Selectize for adding a person
                 */
                createAttendanceSelector: $('#add-guests'),

                /* Container for list fragment
                 */
                attendanceList: $('#attendance-guests'),

                /* Element to show how many attendance places are available
                 */
                available: $('#attendance-guests-available'),

                /* Toaster element
                 */
                toast: $('[data-toast]')
            };

            return $.extend(defaults, options);
        }


        /**
         * Decide whether you can still add people
         *
         * @param available int
         */
        function toggleCreateAttendance(available) {
            if (available === null || available === undefined || available > 0) {
                config.createAttendanceForm.show();
            } else {
                config.createAttendanceForm.hide();
            }
        }


        /**
         * Occurs when you async add someone via the selectize input
         * Add one person at a time, spawn the toast, transact, then clear the selectize
         *
         * @param selectize
         */
        function setupOnAddAttendee(selectize) {
            selectize[0].selectize.on('item_add', function (value, item) {

                /* User's name
                 */
                let name = $(item[0]).html().split(' - ')[0];
                let id = value;

                /* A toast - to your valour, my sword, and our victory together
                 */
                config.toast.toastActivate({
                    text: 'Inviting ' + name + '...'
                });

                $.ajax({
                    url: config.createAttendanceForm.attr('action'),
                    method: 'post',
                    dataType: 'json',
                    data: {
                        guests: id
                    },
                    success: function(responseText) {
                        config.toast.toastTimeout({
                            text: name + ' has been successfully invited',
                            css: 'success'
                        });

                        selectize[0].selectize.clear();

                        /* Update pieces
                         */
                        onAfterAttendanceChange(responseText);
                    },
                    error: function(xhr, status, error) {
                        let responseText = $.parseJSON(xhr.responseText)

                        config.toast.toastTimeout({text: 'Unable to invite ' + name + '. ' + responseText.message, css: 'alert'});

                        selectize[0].selectize.clear();
                    }
                });
            });
        }


        /**
         * Asynchronously remove people's invites
         *
         * @param selectize
         */
        function setupOnRemoveAttendee(selectize) {
            config.attendanceList.on('click', '[data-guest-delete]', function(event) {
                event.preventDefault();

                let element = $(this);

                /* User's name
                 */
                let name = element.attr('data-guest-name');

                /* A toast - to your valour, my sword, and our victory together
                 */
                config.toast.toastActivate({
                    text: 'Uninviting ' + name + '...'
                });

                $.ajax({
                    url: element.attr('href'),
                    method: 'post',
                    dataType: 'json',
                    data: {guest: element.attr('data-guest-delete')},
                    success: function(responseText) {
                        config.toast.toastTimeout({
                            text: name + ' has been uninvited',
                            css: 'success'
                        });

                        /* Update pieces
                         */
                        onAfterAttendanceChange(responseText);
                    },
                    error: function(xhr, status, error) {
                        config.toast.toastTimeout({text: 'Unable to uninvite ' + name + '; please try again', css: 'alert'});
                    }
                });
            });
        }


        /**
         * Facade for what to do when the attendance list changes
         *
         * Update attendance list, counter and rsvp island
         *
         * @param responseText object A json response payload
         */
        function onAfterAttendanceChange(responseText) {
            config.attendanceList.find('article').html(responseText.fragment);
            config.available.html(responseText.attendees.available ? responseText.attendees.available : 'No');

            toggleCreateAttendance(responseText.attendees.available);

            $.fn.rsvpToggle({
                available: responseText.attendees.available,
                isInvited: config.attendanceList.find('[data-guest="' + config.currentUserId + '"]').length
            });
        }
    }


    /**
     * Conditionally show options
     *
     * Show rsvp island under any of the following:
     *  - You're on the invite list,
     *  - There's no guest limit
     *  - See if you can insert yourself into the list because there are available places
     *
     * Otherwise, there are no spaces remaining and you're not explicitly invited
     * You don't get to join the event
     */
    $.fn.rsvpToggle = function(options) {
        options = options || {};

        let config = init(options, $(this));

        const isInvited = config.isInvited;
        const noLimit = config.available === undefined || config.available === null;
        const hasAvailablePlaces = config.available > 0;

        if (isInvited || noLimit || hasAvailablePlaces) {
            config.rsvpAttendanceForm.show();
        } else {
            config.rsvpAttendanceForm.hide();
        }

        /* If you were on the list, but you removed yourself,
         * reset your button styles
         */
        if (!isInvited) {
            config.rsvpAttendanceForm.find('[data-rsvp]').removeClass();
        }

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            let defaults = {
                element: element,

                /* Form to present rsvp options
                 */
                rsvpAttendanceForm: $('#rsvpAttendanceForm'),

                /* Number of available places
                 */
                available: 0,

                /* Whether you're already invited
                 */
                isInvited: false
            };

            return $.extend(defaults, options);
        }
    }
}(jQuery));
