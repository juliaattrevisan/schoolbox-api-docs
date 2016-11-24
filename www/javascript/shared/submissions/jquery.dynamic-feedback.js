/**
 * This javascript is used on Quickmark and Inline Grader
 * to power the feedback forms, save drafts etc.
 *
 * Usage:

 $.fn.dynamicFeedback({
    errors: {{ errors | json_encode | raw }},
    statusIncomplete: {{ statusIncomplete }},
    statusPending: {{ statusPending }},
    statusComplete: {{ statusComplete }}
    ...
    ...

 });

 */

(function() {
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

                /* Strings representing incomplete/pending/complete statuse
                 */
                statusIncomplete: '',
                statusPending: '',
                statusComplete: ''
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

            $('[data-combobox]').combobox(config.errors);
        }

        /**
         * Set up listeners
         */
        function setupListeners() {
            $(config.assessmentList)
                .on('keydown paste keypress', 'textarea', timeoutUpdate)
                .on('change', ':input', timeoutUpdate)
                .on('blur', ':input', doASaveActionNao);
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

                $.ajax($form.attr('action'), {
                    dataType: 'json',
                    data:  { publish: 1 },
                    type: 'post'
                }).done(function () {
                    $form.find('[data-interaction-draft]').slideUp(function () {
                        $btnPublish.text('Publish');
                        $btnDrafted.text('');
                    });

                }).fail(function (xhr) {
                    toast.call($form, xhr.responseText, false);
                    $btnPublish.removeAttr('disabled').text('Publish');

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

            if ($(this).val() == $(this).data('preval')) {
                return;
            }

            var $form = $(this).closest('form');
            $form.find('[data-interaction-draft]').slideDown();
        }

        /**
         * Save Feedback contents upon blur
         */
        function doASaveActionNao() {
            // do a preval comparison to find out if we can skip AJAX
            if ($(this).val() == $(this).data('preval')) {
                return;
            }

            // update the pre-value
            $(this).data('preval', $(this).val());

            var $form = $(this).closest('form');

            var data = {
                type: $form.attr('method'),
                dataType: 'json',
                data: $form.serializeArray()
            };

            $form.find('[data-draft]').attr('disabled', 'disabled');
            $form.find('[data-draft="drafted"]').text('Saving...');

            $(config.formSaveLabel).toastActivate({
                text: 'Autosaving'
            });

            $.ajax($form.attr('action'), data)
                .done(function () {
                    toast.apply($form);

                    var $li = $form.closest('[data-status]');

                    $li.attr('data-status', config.statusComplete);

                    $li.find('[data-status]')
                        .removeClass(config.statusIncomplete + ' ' + config.statusPending)
                        .addClass(config.statusComplete);

                    $(config.filters).find('[data-status="complete"]').prop('checked', true);
                    $form.find('[data-draft="drafted"]').text('Saved as Draft');
                })
                .fail(function (xhr) { toast.call($form, xhr.responseText, false); })
                .always(function () {
                    $form.find('[data-draft=publish]').removeAttr("disabled");
                });
        }

        /**
         * Pop toast upon action occurring
         *
         * @param text
         * @param success
         */
        function toast(text, success) {

            $(config.formSaveLabel).toastTimeout({
                text: text || 'Draft Saved',
                css: (success == false) ? 'alert' : 'success'
            });

            this.find('[data-interaction-draft]').slideDown();
        }

    }
}(jQuery));
