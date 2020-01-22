/**
 * This javascript is used on Quickmark and Inline Grader
 * to power the feedback forms, save drafts etc.
 *
 * Usage:

 $.fn.dynamicFeedback({
    statusIncomplete: {{ statusIncomplete }},
    statusPending: {{ statusPending }},
    statusReviewed: {{ statusReviewed }}
    ...
    ...

 });

 */

(function($) {
    /**
     * Is a draft currently being auto-saved for a student?
     *
     * @type {boolean[]} Indexed by user.id
     */
    var autoSaveInProgress = [];

    /**
     * Has the instructor made more changes during a draft auto-save?
     *
     * @type {boolean[]} Indexed by user.id
     */
    var autoSavePending = [];

    /**
     * Timeout IDs for each comment box, indexed by ID of the comment box.
     * Used to accumulate and clear typing timeouts for each textarea individually.
     *
     * @type {number[]}
     */
    var commentBoxTimeouts = [];


    /**
     * Plugin
     *
     * @param options
     */
    $.fn.dynamicFeedback = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupPreValues();
        setupListeners();
        setupPublishAction();
        disableFormSubmit();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,
                timeoutDurationMs: 600, // After how many milliseconds should we auto-save user input

                /* Label to indicate current status of action being undertaken
                 */
                formSaveLabel: $('[data-save-draft]'),

                /* A list of all the people who have publishable rows
                 */
                assessmentList: $('#assessment-status'),

                /* Buttons which control which rows are visible
                 */
                filters: $('#filters'),

                /* CSS classes representing incomplete/pending/reviewed states
                 */
                statusIncomplete: 'incomplete',
                statusPending: 'pending',
                statusReviewed: 'reviewed',
                statusResubmissionRequired: 'failed',

                /* - The text to display on the publish button
                 * - When publishing is taking place
                 * - When publishing has successfully finished
                 */
                publishText: 'Publish',
                publishingText: 'Publishing...',
                publishedText: 'Feedback published',

                reviewedText: 'Reviewed',
                resubmissionRequiredText: 'Resubmission Required',

                // Callbacks to execute upon initiation and successful completion of Draft and Publish actions
                onPreDraftCallback: $.noop,
                onDraftCallback: $.noop,
                onPrePublishCallback: $.noop,
                onPublishCallback: $.noop
            };

            return $.extend(defaults, options);
        }

        /**
         * Set up pre-values
         */
        function setupPreValues() {
            // setup pre-values
            $(config.assessmentList).find(':input').each(function () {
                // stash the current state, so we can compare on blur and prevent unnecessary firing
                $(this).data('preval', $(this).val());
            });

            /* Set initial state of the Publish button
             */
            $(config.assessmentList).find('[data-draft="publish"]').text(config.publishText);
        }

        /**
         * Set up listeners
         */
        function setupListeners() {
            $(config.assessmentList)
                .on('keydown paste keypress', 'textarea', timeoutUpdate)
                .on('change', ':input', timeoutUpdate)
                .on('success.attachzone', '.attachzone', timeoutUpdate)
                .on('removedfile.attachzone', '.attachzone', timeoutUpdate);
        }



        /**
         * Set up actions to occur when Publishing Feedback
         */
        function setupPublishAction() {
            $(config.assessmentList).on('mousedown', '[data-draft="publish"]', function (e) {
                e.preventDefault();

                var $participant = $(this).closest('[data-participant]'),
                    $form        = $participant.find('[data-feedback-form]'),
                    $actionBar   = $participant.find('[data-action-bar]'),
                    $btnDrafted  = $actionBar.find('[data-draft=drafted]'),
                    $btnPublish  = $actionBar.find('[data-draft=publish]'),
                    participantId = $participant.data('participant'),
                    commentBoxId = "comment-" + participantId;

                // We can safely clear this timeout now that we're publishing
                window.clearTimeout(commentBoxTimeouts[commentBoxId]);

                $btnPublish.attr('disabled', 'disabled').text(config.publishingText);

                $(config.formSaveLabel).toastActivate({
                    text: config.publishingText
                });

                // Publish submits everything
                var formData             = $form.serializeArray(),
                    resubmissionRequired = $form.find('[name="resubmissionRequired"]').prop('checked');
                formData.push({name: 'publish', value: 1});

                config.onPrePublishCallback(participantId, formData);

                $.ajax($form.attr('action'), {
                    dataType: 'json',
                    data:     formData,
                    type:     'post'
                }).done(function (response) {
                    // Have we inserted feedback meta message into action bar ?
                    if ($actionBar.find('[data-feedback-attach-info]').length) {
                        $btnPublish.text(config.publishText).addClass('hide');
                        $btnDrafted.text('');
                    } else {
                        $actionBar.slideUp(function () {
                            $btnPublish.text(config.publishText);
                            $btnDrafted.text('');
                        });
                    }

                    // Update this participant's status:
                    // need to be able to differentiate between reviewed and resubmit required somehow
                    var status = resubmissionRequired ? config.statusResubmissionRequired : config.statusReviewed,
                        title  = resubmissionRequired ? config.resubmissionRequiredText   : config.reviewedText;
                    $participant.attr('data-status', status);

                    // Update the icon
                    $participant.find('[data-status]')
                        .removeClass(config.statusPending)
                        .removeClass(config.statusIncomplete)
                        .removeClass(config.statusReviewed)
                        .removeClass(config.statusResubmissionRequired)
                        .addClass(status)
                        .attr('title', title);

                    // Update the text
                    $participant.find('[data-status-label]').html(title);

                    // Ensure the 'display reviewed' filter is ON
                    $(config.filters).find('[data-status="reviewed"]').prop('checked', true);

                    // Store that this has been published in a data var, so that component info msg
                    // can be deleted if needed on new feedback creation
                    $actionBar.attr('data-published', '');

                    toast.call($participant, config.publishedText, true, false);
                    config.onPublishCallback(
                        participantId,
                        response.success,
                        response.message,
                        response.id
                    );

                }).fail(function (xhr) {
                    toast.call($participant, xhr.responseText, false, false);
                    $btnPublish.removeAttr('disabled').text(config.publishText);
                });
            });
        }

        /**
         * Save draft immediately (for grade field), or after a timeout (for comment box)
         * to prevent excessive draft save requests while typing.
         * New timeouts are continually added while typing, and cleared to allow saving once the user pauses typing.
         */
        function timeoutUpdate() {
            var $input = $(this);

            // Comment box elements have this name attribute
            var elementIsCommentBox = $input.attr("name") === "comment";

            // Unique identifier for this comment box, in the format 'comment-{participantId}'
            var commentId = $input.attr("id");

            if (elementIsCommentBox) {
                // Clear any previous timeout set on this comment box
                window.clearTimeout(commentBoxTimeouts[commentId]);

                // Store the ID of this timeout so we can clear it safely later
                // (setTimeout returns a unique identifier for the timeout)
                commentBoxTimeouts[commentId] = window.setTimeout(function () {
                    onFeedbackChanged.apply($input);
                }, config.timeoutDurationMs);
            } else {
                // No point in using a timeout unless it's a comment box - save immediately
                onFeedbackChanged.apply($input);
            }

            // Skip if no changes have been made
            // This has to go here, rather than at the top of the function,
            // because otherwise rubric descriptor selections don't trigger a draft save??
            if ($input.val() === $input.data('preval')) {
                return;
            }

            var $form = $(this).closest('form');
            $form.find('[data-action-bar]').slideDown();
            $form.find('[data-action-bar] [data-draft=publish]').removeClass('hide');
        }

        /**
         * Callback invoked when the user changes feedback form input.
         * May auto-save a draft, or schedule an auto-save of a draft.
         */
        function onFeedbackChanged() {
            // do a preval comparison to find out if we can skip AJAX
            // (Do not skip attachzones)

            // We passed in  a 'this' to apply() which was already a jquery object
            var $this = this;

            // Don't ignore if an attachzone, rubric mark or resubmit required checkbox
            // Otherwise, ignore if its value hasn't changed since last save
            if (!($this.is('.attachzone') || $this.is('[name="resubmissionRequired"]') || $this.is('[name^="rubric"]'))) {
                if ($this.val() == $this.data('preval')) {
                    return;
                }
            }

            // update the pre-value
            $this.data('preval', $this.val());

            var $participant = $this.closest('[data-participant]'),
                userId       = $participant.data('participant');

            // Is an auto-save in progress?
            if(userId in autoSaveInProgress && autoSaveInProgress[userId]){
                // Don't overlap requests, but indicate we have unsaved changes that must be auto-saved
                autoSavePending[userId] = true;
                return;
            }

            // No auto-saves currently queued for participant - so initiate a draft auto-save.
            autoSaveDraft($participant);
        }

        /**
         * Save the specified $participant's form contents as a draft feedback
         *
         * @param {jQuery} $participant
         */
        function autoSaveDraft($participant)
        {
            var $form        = $participant.find('[data-feedback-form]'),
                $actionBar   = $participant.find('[data-action-bar]'),
                $btnDrafted  = $actionBar.find('[data-draft=drafted]'),
                $btnPublish  = $actionBar.find('[data-draft=publish]'),
                userId       = $participant.data('participant');

            // Register that an auto-save is in progress for this participant
            autoSaveInProgress[userId] = true;

            var data = {
                type: $form.attr('method'),
                dataType: 'json',
                data: $form.serializeArray()
            };

            $actionBar.find('[data-draft]').attr('disabled', 'disabled');
            $btnDrafted.text('Saving...');

            $(config.formSaveLabel).toastActivate({
                text: 'Autosaving...'
            });

            config.onPreDraftCallback($participant.data('participant'), data);

            $.ajax($form.attr('action'), data)
                .done(function (response) {
                    toast.call($participant, 'Draft saved', true, true);

                    // Show the action bar and buttons
                    $actionBar.show();
                    $btnDrafted.show().text('Saved as Draft');
                    $btnPublish.show();

                    // If published feedback already existed, and this is the first change afterwards,
                    if ($actionBar.is('[data-published]')) {
                        $actionBar.removeAttr('data-published');
                    }

                    config.onDraftCallback(
                        $participant.data('participant'),
                        response.success,
                        response.message,
                        response.id
                    );
                })
                .fail(function (xhr) {
                    $actionBar.show();
                    $btnDrafted.show().text('Saving failed');
                    $btnPublish.show();
                    toast.call($participant, xhr.responseText, false, false);
                })
                .always(function () {
                    $btnPublish.removeAttr("disabled");
                    autoSaveInProgress[userId] = false;
                    // Did the user make more changes while the draft auto-save was in progress?
                    if(userId in autoSavePending && autoSavePending[userId]){
                        // Clear the flag and save those pending changes.
                        autoSavePending[userId] = false;
                        autoSaveDraft($participant);
                    }
                });
        }

        /**
         * Pop toast upon action occurring
         *
         * @param {string} text
         * @param {bool} isSuccess
         * @param {bool} openActionBar  Should we open the action bar ?
         */
        function toast(text, isSuccess, openActionBar) {

            if (isSuccess) {
                $(config.formSaveLabel).toastTimeout({
                    text: text || 'Draft saved',
                    css: 'success'
                });
            } else {
                $(config.formSaveLabel).toastTimeout({
                    text: 'Unable to save draft',
                    css: 'alert'
                });
            }

            if(openActionBar){
                this.find('[data-action-bar]').slideDown();
                this.find('[data-action-bar] [data-draft=publish]').removeClass('hide');
            }
        }

        /* The form is never submitted synchronously */
        function disableFormSubmit() {
            $(config.assessmentList).find('form[data-feedback-form]').submit(function(e) {
                e. preventDefault();

                $(this).find('[data-mark-input]').blur();
            });
        }
    }
}(jQuery));
