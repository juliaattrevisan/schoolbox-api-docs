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

(function() {
    /**
     * Draft-saving timeout
     *
     * @var {number}
     */
    var timeoutHandle;

    /**
     * Is a draft currently being auto-saved for a student?
     *
     * @type {boolean[]} Indexed by user.id
     */
    var autoSaveInProgress = [];

    /**
     * Has a student made more changes during a draft auto-save?
     *
     * @type {boolean[]} Indexed by user.id
     */
    var autoSavePending = [];


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

                /* - The text to display on the publish button
                 * - When publishing is taking place
                 * - When publishing has successfully finished
                 */
                publishText: 'Publish',
                publishingText: 'Publishing...',
                publishedText: 'Feedback published',

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
                .on('change', '[data-rubric-descriptor]', timeoutUpdate)
                .on('blur', ':input:not([data-rubric-descriptor])', onFeedbackChanged)
                .on('success.attachzone', '.attachzone', timeoutUpdate)
                .on('removedfile.attachzone', '.attachzone', timeoutUpdate);
        }

        /**
         * Set up actions to occur when Publishing Feedback
         */
        function setupPublishAction() {
            $(config.assessmentList).on('click', '[data-draft="publish"]', function (e) {
                e.preventDefault();

                // Allow no other save actions nao whilst this one is running
                window.clearTimeout(timeoutHandle);

                var $participant = $(this).closest('[data-participant]'),
                    $form        = $participant.find('[data-feedback-form]'),
                    $actionBar   = $participant.find('[data-action-bar]'),
                    $btnDrafted  = $actionBar.find('[data-draft=drafted]'),
                    $btnPublish  = $actionBar.find('[data-draft=publish]');

                $btnPublish.attr('disabled', 'disabled').text(config.publishingText);

                $(config.formSaveLabel).toastActivate({
                    text: config.publishingText
                });

                // Publish submits everything
                var formData = $form.serializeArray();
                formData.push({name: 'publish', value: 1});

                config.onPrePublishCallback($participant.data('participant'), formData);

                $.ajax($form.attr('action'), {
                    dataType: 'json',
                    data:  formData,
                    type: 'post'
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

                    // Update this participant's status
                    $participant.attr('data-status', config.statusReviewed);

                    // Update the icon
                    $participant.find('[data-status]')
                        .removeClass(config.statusIncomplete + ' ' + config.statusPending)
                        .addClass(config.statusReviewed);

                    // Update the text
                    $participant.find('[data-status-label]').html('Reviewed');

                    // Ensure the 'display reviewed' filter is ON
                    $(config.filters).find('[data-status="reviewed"]').prop('checked', true);

                    // Store that this has been published in a data var, so that component info msg
                    // can be deleted if needed on new feedback creation
                    $actionBar.attr('data-published', '');

                    toast.call($participant, config.publishedText, true, false);
                    config.onPublishCallback(
                        $participant.data('participant'),
                        response.success,
                        response.message,
                        response.id
                    );

                }).fail(function (xhr) {
                    toast.call($participant, xhr.responseText, false, false);
                    $btnPublish.removeAttr('disabled').text(config.publishText);

                    // Restore save actions nao
                    timeoutUpdate();
                });
            });
        }

        /**
         * Update timeout
         */
        function timeoutUpdate() {
            var input = this;
            window.clearTimeout(timeoutHandle);

            timeoutHandle = window.setTimeout(function () {
                onFeedbackChanged.apply(input);
            }, config.timeoutDurationMs);

            if ($(this).val() == $(this).data('preval')) {
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
            var $this = $(this);

            // Ignore if not an attach-zone or rubric mark, and the value has not changed since previously saved
            var isAttachZoneOrRubricMark = $this.is('.attachzone') || ($this.attr('name') && $this.attr('name').indexOf('rubric') === 0 );
            if (!isAttachZoneOrRubricMark && $this.val() == $this.data('preval')) {
                return;
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
                    // remove any info about being linked to a response & having attachments
                    if ($actionBar.is('[data-published]')) {
                        $actionBar.find('[data-feedback-attach-info]').html('');
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

            $(config.formSaveLabel).toastTimeout({
                text: text || 'Draft saved',
                css: (isSuccess) ? 'success' : 'alert'
            });

            if(openActionBar){
                this.find('[data-action-bar]').slideDown();
                this.find('[data-action-bar] [data-draft=publish]').removeClass('hide');
            }
        }
    }
}(jQuery));
