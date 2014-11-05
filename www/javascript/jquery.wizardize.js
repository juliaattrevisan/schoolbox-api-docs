/**
 * jQuery wizard plugin
 */
(function($) {
    "use strict";

    var Wizardize = function(element, options) {
        var defaults = {
            startAtStep: 1
        };
        this.element = element;
        this.options = $.extend({}, defaults, options);
        this.step    = this.options.startAtStep;
    };

    Wizardize.prototype.initSteps = function() {
        // Search for data-wizard-step attributes
    };

    Wizardize.prototype.gotoStep = function(step) {
        this.step = step;
        this.element.trigger('wizardize_gotoStep', [this]);
    };

    $.fn.wizardize = function(method) {
        var args = [];
        if (arguments.length === 0) {
            args   = [{}];
            method = 'init';
        } else if (typeof method === 'object') {
            args   = [method];
            method = 'init';
        } else {
            // First argument is the method name - pop it off
            args = Array.prototype.slice.call(arguments);
            args.shift();
        }

        // Init it
        if (method === 'init') {
            this.each(function() {
                var element = $(this);
                element.data('wizardize', new Wizardize(element, args[0]));
            });
        }

        switch (method) {
            case 'gotoStep':
                this.each(function() {
                    var element = $(this);
                    var wizardize = element.data('wizardize');
                    if (wizardize instanceof Wizardize) {
                        wizardize.gotoStep.apply(wizardize, args);
                    }
                });
                break;

        }
        return this;
    };
})(jQuery);
