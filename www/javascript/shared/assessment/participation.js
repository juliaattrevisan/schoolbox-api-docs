/**
 *
 * This is (an expanded form of) my template for a jQuery plugin.
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

;(function ($, window, document, undefined) {

    var pluginName = 'participationSettings';

    var defaults = {
        planData: '#plan-data',
        participants: '#participants',
        participantSettings: '#participation-settings',
        customiseModal: '#participant-customise-modal'
    };

    /**
     * Constructor
     *
     * @param {jQuery} element
     * @param {Object} options
     */
    function ParticipationSettings(element, options) {
        var self = this;
        this.element = element;
        this.options = $.extend(true, {}, defaults, options);
        this.newPlanCount = 0;

        var $participants = $(defaults.participants);
        var $participantSettings = $(defaults.participantSettings);
        var $customiseModal = $(defaults.customiseModal);

        var newPlan = true;

        // ----------------------------------------------------------------
        // Events
        // ----------------------------------------------------------------
        /**
         * Hiding and showing settings on feature enable
         */
        $('#participation-enable').change(function () {
            if ($(this).is(':checked')) {
                $participantSettings.removeClass('hide');
            } else {
                $participantSettings.addClass('hide');
            }
        });

        /**
         * Display tot
         */
        enableActions();

        /**
         * Update action row on change of the participant checkboxes
         */
        $participants.on('change', 'input[data-participant-select]', function () {
            var checkCount = $participants.find(':checked').length;
            var $customiseDropdown = $('#customise-dropdown');
            var $participantRow = $(this).closest('li[data-participant]');

            // Highlight/unhighlight row
            if ($(this).is(':checked')) {
                $participantRow.addClass('participant-selected');
            } else {
                $participantRow.removeClass('participant-selected');
            }

            displayParticipationOptions();

            if (checkCount) {
                // Enable actions
                $customiseDropdown.prop('disabled', false);
            } else {
                // Disable actions
                $customiseDropdown.prop('disabled', true);
            }

        });


        /**
         * Opening the learning plan modal to create a plan
         */
        $participantSettings.on('click', '#participant-customise', function (event) {
            event.preventDefault();
            openPlanModal();
            newPlan = true;
        });

        /**
         * Opening the learning plan modal to modify a plan
         */
        $participantSettings.on('click', '[data-plan-modify]', function (event) {
            event.preventDefault();

            var planId = $(this).attr('data-plan-modify');
            var planData = getPlanData(planId);
            newPlan = false;
            openPlanModal(planData);
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
        $participantSettings.on('click', 'a[data-participant-change]', function (event) {
            event.preventDefault();

            var action = $(this).attr('data-participant-change');

            // Reflect the change in the UI
            var $studentsSelected = $participants.find(':checked');
            $studentsSelected.each(function () {
                var $participantRow = $(this).closest('li');
                var studentID = $participantRow.attr('data-participant');
                var isExcluded = $participantRow.find('div[data-participant-status = excluded]').length;

                // Set student back to default if user pressed 'include' they were excluded, or if user pressed 'remove customisations'
                if ((action === 'include' && isExcluded) || action === 'default') {
                    updateStudentStatus(studentID, true);
                } else if (action === 'exclude') {
                    updateStudentStatus(studentID, false);
                }
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
                'plan-participants': {
                    required: function () {
                        return newPlan;
                    }
                }
            },

            // Ignore all hidden elements, except for the input hidden by selectize
            ignore: ':hidden:not(#plan-participants)',

            // On valid - save the plan details in hidden inputs and apply to selected students
            submitHandler: function () {

                // increment the number of "new plans" in play
                self.newPlanCount++;
                var oldPlanId = $customiseModal.find('#plan-id').val();
                var newPlanId = -1 * self.newPlanCount;

                var planDetails = {
                    'id': newPlan ? newPlanId : oldPlanId,
                    'name': $customiseModal.find('#plan-name').val(),
                    'description': CKEDITOR.instances['plan-desc'].getData(),
                    'publishDate': $customiseModal.find('#plan-publish-date').val(),
                    'openDate': $customiseModal.find('#plan-open-date').val(),
                    'dueDate': $customiseModal.find('#plan-due-date').val(),
                    'closeDate': $customiseModal.find('#plan-close-date').val()
                };

                newPlan ? addPlanData(planDetails) : updatePlanData(planDetails);

                if (newPlan) {
                    $('#participant-customise')
                        .closest('li')
                        .before(createPlanListItem(planDetails, false));

                    $('#plan-options')
                        .append(createPlanListItem(planDetails, true));

                    enableActions();
                }
                else {
                    // Update plan in drop downs
                    $('[data-plan-modify="' + planDetails['id'] + '"]').attr('data-plan-name', planDetails['name']).text(planDetails['name']);
                    $('[data-plan-use="' + planDetails['id'] + '"]').attr('data-plan-name', planDetails['name']).text(planDetails['name']);
                }
                var studentIDs = [];

                if ($('#plan-participants')[0].selectize.getValue().length > 0) {
                    studentIDs = $('#plan-participants')[0].selectize.getValue().split(',');
                }

                // Update the selected students with the plan
                updateStudentPlans(studentIDs, planDetails['id'], planDetails['name'], newPlan);

                // Close the modal
                $customiseModal.foundation('reveal', 'close');

                // Reset the checkboxes and buttons
                if (newPlan) {
                    resetActions();
                }

            }
        });

        /**
         * Clicking an existing plan on a selection of students
         */
        $participantSettings.on('click', 'a[data-plan-use]', function (event) {
            event.preventDefault();

            var planId = $(this).attr('data-plan-use');
            var planName = $(this).attr('data-plan-name');

            // Update the students with the plan
            var studentIDs = [];
            $participants.find(':checked').each(function () {
                studentIDs.push($(this).attr('data-user-id'));
            });
            updateStudentPlans(studentIDs, planId, planName, true);

            // Reset the checkboxes and buttons
            resetActions();
        });

    }


    // ----------------------------------------------------------------
    // Common functions
    // ----------------------------------------------------------------

    /**
     * Update an array of students to a specified plan
     *
     * @param {Array}       students        The students to update
     * @param {int}         planId          The ID of the plan to use
     * @param {string}      planName        The name of the plan
     * @param {boolean}     newPlan         Whether the plan is new or not
     */
    function updateStudentPlans(students, planId, planName, newPlan) {

        // If updating an existing plan
        if (newPlan === false) {
            // Clear this plan from all the students it was previously applied to
            var $existingStudents = $('li[data-participant]').find('input[value = ' + planId + ']');
            $existingStudents.each(function () {

                // Update the status showing for the student
                $(this)
                    .siblings('div[data-participant-row]')
                    .find('div[data-participant-status]')
                    .attr('data-participant-status', 'included')
                    .text('(no customisations)');

                // Update the hidden input for their plan
                $(this).val(0);
            });
        }

        if (students.length > 0 && students !== undefined) {
            // Update the students with the plan
            $(students).each(function (index, studentID) {
                var $participantRow = $('li[data-participant=' + studentID + ']');
                var $participantCard = $participantRow.find('div.card');

            // Update the status display for the student
            $participantRow
                .find('div[data-participant-status]')
                .attr('data-participant-status', 'custom')
                .text(planName);

                // Make sure student is included now
                $participantRow.find('#participant-include-' + studentID).val(1);
                $participantCard.removeClass('participant-excluded');

                // Update the hidden input for their plan
                $participantRow.find('#participant-plan-' + studentID).val(planId);
            });
        }
    }


    /**
     * Update a single student row to included with no customisations, or to excluded
     *
     * @param {int}         studentID             ID of student to update
     * @param {boolean}     include               Whether to set to default, or set to excluded
     */
    function updateStudentStatus(studentID, include) {
        var statusValue;
        var status;
        var $participantRow = $(defaults.participants).find('li[data-participant=' + studentID + ']');
        var $participantCard = $participantRow.find('div.card');

        if (include) {
            $participantCard.removeClass('participant-excluded');
            status = '(no customisations)';
            statusValue = 'included';
        } else {
            $participantCard.addClass('participant-excluded');
            status = '(excluded)';
            statusValue = 'excluded';
        }

        // Update the status showing for the student
        $participantRow
            .find('div[data-participant-status]')
            .attr('data-participant-status', statusValue)
            .text(status);

        // Adjust the hidden value for include
        $('#participant-include-' + studentID).val(include ? 1 : 0);

        // Reset plan back to 0
        $participantRow.find('#participant-plan-' + studentID).val(0);
    }


    /**
     * Resets the checkboxes & disables the actions button
     */
    function resetActions() {
        var $participants = $(defaults.participants);
        var $customiseDropdown = $('#customise-dropdown');

        $participants.find('input[type=checkbox]').prop('checked', false);
        $participants.find('li[data-participant]').removeClass('participant-selected');

        $customiseDropdown.prop('disabled', true);
    }


    /**
     * Destroy and reinit CKEditor
     */
    function reinitCKE() {
        var $planDesc = $(defaults.customiseModal).find('#plan-desc');
        if (CKEDITOR.instances['plan-desc']) {
            CKEDITOR.instances['plan-desc'].destroy();
        }
        $.fn.alaressEditor({
            targetElements: $planDesc
        });
    }

    /**
     * Add new plan data to hidden inputs
     */
    function addPlanData(planDetails) {

        // Save the plan in hidden inputs
        var $hiddenInputs;
        var planId = planDetails['id'];

        $hiddenInputs = $(document.createElement('input')).attr('type', 'hidden').attr('name', 'plans[' + planId + '][id]').val(planId);

        $hiddenInputs = $hiddenInputs.add($(document.createElement('input'))
            .attr('type', 'hidden')
            .attr('name', 'plans[' + planId + '][name]')
            .attr('data-saved-plan', 1)
            .val(planDetails['name']));

        $hiddenInputs = $hiddenInputs.add($(document.createElement('input'))
            .attr('type', 'hidden')
            .attr('name', 'plans[' + planId + '][description]')
            .val(planDetails['description']));

        $hiddenInputs = $hiddenInputs.add($(document.createElement('input'))
            .attr('type', 'hidden')
            .attr('name', 'plans[' + planId + '][publish_date]')
            .val(planDetails['publishDate']));

        $hiddenInputs = $hiddenInputs.add($(document.createElement('input'))
            .attr('type', 'hidden')
            .attr('name', 'plans[' + planId + '][open_date]')
            .val(planDetails['openDate']));

        $hiddenInputs = $hiddenInputs.add($(document.createElement('input'))
            .attr('type', 'hidden')
            .attr('name', 'plans[' + planId + '][due_date]')
            .val(planDetails['dueDate']));

        $hiddenInputs = $hiddenInputs.add($(document.createElement('input'))
            .attr('type', 'hidden')
            .attr('name', 'plans[' + planId + '][close_date]')
            .val(planDetails['closeDate']));

        // Append hidden inputs to the fieldset
        $(defaults.planData).append($hiddenInputs);
    }

    /**
     * Update plan data in the hidden inputs
     */
    function updatePlanData(planDetails) {

        var planId = planDetails['id'];
        var $hiddenInputs = getPlanDataFields(planId);

        // Update hidden fields with new values
        for (var key in $hiddenInputs) {
            $hiddenInputs[key].val(planDetails[key]);
        }

    }

    /**
     * Get plan data from hidden inputs
     */
    function getPlanData(planId) {

        var planData = [];
        var planDataFields = getPlanDataFields(planId);

        for (var key in planDataFields) {
            planData[key] = planDataFields[key].val();
        }

        return planData;
    }

    /**
     * Get hidden input fields for plan
     */
    function getPlanDataFields(planId) {
        var $planData = $(defaults.planData);
        var planDataFileds = {
            'id': $planData.find($("input[name*='plans[" + planId + "][id]']")),
            'name': $planData.find($("input[name*='plans[" + planId + "][name]']")),
            'description': $planData.find($("input[name*='plans[" + planId + "][description]']")),
            'publishDate': $planData.find($("input[name*='plans[" + planId + "][publish_date]']")),
            'openDate': $planData.find($("input[name*='plans[" + planId + "][open_date]']")),
            'dueDate': $planData.find($("input[name*='plans[" + planId + "][due_date]']")),
            'closeDate': $planData.find($("input[name*='plans[" + planId + "][close_date]']"))
        };
        return planDataFileds;
    }

    /**
     * Create a link element for a newly created plan
     */
    function createPlanListItem(planDetails, update) {
        var attrName = update ? 'data-plan-modify' : 'data-plan-use';
        var $planLink = $(document.createElement('a'))
            .attr('href', '#')
            .attr(attrName, planDetails['id'])
            .attr('data-plan-name', planDetails['name'])
            .text(planDetails['name']);

        return $(document.createElement('li')).append($planLink);
    }

    /**
     * Check if there are plans and enable actions
     */
    function enableActions() {
        var checkPlans = $(defaults.planData).find('input').length;
        if (checkPlans) {
            $('#plan-dropdown').closest('button').removeClass('hide');
        }
    }

    function openPlanModal(planData) {

        var $planParticipants = $('#plan-participants');
        var $customiseModal = $(defaults.customiseModal);
        var $participants = $(defaults.participants);
        var $modalHeader = $customiseModal.find('[data-plan-header]');
        var $planId = $customiseModal.find('#plan-id');
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
                url: '/ajax/user.php',
                type: 'GET',
                data: {groupID: 0},
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
        if (planData !== undefined) {
            $participants.find('input[value = ' + planData['id'] + ']').each(function () {
                var participantId = $(this).parent().attr('data-participant');
                var participantName = $('#participant-' + participantId).attr('data-user-name');
                participantSelectize.addOption({
                    'id': participantId,
                    'name': participantName
                });
                participantSelectize.addItem(participantId);

            });
        }
        else {
            $participants.find(':checked').each(function () {
                participantSelectize.addOption({
                    'id': $(this).attr('data-user-id'),
                    'name': $(this).attr('data-user-name')
                });
                participantSelectize.addItem($(this).attr('data-user-id'));
            });

            // Clear inputs
            $planName.val('');
        }

        // Clear any validation errors
        $('#plan-name-error').hide();
        $('#plan-publish-date-error').hide();
        $('#plan-open-date-error').hide();
        $('#plan-due-date-error').hide();
        $('#plan-close-date-error').hide();
        $('#plan-participants-error').hide();

        // Set dates & description to whatever is currently set on the main form
        if (planData !== undefined) {
            $modalHeader.text('Modify Learning Plan');
            $planId.val(planData['id']);
            $planName.val(planData['name']);
            $planPublishDate.val(planData['publishDate']);
            $planOpenDate.val(planData['openDate']);
            $planDueDate.val(planData['dueDate']);
            $planCloseDate.val(planData['closeDate']);
            CKEDITOR.instances['plan-desc'].setData(planData['description']);
        }
        else {
            $modalHeader.text('Create New Learning Plan');
            $planId.val(0);
            $planPublishDate.val($('#publishDate').val());
            $planOpenDate.val($('#openDate').val());
            $planDueDate.val($('#dueDate').val());
            $planCloseDate.val($('#closeDate').val());
            CKEDITOR.instances['plan-desc'].setData(CKEDITOR.instances['assessment-description'].getData());
        }

        // Open modal
        $customiseModal.foundation('reveal', 'open', {
            clearData: true
        });
    }

    /**
     * Control action display under the "Modify Participation" drop down
     */
    function displayParticipationOptions() {
        var $participants = $(defaults.participants);
        var excluded = $participants.find(':checked').closest('li').find('[data-participant-status = excluded]').length;
        var included = $participants.find(':checked').closest('li').find('[data-participant-status = included]').length;
        var custom = $participants.find(':checked').closest('li').find('[data-participant-status = custom]').length;

        // Show exclude action if at least one selected student is included or has a custom plan
        if (included || custom) {
            $('[data-participant-change = exclude]').removeClass('hide');
        } else {
            $('[data-participant-change = exclude]').addClass('hide');
        }

        // Show include action if at least one selected student is excluded
        if (excluded) {
            $('[data-participant-change = include]').removeClass('hide');
        } else {
            $('[data-participant-change = include]').addClass('hide');
        }

        // Show 'remove customisations' action if at least one selected student is excluded or has a custom plan
        if (excluded || custom) {
            $('[data-participant-change = default]').removeClass('hide');
        } else {
            $('[data-participant-change = default]').addClass('hide');
        }

    }


    /**
     * The hook into jQuery's plugin infrastructure.
     *
     * @param {Object} options
     */
    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new ParticipationSettings(this, options));
            }
        });
    };

})(jQuery, window, document);
