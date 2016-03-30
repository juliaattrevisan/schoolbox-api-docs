/**
 * A not-quite-Wizard is a Mage.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {

    var pluginName = 'mage';

    var defaults = {

        submittable: 'fieldset',
        reenablePrevious: false,
        onXhrDone: $.noop,
        onXhrFail: $.noop,
        onXhrAlways: $.noop,
        setFormData: $.noop
    };

    function Mage(element, options) {
        var self = this;

        this.element = element;
        this.options = undefined;
        setOptions.call(this, options);

        var $elem = $(this.element);
        // non-negotiable selector
        var $form = $elem.closest('form');

        if ($form.length) {

            // don't duplicate this listener
            $form.off('submit', submitIntercept).on('submit', submitIntercept);
        }

        // wrap the interceptor so we can clear and reset it for overloading
        var onclick = function (e) { clickIntercept(e, self, $elem, $form); };

        // prevent submissions on click and prevent double-listen
        $elem.off('click', onclick).on('click', onclick);
    }


    $.fn[pluginName] = function (options) {

        var plugin = 'plugin_' + pluginName;

        if (typeof options === 'object' || typeof options === 'undefined') {
            // if this is configuration
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new Mage(this, options));

                } else {
                    // just update existing config
                    setOptions.call($.data(this, plugin), options);
                }
            });

        } else if (typeof options === 'string') {
            // if this is a function request
            var mage = this.eq(0).data(plugin);

            if (typeof mage === 'undefined') {
                throw "Mage not initialised";
            }

            // return the thing I'm pointing at
            if (options === 'next' || options === 'target') {
                return $('[data-mage="' + $(mage.element).data('mage-next') + '"]');
            }

        }

    };


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Object} opts
     */
    function setOptions(opts) {

        // if options are already defined, merge with that, otherwise defaults
        var merge = typeof this.options === 'undefined' ? defaults : this.options;

        // and apply the merged set
        this.options = $.extend(true, {}, merge, opts);
    }


    /**
     * Prevent the form from submitting itself
     *
     * @param {Event} e
     */
    function submitIntercept(e) {
        if ($(this).data('mage-preventsubmit')) {
            e.preventDefault();
        }
    }

    /**
     *
     * @param {Event} e
     * @param {Mage} self
     * @param {jQuery} elem
     * @param {jQuery} [form] optional
     */
    function clickIntercept (e, self, elem, form) {
        e.preventDefault();

        if (typeof elem.data('mageSubmitted') != 'undefined') {
            // do not resubmit this section
            return;
        }

        // Perform validation if required

        events = $(e.target).data('events');

        if (events != null && typeof(events.validate) !== undefined) {
            var validator = $(e.target).triggerHandler('validate');

            if (validator !== undefined) {
                if (!validator['result']) {

                    // Validation failed, show message and bomb out

                    form.find('[data-mage-error]').hide().html(validator['message']).slideDown();
                    return;
                }
            }
        }

        // HTML5 FTW
        var method = elem.attr('formmethod') || (form.length ? form.attr('method') : 'post'),
            action = elem.attr('formaction') || (form.length ? form.attr('action') : window.location),
            target = elem.closest(self.options.submittable);

        // do the prep-work
        xhrBefore.call(self, target);

        // send it off
        xhrSend.call(self, method, action, target)
    }


    /**
     * Submits the requested XHR, binds the listeners, then returns the Promise.
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {String} method
     * @param {String} action
     * @param {jQuery} targetElement
     * @return {Deferred}
     */
    function xhrSend(method, action, targetElement) {

        var mage = this,
            targetCopy = targetElement.clone().removeAttr('disabled'),
            form = $('<form/>'), formSerial, formData = {};

        if (this.element.name) {
            // submit clicked-button value
            targetCopy.append($('<input type=text />').attr('name', this.element.name).attr('value', 1))
        }

        form.append(targetCopy);

        // set up an object that can be manipulated outside
        formSerial = form.serializeArray();

        // convert this into a single object rather than an array of objects
        for (var i = 0; i < formSerial.length; ++i) {
            formData[formSerial[i]['name']] = formSerial[i]['value'];
        }

        // pre-flight check
        mage.options.setFormData(formData);

        return $
            .ajax(action, {
                type: method,
                data: formData
            })
            .done(function (data, status, xhr) {
                xhrDone.call(mage, mage.options.onXhrDone, targetElement, parseJSON(data));
            })
            .fail(function (xhr, status, error) {
                xhrFail.call(mage, mage.options.onXhrFail, targetElement, parseJSON(xhr.responseText));
            })
            .always(function (xhrOrData, status, xhrOrError) {
                xhrAlways.call(mage, mage.options.onXhrAlways, targetElement);
            });
    }


    /**
     * Attempt to parse input to return JSON, but do it safely
     *
     * @param {*} ambiguousData
     * @return {Object|String}
     */
    function parseJSON(ambiguousData) {
        try {
            // if this is a JSON string, it will return the object...
            return JSON.parse(ambiguousData);
        } catch (e) {
            // ...otherwise, just throw back whatever we were passed
            return ambiguousData;
        }
    }


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {jQuery} target
     */
    function xhrBefore(target) {
        target.attr('disabled', 'disabled');
        target.find('[data-mage-error]').slideUp(100);

        var $e = $(this.element);

        $e.data('mage-reset', $e.val()).val($e.data('mage-working')).attr('readonly', 'readonly');
    }


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Function} onXhrDone
     * @param {jQuery} target
     * @param {*} data
     */
    function xhrDone(onXhrDone, target, data) {

        var slideDuration = 600;

        // ensure the error is slidden away (although onBefore should have handled it)
        target.find('[data-mage-error]').slideUp(slideDuration);

        // slide away the submit for this section
        $(this.element).data('mage-submitted', true).closest('[data-mage-submitrow]').slideUp(slideDuration);

        // call the callback (this would include any specific manipulation for the section being targeted)
        onXhrDone.call(this.element, data);

        // slide out the target section
        var next = $('[data-mage="' + $(this.element).data('mage-next') +  '"]');
        next.removeAttr('disabled');
        next.find('[data-mage-collapsed]').hide().removeClass('hide').slideDown(slideDuration);
    }


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Function} onXhrFail
     * @param {jQuery} target
     * @param {*} error
     */
    function xhrFail(onXhrFail, target, error) {

        // apply the error text and slide into view
        target.find('[data-mage-error]').hide().html(error).slideDown();

        target.removeAttr('disabled');

        var $e = $(this.element);

        $e.val($e.data('mage-reset')).removeAttr('readonly');

        // call the callback
        onXhrFail.call(this.element, error);
    }


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Function} onXhrAlways
     * @param {jQuery} target
     */
    function xhrAlways(onXhrAlways, target) {

        // re-enable if requested
        if (this.options.reenablePrevious) {
            target.removeAttr('disabled');
        }

        // call the callback
        onXhrAlways.call(this.element);
    }



})(jQuery, window, document);
