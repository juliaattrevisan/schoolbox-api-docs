$(function() {
    var $planDataContainer = $('#plan-data');
    var $participants = $('#participants');
    var $participantSettings = $('#participation-settings');
    var $customiseModal = $('#participant-customise-modal');


    // ----------------------------------------------------------------
    // Events
    // ----------------------------------------------------------------

    /**
     * Hiding and showing settings on feature enable
     */
    $('#participation-enable').change(function() {
        if ($(this).is(':checked')) {
            $participantSettings.removeClass('hide');
        } else {
            $participantSettings.addClass('hide');
        }
    });


    /**
     * Update action row on change of the participant checkboxes
     */
    $participants.on('change', 'input[data-participant-select]', function() {
        var checkCount = $participants.find(':checked').length;
        var $customiseDropdown = $('#customise-dropdown');
        var $participantRow = $(this).closest('li[data-participant]');

        if (checkCount) {
            // Enable actions
            $customiseDropdown.prop('disabled', false);
        } else {
            // Disable actions
            $customiseDropdown.prop('disabled', true);
        }

        // Highlight/unhighlight row
        if ($(this).is(':checked')) {
            $participantRow.addClass('participant-selected');
        } else {
            $participantRow.removeClass('participant-selected');
        }
    });


    /**
     * Opening the learning plan modal
     */
    $participantSettings.on('click', '#participant-customise', function(event) {
        event.preventDefault();

        var $planParticipants = $('#plan-participants');
        var $planName = $customiseModal.find('#plan-name');
        var $planDesc = $customiseModal.find('#plan-desc');
        var $planPublishDate = $customiseModal.find('#plan-publish-date');
        var $planOpenDate = $customiseModal.find('#plan-open-date');
        var $planDueDate = $customiseModal.find('#plan-due-date');
        var $planCloseDate = $customiseModal.find('#plan-close-date');

        // Init Selectize
        $planParticipants.selectizeSelector({
            multiple: true,
            ajax: {
                url:      '/ajax/user.php',
                type:     'GET',
                data:     {groupID: 0},
                dataType: 'json'
            }
        });
        var participantSelectize = $planParticipants[0].selectize;

        // Destroy and reinit CKEditor
        reinitCKE();

        // Disable dates if assessment doesn't accept online submissions
        if (!$('#acceptOnline').length || $('#acceptOnline').val() === '0') {
            $('#plan-open-date').closest('[data-plan-date-container]').addClass('hide');
            $('#plan-close-date').closest('[data-plan-date-container]').addClass('hide');
        } else {
            $('#plan-open-date').closest('[data-plan-date-container]').removeClass('hide');
            $('#plan-close-date').closest('[data-plan-date-container]').removeClass('hide');
        }

        // Fill the selectize with the checked students
        participantSelectize.clear();
        $participants.find(':checked').each(function() {
            participantSelectize.addOption({
                'id': $(this).attr('data-user-id'),
                'name': $(this).attr('data-user-name')
            });
            participantSelectize.addItem($(this).attr('data-user-id'));
        });

        // Clear inputs
        $planName.val('');

        // Clear any validation errors
        $('#plan-name-error').hide();
        $('#plan-publish-date-error').hide();
        $('#plan-open-date-error').hide();
        $('#plan-due-date-error').hide();
        $('#plan-close-date-error').hide();

        // Set dates & description to whatever is currently set on the main form
        $planPublishDate.val($('#publishDate').val());
        $planOpenDate.val($('#openDate').val());
        $planDueDate.val($('#dueDate').val());
        $planCloseDate.val($('#closeDate').val());
        CKEDITOR.instances['plan-desc'].setData(CKEDITOR.instances['assessment-description'].getData());

        // Open modal
        $customiseModal.foundation('reveal', 'open', {
            clearData: true
        });
    });


    /**
     * On open of the custom plan modal - destroy and re-init CKEditor
     * This was implemented because when you go into a datepicker, then return to the custom plan modal - ckeditor breaks
     */
    $customiseModal.on('open.fndtn.reveal', function () {
        reinitCKE();
    });


    /**
     *  Including/excluding students
     */
    $participantSettings.on('click', 'a[data-participant-change]', function(event) {
        event.preventDefault();

        var include = $(this).attr('data-participant-change') === 'include';

        // Reflect the change in the UI
        var $studentsSelected = $participants.find(':checked');
        $studentsSelected.each(function() {
            var $participantRow = $(this).closest('li');
            var $participantCard = $participantRow.find('div.card');
            var studentID = $participantRow.attr('data-participant');
            var status;

            if (include) {
                $participantCard.removeClass('participant-excluded');
                status = '(no customisations)';
            } else {
                $participantCard.addClass('participant-excluded');
                status = '(excluded)';
            }

            // Update the status showing for the student
            $participantRow.find('div[data-participant-status]').text(status);

            // Adjust the hidden value for include
            $('#participant-include-' + studentID).val(+include);

            // Reset plan back to 0
            $participantRow.find('#participant-plan-' + studentID).val(0);

        });

        // Reset the checkboxes and buttons
        resetActions();
    });


    // Validate plan form
    $('#participation-plan-form').alaressValidate({
        rules: {
            'plan-name': 'required',
            'plan-description': 'required',
            'plan-publish-date': {
                required: true,
                datepickerDate: true
            },
            'plan-open-date': {
                required: true,
                datepickerDate: true
            },
            'plan-due-date': {
                required: true,
                datepickerDate: true
            },
            'plan-close-date': {
                required: true,
                datepickerDate: true
            },
            'plan-participants': 'required'
        },
        ignore: ':hidden',

        // On valid - save the plan details in hidden inputs and apply to selected students
        submitHandler: function() {
            var $customiseModal = $('#participant-customise-modal');
            var planIndex = $planDataContainer.find('[data-saved-plan]').length + 1;
            var planName = $customiseModal.find('#plan-name').val();
            var planDesc = CKEDITOR.instances['plan-desc'].getData();
            var publishDate = $customiseModal.find('#plan-publish-date').val();
            var openDate = $customiseModal.find('#plan-open-date').val();
            var dueDate = $customiseModal.find('#plan-due-date').val();
            var closeDate = $customiseModal.find('#plan-close-date').val();

            // Save the plan in hidden inputs
            var hiddenInputs;

            hiddenInputs = $(document.createElement('input')).attr('type', 'hidden').attr('name', 'plans[' + planIndex + '][id]').val('');

            hiddenInputs = hiddenInputs.add($(document.createElement('input'))
                                                .attr('type', 'hidden')
                                                .attr('name', 'plans[' + planIndex + '][name]')
                                                .attr('data-saved-plan', 1)
                                                .val(planName));

            hiddenInputs = hiddenInputs.add($(document.createElement('input'))
                                                .attr('type', 'hidden')
                                                .attr('name', 'plans[' + planIndex + '][description]')
                                                .val(planDesc));

            hiddenInputs = hiddenInputs.add($(document.createElement('input'))
                                                .attr('type', 'hidden')
                                                .attr('name', 'plans[' + planIndex + '][publish_date]')
                                                .val(publishDate));

            hiddenInputs = hiddenInputs.add($(document.createElement('input'))
                                                .attr('type', 'hidden')
                                                .attr('name', 'plans[' + planIndex + '][open_date]')
                                                .val(openDate));

            hiddenInputs = hiddenInputs.add($(document.createElement('input'))
                                                .attr('type', 'hidden')
                                                .attr('name', 'plans[' + planIndex + '][due_date]')
                                                .val(dueDate));

            hiddenInputs = hiddenInputs.add($(document.createElement('input'))
                                                .attr('type', 'hidden')
                                                .attr('name', 'plans[' + planIndex + '][close_date]')
                                                .val(closeDate));

            // Append hidden inputs to the fieldset
            $planDataContainer.append(hiddenInputs);

            // Add plan to drop down
            $('#participant-customise')
                .closest('li')
                .before('<li><a href="#" data-plan-use="' + planIndex + '" data-plan-name="' + planName + '">' + planName + '</a></li>');

            // Update the selected students with the plan
            updatePlan($('#plan-participants')[0].selectize.getValue().split(','), planIndex, planName);

            // Close the modal
            $customiseModal.foundation('reveal', 'close');

            // Reset the checkboxes and buttons
            resetActions();

        }
    });


    /**
     * Clicking an existing plan on a selection of students
     */
    $participantSettings.on('click', 'a[data-plan-use]', function(event) {
        event.preventDefault();

        var planIndex = $(this).attr('data-plan-use');
        var planName = $(this).attr('data-plan-name');

        // Update the students with the plan
        var studentIDs = [];
        $participants.find(':checked').each(function() {
            studentIDs.push($(this).attr('data-user-id'));
        });
        updatePlan(studentIDs, planIndex, planName);

        // Reset the checkboxes and buttons
        resetActions();
    });


    // ----------------------------------------------------------------
    // Common functions
    // ----------------------------------------------------------------

    /**
     * Update the students selected
     */
    function updatePlan(students, planIndex, planName) {
        // Update the students with the plan
        $(students).each(function(index, studentID) {
            var $participantRow = $('li[data-participant=' + studentID + ']');
            var $participantCard = $participantRow.find('div.card');

            // Update the status showing for the student
            $participantRow.find('div[data-participant-status]').text(planName);

            // Make sure student is included now
            $participantRow.find('#participant-include-' + studentID).val(1);
            $participantCard.removeClass('participant-excluded');

            // Update the hidden input for their plan
            $participantRow.find('#participant-plan-' + studentID).val(planIndex);
        });
    }


    /**
     * Resets the checkboxes & disables the button
     */
    function resetActions() {
        $participants.find('input[type=checkbox]').prop('checked', false);
        $participants.find('li[data-participant]').removeClass('participant-selected');
    }


    /**
     * Destroy and reinit CKEditor
     */
    function reinitCKE() {
        var $planDesc = $customiseModal.find('#plan-desc');
        if (CKEDITOR.instances['plan-desc']) {
            CKEDITOR.instances['plan-desc'].destroy();
        }
        $.fn.alaressEditor({
            targetElements: $planDesc
        });
    }

});