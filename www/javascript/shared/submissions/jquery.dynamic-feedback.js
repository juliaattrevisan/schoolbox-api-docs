/**
 * This javascript is used on Quickmark and Inline Grader
 * to power the feedback forms, save drafts etc.
 *
 * Usage:

 $.fn.dynamicFeedback({
    errors: {{ errors | json_encode | raw }},
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
     * @var {number} tt
     */
    var tt;

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

                /* Label to indicate current status of action being undertaken
                 */
                formSaveLabel: $('[data-save-draft]'),

                /* A list of all the people who have publishable rows
                 */
                assessmentList: $('#assessment-status'),

                /* Buttons which control which rows are visible
                 */
                filters: $('#filters'),

                /* A list of "Feedback States"
                 */
                errors: [],

                /* CSS classes representing incomplete/pending/reviewed states
                 */
                statusIncomplete: 'incomplete',
                statusPending: 'pending',
                statusReviewed: 'reviewed',

                /* The text to display on the publish button
                 */
                publishText: 'Publish',

                /* A callback to execute upon successful completion of Publish action
                 */
                onPublishCallback: function() {}
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Set up pre-values
         * Also populate the comboboxes with Feedback States
         */
        function setupPreValues() {
            // setup pre-values
            $(config.assessmentList).find(':input').each(function () {
                // stash the current state, so we can compare on blur and prevent unnecessary firing
                $(this).data('preval', $(this).val());
            });

            /* Feedback States
             */
            $('[data-combobox]').combobox(config.errors);

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
                .on('blur', ':input', doASaveActionNao)
                .on('success.attachzone', '.attachzone', timeoutUpdate)
                .on('removedfile.attachzone', '.attachzone', timeoutUpdate);
        }

        /**
         * Set up actions to occur when Publishing Feedback
         */
        function setupPublishAction() {
            $(config.assessmentList).on('click', '[data-draft="publish"]', function (e) {
                e.preventDefault();

                var $form      = $(this).closest('form'),
                    $actionBar = $form.find('[data-interaction-draft]');

                var $btnDrafted = $actionBar.find('[data-draft=drafted]'),
                    $btnPublish = $actionBar.find('[data-draft=publish]');

                $btnPublish.attr('disabled', 'disabled').text('Publishing...');

                $(config.formSaveLabel).toastActivate({
                    text: 'Publishing...'
                });

                // Publish submits everything
                var formData = $form.serializeArray();
                formData.push({name: 'publish', value: 1});
                $.ajax($form.attr('action'), {
                    dataType: 'json',
                    data:  formData,
                    type: 'post'
                }).done(function () {
                    // Get handle to participant row
                    var $li = $form.closest('[data-participant]');

                    var $actionBar = $li.find('[data-interaction-draft]');

                    if ($actionBar.find('.component-action-info').length) {
                        $btnPublish.text(config.publishText).addClass('hide');
                        $btnDrafted.text('');
                    } else {
                        $actionBar.slideUp(function () {
                            $btnPublish.text(config.publishText);
                            $btnDrafted.text('');
                        });
                    }

                    // Update this participant's status
                    $li.attr('data-status', config.statusReviewed);

                    // Update the icon
                    $li.find('[data-status]')
                        .removeClass(config.statusIncomplete + ' ' + config.statusPending)
                        .addClass(config.statusReviewed);

                    // Update the text
                    $li.find('[data-status-label]').html('Reviewed');

                    // Ensure the 'display reviewed' filter is ON
                    $(config.filters).find('[data-status="reviewed"]').prop('checked', true);

                    // Store that this has been published in a data var, so that component info can be deleted if needed
                    // on new feedback creation
                    $actionBar.attr('data-published', true);

                    toast.call($form, 'Feedback published', true, false);
                    config.onPublishCallback();

                }).fail(function (xhr) {
                    toast.call($form, xhr.responseText, false, false);
                    $btnPublish.removeAttr('disabled').text(config.publishText);

                }).always(function () {

                });
            });
        }

        /**
         * Update timeout
         */
        function timeoutUpdate() {
            var input = this;
            window.clearTimeout(tt);

            tt = window.setTimeout(function () {
                doASaveActionNao.apply(input);
            }, 600);

            if ($(this).val() === $(this).data('preval')) {
                return;
            }

            var $form = $(this).closest('form');
            $form.find('[data-interaction-draft]').slideDown();
            $form.find('[data-interaction-draft] [data-draft=publish]').removeClass('hide');
        }

        /**
         * Save Feedback contents upon blur
         */
        function doASaveActionNao() {
            // do a preval comparison to find out if we can skip AJAX
            // (Do not skip attachzones)
            var $this = $(this);
            if ((! ($this.is('.attachzone') || ($this.attr('name') && $this.attr('name').indexOf('rubric') === 0 ))) && $this.val() === $this.data('preval')) {
                return;
            }

            // update the pre-value
            $this.data('preval', $this.val());

            var $form = $this.closest('form');

            var data = {
                type: $form.attr('method'),
                dataType: 'json',
                data: $form.serializeArray()
            };

            $form.find('[data-draft]').attr('disabled', 'disabled');
            $form.find('[data-draft="drafted"]').text('Saving...');

            $(config.formSaveLabel).toastActivate({
                text: 'Autosaving...'
            });

            $.ajax($form.attr('action'), data)
                .done(function () {
                    toast.call($form, 'Draft saved', true, true);
                    $form.find('[data-draft="drafted"]').text('Saved as draft');

                    // If this was just published and this is the first change afterwards - removing any info about being linked to a response & having attachments
                    if ($form.find('[data-published="true"]').length) {
                        $form.find('[data-feedback-attach-info]').html('');
                        $form.find('[data-interaction-draft][data-published=true]').removeAttr('data-published');
                    }
                })
                .fail(function (xhr) {
                    toast.call($form, xhr.responseText, false, false);
                })
                .always(function () {
                    $form.find('[data-draft=publish]').removeAttr("disabled");
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
                this.find('[data-interaction-draft]').slideDown();
                this.find('[data-interaction-draft] [data-draft=publish]').removeClass('hide');
            }
        }
    }
}(jQuery));
