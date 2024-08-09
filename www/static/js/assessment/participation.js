/**
 *
 * This is (an expanded form of) my template for a jQuery plugin.
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

(function ($, window, document, undefined) {
    var pluginName = "participationSettings";

    var defaults = {
        planData: "#plan-data",
        participants: "#participants",
        participantSettings: "#participation-settings",
        customiseModal: "#participant-customise-modal",
        getAvailableDateFields: function () {
            return ["publish", "open", "due", "close"];
        },
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
        $("#participation-enable").change(function () {
            if ($(this).is(":checked")) {
                $participantSettings.removeClass("hide");
            } else {
                $participantSettings.addClass("hide");
            }
        });

        /**
         * Display tot
         */
        enableActions();

        /**
         * Update action row on change of the participant checkboxes
         */
        $participants.on("change", "input[data-participant-select]", function () {
            var checkCount = $participants.find(":checked").length;
            var $customiseDropdown = $("#customise-dropdown");
            var $participantRow = $(this).closest("li[data-participant]");

            // Highlight/unhighlight row
            if ($(this).is(":checked")) {
                $participantRow.addClass("participant-selected");
            } else {
                $participantRow.removeClass("participant-selected");
            }

            displayParticipationOptions();

            if (checkCount) {
                // Enable actions
                $customiseDropdown.prop("disabled", false);
            } else {
                // Disable actions
                $customiseDropdown.prop("disabled", true);
            }
        });

        /**
         * Opening the learning plan modal to create a plan
         */
        $participantSettings.on("click", "#participant-customise", function (event) {
            event.preventDefault();
            openPlanModal(undefined, self.options);
            newPlan = true;
        });

        /**
         * Opening the learning plan modal to modify a plan
         */
        $participantSettings.on("click", "[data-plan-modify]", function (event) {
            event.preventDefault();

            var planId = $(this).attr("data-plan-modify");
            var planData = getPlanData(planId);
            newPlan = false;
            openPlanModal(planData, self.options);
        });

        /**
         *  Including/excluding students
         */
        $participantSettings.on("click", "a[data-participant-change]", function (event) {
            event.preventDefault();

            var action = $(this).attr("data-participant-change");

            // Reflect the change in the UI
            var $studentsSelected = $participants.find(":checked").closest("li");

            // If we're excluding students and any of them have interactions, warn the user
            if (action === "exclude" && anyHaveInteractions($studentsSelected)) {
                $(self).confirmDialog({
                    text:
                        '<p>The following student(s) have submissions and/or feedback for this assessment:</p><ul class="information-list">' +
                        getStudentListHTMLString($studentsSelected) +
                        "</ul><br/><p>Exclusion from this assessment will remove access to these submissions and/or feedback. Proceed?</p>",
                    format: "html",
                    confirmEvent: function () {
                        $studentsSelected.each(function () {
                            var studentID = $(this).attr("data-participant");
                            updateStudentStatus(studentID, false, self);
                        });
                        // Reset the checkboxes and buttons
                        resetActions();
                    },
                });
            } else {
                $studentsSelected.each(function () {
                    var $participantRow = $(this);
                    var studentID = $participantRow.attr("data-participant");
                    var isExcluded = $participantRow.find("div[data-participant-status = excluded]").length;

                    // Set student back to default if user pressed 'include' they were excluded, or if user pressed 'remove customisations'
                    if ((action === "include" && isExcluded) || action === "default") {
                        updateStudentStatus(studentID, true, self);

                        // Reset the checkboxes and buttons
                        resetActions();
                    } else if (action === "exclude") {
                        updateStudentStatus(studentID, false, self);
                        // Reset the checkboxes and buttons
                        resetActions();
                    }
                });
            }
        });

        /**
         * Return a boolean representing whether any of the students in the given list have feedbacks and/or responses.
         *
         * @param {Object}       $participants        jQuery object containing a list of participant DOM elements
         * @returns {boolean}
         */
        function anyHaveInteractions($participants) {
            var anyInteractions = false;
            $participants.each(function () {
                var $participantCard = $(this).find(".card");
                if ($participantCard.is("[data-has-interaction]")) {
                    anyInteractions = true;
                }
            });
            return anyInteractions;
        }

        /**
         * Hack together a list of students with interactions that can be rendered in the confirm dialog
         * @param {Object}      $participants       jQuery object containing a list of participant DOM elements
         * @returns {string}
         */
        function getStudentListHTMLString($participants) {
            var studentListHtmlString = "";

            $participants.each(function () {
                if ($(this).find(".card").is("[data-has-interaction]")) {
                    studentListHtmlString += "<li><div class='row'><div class='small-12 list-item compact card'>";
                    studentListHtmlString += $(this).find("div.card").html();
                    studentListHtmlString += "</div></div></li>";
                }
            });

            return studentListHtmlString;
        }

        // Validate plan form
        $("#participation-plan-form").alaressValidate({
            rules: {
                "plan-name": "requiredNotBlank",
                "plan-description": "required",
                planPublishDate: {
                    required: true,
                    datepickerDate: true,
                },
                planOpenDate: {
                    required: function () {
                        return $.inArray("open", options.getAvailableDateFields()) > -1;
                    },
                    datepickerDate: function () {
                        return $.inArray("open", options.getAvailableDateFields()) > -1;
                    },
                },
                planDueDate: {
                    required: true,
                    datepickerDate: true,
                },
                planCloseDate: {
                    required: function () {
                        return $.inArray("close", options.getAvailableDateFields()) > -1;
                    },
                    datepickerDate: function () {
                        return $.inArray("close", options.getAvailableDateFields()) > -1;
                    },
                },
                "plan-participants": {
                    required: function () {
                        return newPlan;
                    },
                },
            },

            // Ignore all hidden elements, except for the input hidden by selectize
            ignore: ":hidden:not(#plan-participants)",

            // On valid - save the plan details in hidden inputs and apply to selected students
            submitHandler: function () {
                // increment the number of "new plans" in play
                self.newPlanCount++;
                var oldPlanId = $customiseModal.find("#plan-id").val();
                var newPlanId = -1 * self.newPlanCount;

                var planDetails = {
                    id: newPlan ? newPlanId : oldPlanId,
                    name: $customiseModal.find("#plan-name").val(),
                    description: window.sbProps.editors["plan-desc"].getData(),
                    publishDate: $customiseModal.find("#planPublishDate").val(),
                    openDate: $customiseModal.find("#planOpenDate").val(),
                    dueDate: $customiseModal.find("#planDueDate").val(),
                    closeDate: $customiseModal.find("#planCloseDate").val(),
                };

                newPlan ? addPlanData(planDetails) : updatePlanData(planDetails);

                if (newPlan) {
                    $("#participant-customise").closest("li").before(createPlanListItem(planDetails, false));

                    $("#plan-options").append(createPlanListItem(planDetails, true));

                    enableActions();
                } else {
                    // Update plan in drop downs
                    $('[data-plan-modify="' + planDetails["id"] + '"]')
                        .attr("data-plan-name", planDetails["name"])
                        .text(planDetails["name"]);
                    $('[data-plan-use="' + planDetails["id"] + '"]')
                        .attr("data-plan-name", planDetails["name"])
                        .text(planDetails["name"]);
                }
                var studentIDs = [];

                if ($("#plan-participants")[0].selectize.getValue().length > 0) {
                    studentIDs = $("#plan-participants")[0].selectize.getValue().split(",");
                }

                // Update the selected students with the plan
                updateStudentPlans(studentIDs, planDetails["id"], planDetails["name"], newPlan);

                // Close the modal
                $customiseModal.foundation("reveal", "close");

                // Reset the checkboxes and buttons
                if (newPlan) {
                    resetActions();
                }
            },
        });

        /**
         * Clicking an existing plan on a selection of students
         */
        $participantSettings.on("click", "a[data-plan-use]", function (event) {
            event.preventDefault();

            var planId = $(this).attr("data-plan-use");
            var planName = $(this).attr("data-plan-name");

            // Update the students with the plan
            var studentIDs = [];
            $participants.find(":checked").each(function () {
                studentIDs.push($(this).attr("data-user-id"));
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
            var $existingStudents = $("li[data-participant]").find("input[value = " + planId + "]");
            $existingStudents.each(function () {
                // Update the status showing for the student
                $(this)
                    .siblings("div[data-participant-row]")
                    .find("div[data-participant-status]")
                    .attr("data-participant-status", "included")
                    .text("(no customisations)");

                // Update the hidden input for their plan
                $(this).val(0);
            });
        }

        if (students.length > 0 && students !== undefined) {
            // Update the students with the plan
            $(students).each(function (index, studentID) {
                var $participantRow = $("li[data-participant=" + studentID + "]");
                var $participantCard = $participantRow.find("div.card");

                // Update the status display for the student
                $participantRow
                    .find("div[data-participant-status]")
                    .attr("data-participant-status", "custom")
                    .text(planName);

                // Make sure student is included now
                $participantRow.find("#participant-include-" + studentID).val(1);
                $participantCard.removeClass("participant-excluded");

                // Update the hidden input for their plan
                $participantRow.find("#participant-plan-" + studentID).val(planId);
            });
        }
    }

    /**
     * Update a single student row to included with no customisations, or to excluded
     *
     * @param {int}         studentID             ID of student to update
     * @param {boolean}     include               Whether to set to default, or set to excluded
     * @param {Object}      self
     */
    function updateStudentStatus(studentID, include) {
        var statusValue;
        var status;
        var $participantRow = $(defaults.participants).find("li[data-participant=" + studentID + "]");
        var $participantCard = $participantRow.find("div.card");

        if (include) {
            $participantCard.removeClass("participant-excluded");
            status = "(no customisations)";
            statusValue = "included";
        } else {
            $participantCard.addClass("participant-excluded");
            status = "(excluded)";
            statusValue = "excluded";
        }

        $participantRow.find("div[data-participant-status]").attr("data-participant-status", statusValue).text(status);

        // Adjust the hidden value for include
        $("#participant-include-" + studentID).val(include ? 1 : 0);

        // Reset plan back to 0
        $participantRow.find("#participant-plan-" + studentID).val(0);
    }

    /**
     * Resets the checkboxes & disables the actions button
     */
    function resetActions() {
        var $participants = $(defaults.participants);
        var $customiseDropdown = $("#customise-dropdown");

        $participants.find("input[type=checkbox]").prop("checked", false);
        $participants.find("li[data-participant]").removeClass("participant-selected");

        $customiseDropdown.prop("disabled", true);
    }

    /**
     * Initialise CKEditor
     */
    async function initCKE() {
        return new Promise((resolve) => {
            if (window.sbProps.editors["plan-desc"]) {
                return resolve();
            }
            document.getElementById("plan-desc").addEventListener("EDITOR__CREATED", function () {
                resolve();
            });
            document.dispatchEvent(
                new CustomEvent("EDITOR__CREATE", {
                    detail: {
                        targetElements: ["#plan-desc"],
                    },
                }),
            );
        });
    }

    /**
     * Add new plan data to hidden inputs
     */
    function addPlanData(planDetails) {
        // Save the plan in hidden inputs
        var $hiddenInputs;
        var planId = planDetails["id"];

        $hiddenInputs = $(document.createElement("input"))
            .attr("type", "hidden")
            .attr("name", "plans[" + planId + "][id]")
            .val(planId);

        $hiddenInputs = $hiddenInputs.add(
            $(document.createElement("input"))
                .attr("type", "hidden")
                .attr("name", "plans[" + planId + "][name]")
                .attr("data-saved-plan", 1)
                .val(planDetails["name"]),
        );

        $hiddenInputs = $hiddenInputs.add(
            $(document.createElement("input"))
                .attr("type", "hidden")
                .attr("name", "plans[" + planId + "][description]")
                .val(planDetails["description"]),
        );

        $hiddenInputs = $hiddenInputs.add(
            $(document.createElement("input"))
                .attr("type", "hidden")
                .attr("name", "plans[" + planId + "][publish_date]")
                .val(planDetails["publishDate"]),
        );

        $hiddenInputs = $hiddenInputs.add(
            $(document.createElement("input"))
                .attr("type", "hidden")
                .attr("name", "plans[" + planId + "][open_date]")
                .val(planDetails["openDate"]),
        );

        $hiddenInputs = $hiddenInputs.add(
            $(document.createElement("input"))
                .attr("type", "hidden")
                .attr("name", "plans[" + planId + "][due_date]")
                .val(planDetails["dueDate"]),
        );

        $hiddenInputs = $hiddenInputs.add(
            $(document.createElement("input"))
                .attr("type", "hidden")
                .attr("name", "plans[" + planId + "][close_date]")
                .val(planDetails["closeDate"]),
        );

        // Append hidden inputs to the fieldset
        $(defaults.planData).append($hiddenInputs);
    }

    /**
     * Update plan data in the hidden inputs
     */
    function updatePlanData(planDetails) {
        var planId = planDetails["id"];
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
            id: $planData.find($("input[name*='plans[" + planId + "][id]']")),
            name: $planData.find($("input[name*='plans[" + planId + "][name]']")),
            description: $planData.find($("input[name*='plans[" + planId + "][description]']")),
            publishDate: $planData.find($("input[name*='plans[" + planId + "][publish_date]']")),
            openDate: $planData.find($("input[name*='plans[" + planId + "][open_date]']")),
            dueDate: $planData.find($("input[name*='plans[" + planId + "][due_date]']")),
            closeDate: $planData.find($("input[name*='plans[" + planId + "][close_date]']")),
        };
        return planDataFileds;
    }

    /**
     * Create a link element for a newly created plan
     */
    function createPlanListItem(planDetails, update) {
        var attrName = update ? "data-plan-modify" : "data-plan-use";
        var $planLink = $(document.createElement("a"))
            .attr("href", "#")
            .attr(attrName, planDetails["id"])
            .attr("data-plan-name", planDetails["name"])
            .text(planDetails["name"]);

        return $(document.createElement("li")).append($planLink);
    }

    /**
     * Check if there are plans and enable actions
     */
    function enableActions() {
        var checkPlans = $(defaults.planData).find("input").length;
        if (checkPlans) {
            $("#plan-dropdown").removeClass("hide");
        }
    }

    async function openPlanModal(planData, options) {
        var $planParticipants = $("#plan-participants");
        var $customiseModal = $(defaults.customiseModal);
        var $participants = $(defaults.participants);
        var $modalHeader = $customiseModal.find("[data-plan-header]");
        var $planId = $customiseModal.find("#plan-id");
        var $planName = $customiseModal.find("#plan-name");
        var $planDesc = $customiseModal.find("#plan-desc");
        var $planPublishDate = $customiseModal.find("#planPublishDate");
        var $planOpenDate = $customiseModal.find("#planOpenDate");
        var $planDueDate = $customiseModal.find("#planDueDate");
        var $planCloseDate = $customiseModal.find("#planCloseDate");

        var participantArray = $(defaults.participants)
            .find("[data-participant-select]")
            .map(function () {
                var obj = { id: $(this).data("userId"), name: $(this).data("userName") };
                return obj;
            });

        // Init Selectize
        $planParticipants.selectize({
            delimiter: ",",
            options: participantArray,
            dropdownParent: "body",
            plugins: ["remove_button"],
            create: false,
            hideSelected: true,
            createOnBlur: true,
            persist: false,
            searchField: "name",
            labelField: "name",
            valueField: "id",
        });
        var participantSelectize = $planParticipants[0].selectize;

        // Wait for CKE to init to ensure we can set its value correctly below
        await initCKE();

        // Fill the selectize with the checked students
        participantSelectize.clear();
        if (planData !== undefined) {
            $participants.find("input[value = " + planData["id"] + "]").each(function () {
                var participantId = $(this).parent().attr("data-participant");
                var participantName = $("#participant-" + participantId).attr("data-user-name");
                participantSelectize.addOption({
                    id: participantId,
                    name: participantName,
                });
                participantSelectize.addItem(participantId);
            });
        } else {
            $participants.find(":checked").each(function () {
                participantSelectize.addOption({
                    id: $(this).attr("data-user-id"),
                    name: $(this).attr("data-user-name"),
                });
                participantSelectize.addItem($(this).attr("data-user-id"));
            });

            // Clear inputs
            $planName.val("");
        }

        // Clear any validation errors
        $("#plan-name-error").hide();
        $("#planPublishDate-error").hide();
        $("#planOpenDate-error").hide();
        $("#planDueDate-error").hide();
        $("#planCloseDate-error").hide();
        $("#plan-participants-error").hide();

        // Set dates & description to whatever is currently set on the main form
        if (planData !== undefined) {
            $modalHeader.text("Modify Learning Plan");
            $planId.val(planData["id"]);
            $planName.val(planData["name"]);
            $planPublishDate.val(planData["publishDate"]);
            $planOpenDate.val(planData["openDate"]);
            $planDueDate.val(planData["dueDate"]);
            $planCloseDate.val(planData["closeDate"]);
            window.sbProps.editors["plan-desc"].setData(planData["description"]);
        } else {
            $modalHeader.text("Create New Learning Plan");
            $planId.val(0);
            $planPublishDate.val($("#publishDate").val());
            $planOpenDate.val($("#openDate").val());
            $planDueDate.val($("#dueDate").val());
            $planCloseDate.val($("#closeDate").val());
            window.sbProps.editors["plan-desc"].setData(window.sbProps.editors["assessment-description"].getData());
        }

        var $participantCustomiseModal = $("#participant-customise-modal");

        // Open modal
        $customiseModal.foundation("reveal", "open", {
            clearData: false,
            multiple_opened: true,
            close_on_background_click: false,
            opened: function () {
                if ($participantCustomiseModal.hasClass("toback")) {
                    // so the participant customise modal and link balloon is behind the modal bkg
                    $participantCustomiseModal.css("z-index", "calc(var(--sbx-depth-modal) - 3)");
                    updateLinkBalloonDepth("calc(var(--sbx-depth-modal) - 2)");
                } else {
                    $participantCustomiseModal.css("z-index", "var(--sbx-depth-modal)");
                }
            },
            closed: function () {
                $participantCustomiseModal.attr("modal-status", "closed");
                // so the participant customise modal and link balloon is back in front of the modal bkg
                $participantCustomiseModal.css("z-index", "var(--sbx-depth-modal)");
                updateLinkBalloonDepth("calc(var(--sbx-depth-modal) + 1)");
            },
        });
    }

    /**
     * When a resource modal is opened, we need to ensure the
     * link balloon is also updated so it sits behind  the resource modal
     * We target the specific link balloon by its editor id as there
     * can be multiple editors on a page
     */
    function updateLinkBalloonDepth(depth) {
        const linkBalloon = document.querySelector(
            `[data-balloon-editor-id="${window.sbProps.editors["plan-desc"].id}"]`,
        );
        if (linkBalloon) {
            linkBalloon.style.zIndex = depth;
        }
    }

    /**
     * Control action display under the "Modify Participation" drop down
     */
    function displayParticipationOptions() {
        var $participants = $(defaults.participants);
        var excluded = $participants.find(":checked").closest("li").find("[data-participant-status = excluded]").length;
        var included = $participants.find(":checked").closest("li").find("[data-participant-status = included]").length;
        var custom = $participants.find(":checked").closest("li").find("[data-participant-status = custom]").length;

        // Show exclude action if at least one selected student is included or has a custom plan
        if (included || custom) {
            $("[data-participant-change = exclude]").removeClass("hide");
        } else {
            $("[data-participant-change = exclude]").addClass("hide");
        }

        // Show include action if at least one selected student is excluded
        if (excluded) {
            $("[data-participant-change = include]").removeClass("hide");
        } else {
            $("[data-participant-change = include]").addClass("hide");
        }

        // Show 'remove customisations' action if at least one selected student is excluded or has a custom plan
        if (excluded || custom) {
            $("[data-participant-change = default]").removeClass("hide");
        } else {
            $("[data-participant-change = default]").addClass("hide");
        }
    }

    /**
     * The hook into jQuery's plugin infrastructure.
     *
     * @param {Object} options
     */
    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" + pluginName, new ParticipationSettings(this, options));
            }
        });
    };
})(jQuery, window, document);
