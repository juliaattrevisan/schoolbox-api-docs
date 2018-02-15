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
        currentStopClass: 'current-stop',
        reenablePrevious: false,
        beforeSubmit: $.noop,
        onXhrDone: $.noop,
        onXhrFail: $.noop,
        onXhrAlways: $.noop,
        onRevert: $.noop,
        confirmRevert: $.noop,
        setFormData: $.noop,
        validateOptions: false
    };

    function Mage(element, options) {
        var self = this;

        this.element = element;
        this.options = undefined;
        setOptions.call(this, options);

        var $elem = $(this.element);

        this.thisPart = $elem.attr('name');
        this.nextPart = $elem.data('mage-next');

        // non-negotiable selector
        var $form = $elem.closest('form');

        if ($form.length) {

            // don't duplicate this listener
            $form.off('submit', submitIntercept).on('submit', submitIntercept);
        }

        // wrap the interceptor so we can clear and reset it for overloading
        var onclick = function (e) {
            e.preventDefault();
            submitSection(self, $elem, $form);
        };

        var onrevert = function (e) {
            e.preventDefault();

            self.options.confirmRevert.call($(this).closest('[data-mage-trainstop]'), e, function () {
                // if this callback is called by the confirmation, perform the reversion
                var sectionToRestore = $(self.element).closest(self.options.submittable);
                var sectionsToClear  = sectionToRestore.nextAll(self.options.submittable);

                sectionsToClear.find(':submit').mage('hide');

                // just before re-enabling the section, call the callback
                self.options.onRevert.call(self.element);

                // enable this section (which will also open it)
                self.enableSection();

                // set the trainline to the correct stop
                self.visitSection();
            });
        };

        // prevent double-listen of reversion
        $('[data-mage-trainstop=' + this.thisPart + '] a').off('click', onrevert).on('click', onrevert);

        // prevent submissions on click and prevent double-listen
        // this is delegated so stallSubmit can do its job where required
        $form
            .off('click', '[name=' + this.thisPart + ']', onclick)
            .on('click',  '[name=' + this.thisPart + ']', onclick);
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
            var rtn = [];

            this.each(function () {
                var mage = $(this).data(plugin);

                if (typeof mage === 'undefined') {
                    throw "Mage not initialised";
                }

                // return the thing I'm pointing at
                if (options === 'next' || options === 'target') {
                    rtn.push($('[data-mage="' + $(mage.element).data('mage-next') + '"]'));
                }

                // attempt a method call
                var method = options + 'Section';

                if (method in mage) {
                    rtn.push(mage[method].call(mage));
                }
            });

            return $($.map(rtn, function (el) { return $.makeArray(el) } ));
        }

    };


    /**
     * Disable the fieldset to which this mage step applies.
     * Does NOT automatically close the section.
     *
     * @return void
     */
    Mage.prototype.disableSection = function () {

        var $e = $(this.element);
        var $t = $e.closest(this.options.submittable);
        
        $t.attr('disabled', 'disabled');
        $t.find('[data-mage-error]').slideUp(slideFast);

        if ($e.data('mage-reset') === undefined) {
            $e.data('mage-reset', $e.val());
        }
        $e.val($e.data('mage-working')).attr('readonly', 'readonly');
    };

    /**
     * Enable the fieldset to which this mage step applies.
     * If the section is closed, this will also open it.
     *
     * @return void
     */
    Mage.prototype.enableSection = function () {

        var $e = $(this.element);
        var $t = $e.closest(this.options.submittable);

        $t.removeAttr('disabled');

        if ($e.data('mage-reset')) {
            // reset the submit button text and re-enable it
            $e.val($e.data('mage-reset')).removeAttr('readonly');
        }

        // unset the 'submitted' flag
        $e.removeData('mage-submitted');

        // open this section if it's not already
        this.openSection();
    };

    /**
     * Close the section to which this mage step applies.
     * If the section is enabled, this will also disable it.
     *
     * @return void
     */
    Mage.prototype.closeSection = function () {

        var $e = $(this.element);
        var $t = $e.closest(this.options.submittable);

        this.disableSection();

        // slide away the submit for this section
        $e.closest('[data-mage-submitrow]').slideUp(slideSlow);

        // also slide away this section's helptext
        $e.closest(this.options.submittable).find('[data-mage-helptext]').slideUp(slideSlow, function() {
            // disable all inputs in the closed section
            $t.find(':input').attr('disabled', 'disabled');
        });


    };

    /**
     * Open the section to which this mage step applies.
     * Does NOT automatically enable the section.
     *
     * @return void
     */
    Mage.prototype.openSection = function () {

        var $e = $(this.element);
        var $t = $e.closest(this.options.submittable);

        // enable all inputs in the section (that we have not explicitly declared to be excluded)
        $t.find(':input:not([data-mage-disabled])').removeAttr('disabled');

        // slide out the main content wrapper
        var $content = $e.closest('[data-mage-collapsed]');

        if ($content.hasClass('hide')) {
            // swap .hide for jQuery.hide()
            $content.hide().removeClass('hide');
        }

        $e.closest('[data-mage-collapsed]').slideDown(slideSlow);

        // slide out the submit for this section
        $e.closest('[data-mage-submitrow]').slideDown(slideSlow);

        // also out this section's helptext
        $e.closest(this.options.submittable).find('[data-mage-helptext]').slideDown(slideSlow);
    };

    /**
     * Hide the section to which this mage step applies.
     * This is the more aggressive form of ::closeSection(), hiding *all* the content except the legend
     *
     * @return void
     */
    Mage.prototype.hideSection = function () {

        var $e = $(this.element);

        var self = this;

        $e.closest('[data-mage-collapsed]').slideUp(slideSlow, function () {

            // hide any previous errors
            $(this).find('[data-mage-error]').hide();

            // at this point you might think "wait what??" - what we're doing is hiding the overall
            // wrapper of this content, but preparing the content for the next time it's displayed
            $e.closest('[data-mage-submitrow]').show();

            // also out this section's helptext
            $e.closest(self.options.submittable).find('[data-mage-helptext]').show();

        });
    };

    /**
     * Set the 'current stop' of the trainline to be this section.
     *
     * @return void
     */
    Mage.prototype.visitSection = function () {

        var thisIsMyStop = $('[data-mage-trainstop=' + this.thisPart + ']');

        var c = this.options.currentStopClass;

        thisIsMyStop.addClass(c).siblings('.'+c).removeClass(c);
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
     * @param {event} e
     */
    function submitIntercept(e) {
        if ($(this).data('mage-preventsubmit')) {
            e.preventDefault();
        }
    }

    /**
     * Perform submission of this section of the form
     *
     * @param {Mage} self
     * @param {jQuery} elem
     * @param {jQuery} [form] optional
     */
    function submitSection(self, elem, form) {

        // destroy any existing alaressValidate options
        $(form).removeData('validator');

        // set alaressValidate with new options
        if (self.options.validateOptions !== false) {
            form.alaressValidate(self.options.validateOptions);
        }

        // call beforeSubmit
        self.options.beforeSubmit.call();

        copyCkEditorValues.call(self, elem);

        // Update textarea inner htmls with the value, for firefox <= 51
        elem.closest(self.options.submittable).find('textarea').each(function() {
            $(this).html($(this).val());
        });

        /**
         * Validate the form
         * Will use alaressValidate if set on the form or any required attributes on fields
         * If the form has neither - it will pass through without any validation
         */
        if (form.valid() == false) {

            // focus user to first invalid field
            $(form).find(':input.error').first().focus();

            // prevent submit
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
        xhrBefore.call(self);

        // send it off
        xhrSend.call(self, method, action, target);
    }

    /**
     * Called just before validation runs to copy any CK Editor values to their corresponding text area
     */
    function copyCkEditorValues(elem)
    {
        // Is CkEditor running on this page?
        if(typeof CKEDITOR == 'undefined' || typeof CKEDITOR.instances == 'undefined'){
            return;
        }

        /* Find textareas with a name attribute
         */
        var $textAreas = elem.closest(this.options.submittable).find('textarea[name]');

        $textAreas.each(function(idx, elem){
            var $textArea = $(elem);
            var $ckEditorById = CKEDITOR.instances[$textArea.attr('id')];
            var $ckEditorByName = CKEDITOR.instances[$textArea.attr('name')];

            // Does there exist a CK Editor instance for this text area?
            //
            // Update textareas in two different ways  because different browsers (*cough*firefail*cough*) can't
            // handle textarea.val() as part of form serialization

            /* Note that ckeditors can spawn via an id or a name attribute.
             * The attribute that was used when they spawned is the array key for CKEDITOR.instances[] literal.
             *
             * Name and id attributes do not have to be identical, so we have to check both because at this location
             * we don't know how a ckeditor was spawned/initialised
             *
             * ids are unique on the page and ckeditor can't natively handle multiple textareas with the same name
             * attribute. An id cannot match to multiple inputs. A name cannot match to multiple inputs.
             *
             * So we can make a reasonable conclusion that a $textArea's id, or its name, must be in the
             * ckeditor.instances literal, for the current $textArea that we're examining.
             *
             * Check by id first, then check by name to stay synonymous with editor.js start();
             * It doesn't really matter which order you choose, for for the sake of similarity...
             */
            if ($ckEditorById !== undefined) {
                $textArea.html($ckEditorById.getData());
                $textArea.val($ckEditorById.getData());
            } else if ($ckEditorByName !== undefined) {
                $textArea.html($ckEditorByName.getData());
                $textArea.val($ckEditorByName.getData());
            }
        });
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

        // pre-flight check
        mage.options.setFormData(formSerial);

        return $
            .ajax(action, {
                type: method,
                data: formSerial
            })
            .done(function (data/*, status, xhr*/) {
                xhrDone.call(mage, mage.options.onXhrDone, targetElement, parseJSON(data));
            })
            .fail(function (xhr/*, status, error*/) {
                xhrFail.call(mage, mage.options.onXhrFail, targetElement, parseJSON(xhr.responseText));
            })
            .always(function (/*xhrOrData, status, xhrOrError*/) {
                xhrAlways.call(mage, mage.options.onXhrAlways);
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
     */
    function xhrBefore() {
        this.disableSection();
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

        // mark this section as being submitted, preventing repeat submissions
        $(this.element).data('mage-submitted', true);

        // close this section
        this.closeSection();

        /* Enable the fieldset and then inject the dom fragment in
         * selectize (and perhaps other plugins) won't re-enable themselves if the
         * parent fieldset is re-enabled. Upon instantiation selectize sets its
         * locked/unlocked state - but previously, instantiation was into a disabled
         * fieldset, so you have to manually re-enable them which is terrible
         */
        $('[data-mage="' + this.nextPart +  '"]').removeAttr('disabled');

        // call the callback (this would include any specific manipulation for the section being targeted)
        onXhrDone.call(this.element, data);

        // if this is not the final section, the submit for the next section should be available, albeit hidden
        var nextMage = $('[name="' + this.nextPart +  '"]');

        if (nextMage.length) {
            // enable and show the next section
            nextMage.mage('enable');

            // set the trainline to the next stop
            nextMage.mage('visit');
        }
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

        // re-enable this section (which will implicitly reopen it if it's closed)
        this.enableSection();

        // call the callback
        onXhrFail.call(this.element, error);
    }


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Function} onXhrAlways
     */
    function xhrAlways(onXhrAlways) {

        // re-enable if requested
        if (this.options.reenablePrevious) {
            this.enableSection();
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
     *  needs the result from all of them - see submitSection)
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
