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

        /* Initial conditions
         */
        toggleRSVP(config.rsvpAttendanceForm.attr('data-guests-available'));

        /* Set up selectizes if you have perms
         */
        if (config.canModify) {
            const selectize = config.createAttendanceSelector.selectizeSelector({
                ajax: {
                    url: '/ajax/user.php',
                    type: 'get',
                    data: {groupID: 0, showCampus: true},
                    dataType: 'json'
                },
                multiple: false
            });

            setupOnAddAttendee(selectize);
            setupOnRemoveAttendee(selectize);
        } else {
            config.createAttendanceSelector.remove();
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

                /* Your user id
                 */
                currentUserId: 0,

                /* Access perms for hiding/showing form elements
                 */
                canModify: false,

                /* Add a person
                 */
                createAttendanceForm: $('#createAttendanceForm'),

                /* Selectize for adding a person
                 */
                createAttendanceSelector: $('#add-guests'),

                /* Form to present rsvp options
                 */
                rsvpAttendanceForm: $('#rsvpAttendanceForm'),

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
         * Conditionally show options
         *
         * If you're on the invite list, always show rsvp island
         * Else, see if you can insert yourself into the list
         * Otherwise, there are no spaces remaining and you're not explicitly invited
         *
         * @param available int
         */
        function toggleRSVP(available) {
            if (config.attendanceList.find('[data-guest="'+config.currentUserId+'"]').length) {
                config.rsvpAttendanceForm.show();
            } else if (available > 0) {
                config.rsvpAttendanceForm.show();
            } else {
                config.rsvpAttendanceForm.hide();
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
            config.attendanceList.find('ul').replaceWith(responseText.fragment);
            config.available.html(responseText.available ? responseText.available : 'No');

            toggleRSVP(responseText.available);
        }
    }
}(jQuery));
