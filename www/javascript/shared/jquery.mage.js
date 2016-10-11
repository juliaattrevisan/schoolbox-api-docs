/**
 * A not-quite-Wizard is a Mage.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {

    var slideSlow = 600;
    var slideFast = 100;

    var pluginName = 'mage';

    var defaults = {

        submittable: 'fieldset',
        reenablePrevious: false,
        onXhrDone: $.noop,
        onXhrFail: $.noop,
        onXhrAlways: $.noop,
        manipulateSerialized: function(formSerial) {
            return formSerial;
        },
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


        // Perform validation if required

        // We cannot use a simple triggerHandler('validate') as that
        // only returns the value of the last handler to execute.
        // @see http://stackoverflow.com/questions/2518421/jquery-find-events-handlers-registered-with-an-object

        var allValid = true;
        $.each(getEventHandlers(e.target, 'validate'), function(idx, obj){
            // Quit as soon as we know data is invalid (don't merge errors)
            if(allValid && undefined !== obj['type'] && 'validate' === obj['type']){
                var validator = obj.handler.call(e.target);
                if (validator !== undefined) {
                    if (!validator['result']) {
                        // Validation failed, show message and bomb out
                        form.find('[data-mage-error]').hide().html(validator['message']).slideDown();
                        allValid = false;
                    }
                }
            }
        });
        if(!allValid){
            return;
        }

        if (typeof elem.data('mageSubmitted') != 'undefined') {
            // do not resubmit this section
            return;
        }

        // HTML5 FTW
        var method = elem.attr('formmethod') || (form.length ? form.attr('method') : 'post'),
            action = elem.attr('formaction') || (form.length ? form.attr('action') : window.location.pathname),
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
            targetCopy = cloneTarget(targetElement),
            form = $('<form/>'), formSerial;

        if (this.element.name) {
            // submit clicked-button value
            targetCopy.append($('<input type=text />').attr('name', this.element.name).val(1))
        }

        form.append(targetCopy);

        // set up an object that can be manipulated outside
        formSerial = form.serializeArray();

        /* Last chance to manipulate serialized data stack
         * There might be a better way than this, but didn't find it
         */
        formSerial = mage.options.manipulateSerialized.call(mage, formSerial);

        // pre-flight check
        mage.options.setFormData(formSerial);

        return $
            .ajax(action, {
                type: method,
                data: formSerial
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
     * This function works around a long-standing jquery bug: http://dev.jquery.com/ticket/1294
     * tl;dr: the clone() function does not preserve the chosen value of <select/> elements :(
     *
     * @param target
     * @returns {*}
     */
    function cloneTarget(target)
    {
        var hauser = target.clone().removeAttr('disabled');

        hauser.find('select').each(function () {

            var $select = $(this),
                name    = $select.attr('name'),
                // extract the value from the ORIGINAL
                value   = target.find('[name="' + name + '"]').val(),
                // replicate the select with a new input
                $hidden = $('<input type="hidden" />').attr('name', name).val(value);

            // and switch it out
            $select.replaceWith($hidden);
        });

        return hauser;
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
        target.find('[data-mage-error]').slideUp(slideFast);

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

        // ensure the error is slidden away (although onBefore should have handled it)
        target.find('[data-mage-error]').slideUp(slideSlow);

        // slide away the submit for this section
        $(this.element).data('mage-submitted', true).closest('[data-mage-submitrow]').slideUp(slideSlow);

        // also slide away this section's helptext
        $(this.element).closest(this.options.submittable).find('[data-mage-helptext]').slideUp(slideSlow);

        // slide out the target section
        var next = $('[data-mage="' + $(this.element).data('mage-next') +  '"]');
        next.removeAttr('disabled');

        /* Enable the fieldset and then inject the dom fragment in
         * selectize (and perhaps other plugins) won't re-enable themselves if the
         * parent fieldset is re-enabled. Upon instantiation selectize sets its
         * locked/unlocked state - but previously, instantiation was into a disabled
         * fieldset, so you have to manually re-enable them which is terrible
         */
        // call the callback (this would include any specific manipulation for the section being targeted)
        onXhrDone.call(this.element, data);

        next.find('[data-mage-collapsed]').hide().removeClass('hide').slideDown(slideSlow);
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

    /**
     * Retrieves all event handlers for a target.
     *
     * Deliberately not pluginified, as you shouldn't ever have to manually
     * call event handlers.
     *
     * (Mage does in order to call validation handlers manually, because it
     *  needs the result from all of them - see clickIntercept)
     *
     * @param target
     * @param event
     * @returns {Array}
     */
    function getEventHandlers(target, event)
    {
        if (!('_data' in $)) {
            // Access to _data was removed
            throw new Error('jQuery._data internal API method was removed');
        }
        var events = $._data(target, 'events');
        if (typeof event === 'undefined') {
            return events;
        }
        return (event in events) ? events[event] : [];
    }

})(jQuery, window, document);
