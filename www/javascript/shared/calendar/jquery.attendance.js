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
         * Facade for what to do when the attendance list changes
         *
         * Update attendance list, counter and rsvp island
         *
         * @param responseText object A json response payload
         */
        function onAfterAttendanceChange(responseText) {
            config.attendanceList.find('article').html(responseText.fragment);

            toggleCreateAttendance(responseText.attendees.available);
        }
    }
}(jQuery));
