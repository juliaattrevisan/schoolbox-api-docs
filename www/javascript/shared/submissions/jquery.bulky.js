/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
;(function ($, window, document, undefined) {
    var pluginName = 'bulky';

    var currentlyOpenModal = null;

    var defaults = {
        onSubmit: $.noop,  // action-bar DOM element
        submitText: $.noop, // checkedCount, totalCount
        userList: null,
        templateSelector: '[data-template]',
        submitSelector: '[data-submit]',
        checkboxSelector: '[data-selected]',
        labelSelector: '[data-participant-name]',
        unavailableTextSelector: '[data-mass-unavailable]',
        availableTextSelector: '[data-mass-available]',
        participantSelector: '[data-participant]',
        actionBarSelector: '[data-action-bar]',
        userSelector: null,

        /* A function to determine whether an element should be submittable
         */
        checkboxConditionality: function(id) {
            return true;
        }
    };


    /**
     * Bulk Checklist
     *
     * @param element
     * @param options
     * @constructor
     */
    function Bulky(element, options) {
        var bulky = this;

        this.element      = $(element);
        this.options      = $.extend(true, {}, defaults, options);
        this.list         = $('#' + this.element.data('listId'));
        this.template     = this.list.find(this.options.templateSelector).removeAttr('data-template').detach();
        this.submitButton = this.element.find(this.options.submitSelector);

        // validate a couple things
        if (!options.userList) {
            throw "userList option must be a jQuery object";
        }

        if (!options.userSelector) {
            throw "userSelector option must be a string";
        }

        if (!this.list.length) {
            throw "Did not find matching list for data-list-id";
        }

        /* A convenience var
         */
        var opt = this.options;

        // install listeners
        this.element.on('change', opt.checkboxSelector, function () {

            var $checks = bulky.element.find(opt.checkboxSelector);

            // see if the list has anything selected
            var all  = $checks.length,
                len  = $checks.filter(':checked').length;

            refreshSubmitText.call(bulky, len, all);

            if (len) {
                bulky.submitButton.removeAttr('disabled');

            } else {
                bulky.submitButton.attr('disabled', 'disabled');
            }
        });

        /**
         * When the modal opens
         */
        this.element.on('open.fndtn.reveal', function () {
            var $checks;

            /* Number of invalid checkboxes
             * If all checkboxes are invalid, hide the action button and display different text
             */
            var numInvalid = 0;

            // cache this value before we inject the contents
            $(this).data('fixedContentHeight', $(this).outerHeight());

            /* Iterate through all these nodes and assign ids
             * Then construct a html fragment to insert into the modal
             */
            bulky.list.html(
                opt.userList.find(opt.actionBarSelector + opt.userSelector).closest(opt.labelSelector).map(function() {
                    var $item = bulky.template.clone(),
                        tag   = $item.prop("tagName").toLowerCase(),
                        $lbl  = $item.find(opt.labelSelector),
                        $chk  = $item.find(opt.checkboxSelector),
                        id    = $(this).closest(opt.participantSelector).data('participant'),
                        html;

                    /* Assign ids
                     */
                    $chk.attr('id', id);
                    $lbl.attr('for', id);

                    // apply the name
                    $lbl.text($(this).data('participantName'));

                    /* Checkbox must pass this conditional to be provided
                     */
                    if (!opt.checkboxConditionality(id)) {
                        $chk.prop('disabled', true).removeAttr('checked');
                        $lbl.text($lbl.text() + ' (invalid)');

                        numInvalid++;
                    }

                    // because outerHtml doesn't exist as a thing -_-
                    html = ['<', tag, '>', $item.html(), '</', tag, '>' ].join('');

                    return html;
                }).get().join('')
            );

            /* All the checkboxes that have been ticked
             * Change contents of modal accordingly
             */
            $checks = bulky.element.find(opt.checkboxSelector);

            if ($checks.length === numInvalid) {

                /* If they're all invalid
                 */
                bulky.submitButton.hide();
                bulky.element.find(opt.unavailableTextSelector).show();
                bulky.element.find(opt.availableTextSelector).hide();
            } else if ($checks.length) {

                /* There are some checkboxes to submit
                 */
                refreshSubmitText.call(bulky, $checks.length, $checks.length);

                bulky.submitButton.show();
                bulky.element.find(opt.unavailableTextSelector).hide();
                bulky.element.find(opt.availableTextSelector).show();
            } else {

                /* There are no checkboxes available
                 */
                bulky.submitButton.hide();
                bulky.element.find(opt.unavailableTextSelector).show();
                bulky.element.find(opt.availableTextSelector).hide();
            }
        });


        /**
         * When the modal finishes opening
         */
        this.element.on('opened.fndtn.reveal', function () {
            currentlyOpenModal = $(this);
            // start listening
            $(window).on('resize', refreshModalHeight);
            refreshModalHeight.call(window);
        });


        /**
         * When the modal finishes closing
         */
        this.element.on('closed.fndtn.reveal', function () {
            currentlyOpenModal = null;

            // stop listening
            $(window).off('resize', refreshModalHeight);

            // clear the list
            bulky.list.html('');

            // re-enable submit button
            bulky.submitButton.removeAttr('disabled');
        });


        /**
         * When a submit button has been clicked
         */
        this.submitButton.on('click', function (e) {
            e.preventDefault();

            /* Find all the action bars that were in the checkbox list
             */
            opt.onSubmit.call(bulky, bulky.element.find(':checked').map(function() {
                return opt.userList.find('[data-participant='+this.id+'] [data-action-bar]').get();
            }));
        });


        /**
         * When any of the other component action buttons have been clicked
         * Typically it's "Cancel" or the Action button itself, after which
         * the modal can be dismissed
         */
        this.element.on('click', '.button', function () {
            bulky.element.foundation('reveal', 'close');
        });
    }


    /**
     * Private method; expects to be applied to Bulky
     */
    function refreshSubmitText(len, all) {
        var text = this.options.submitText.call(this, len, all);

        if (typeof text === 'string' && text.trim().length) {
            this.submitButton.text(text.trim());
        }
    }


    /**
     * Will be called when the viewport is resized
     * Private method; expects to be applied to window
     */
    function refreshModalHeight() {
        if (!currentlyOpenModal) {
            return;
        }

        var maxHeight = parseInt($(window).height() / 5 * 4) - currentlyOpenModal.data('fixedContentHeight');

        $('#' + currentlyOpenModal.data('listId')).css('maxHeight', maxHeight);
    }


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Bulky(this, options));
            }
        });
    }

})(jQuery, window, document);
