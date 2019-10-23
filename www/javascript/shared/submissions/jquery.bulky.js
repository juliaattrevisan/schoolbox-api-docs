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
        checkboxConditionality: function(id) {
            return true;
        }
    };

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

        this.element.on('open.fndtn.reveal', function () {

            var $checks;

            // cache this value before we inject the contents
            $(this).data('fixedContentHeight', $(this).outerHeight());

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

                    if (opt.checkboxConditionality(id)) {

                    } else {

                    }

                    // because outerHtml doesn't exist as a thing -_-
                    html = ['<', tag, '>', $item.html(), '</', tag, '>' ].join('');

                    return html;

                }).get().join('')
            );

            $checks = bulky.element.find(opt.checkboxSelector);

            if ($checks.length) {
                refreshSubmitText.call(bulky, $checks.length, $checks.length);
                bulky.submitButton.show();
                bulky.element.find(opt.unavailableTextSelector).hide();
                bulky.element.find(opt.availableTextSelector).show();

            } else {
                bulky.submitButton.hide();
                bulky.element.find(opt.unavailableTextSelector).show();
                bulky.element.find(opt.availableTextSelector).hide();
            }

        });

        this.element.on('opened.fndtn.reveal', function () {
            currentlyOpenModal = $(this);
            // start listening
            $(window).on('resize', refreshModalHeight);
            refreshModalHeight.call(window);
        });

        this.element.on('closed.fndtn.reveal', function () {
            currentlyOpenModal = null;

            // stop listening
            $(window).off('resize', refreshModalHeight);

            // clear the list
            bulky.list.html('');

            // re-enable submit button
            bulky.submitButton.removeAttr('disabled');
        });

        this.submitButton.on('click', function (e) {
            e.preventDefault();
            opt.onSubmit.call(bulky, bulky.element.find(':checked').map(function () { return opt.userList.find('[data-participant='+this.id+'] [data-action-bar]').get(); }));
        });

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
