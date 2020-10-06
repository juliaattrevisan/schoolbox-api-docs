/**
 * Plugin for dynamically calculating and displaying a grade based on a given markType
 *
 * Usage:
 *  - initialise as per a normal plugin (either directly onto an element or, more likely, a parent container)
 *  - trigger the event 'dynagrade' on the element you want to calculate. This element must have 2 data attributes:
 *          * data-numerator
 *          * data-denominator
 *  -
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
;(function ($, window, document, undefined) {

    var pluginName = 'dynagrade';

    var defaults = {
        // a submission_mark_type.slug value
        mode: 'percent',

        // the configuration data specific to the given marktype (presently only 'letter' uses a config)
        conf: {},

        // what to do once a calculation is performed, by default just set the element's text to match:
        //  - `this` refers to the target element (wrapped in $);
        //  - `text` is the calculated output intended for display
        onCalculate: function (text) {
            this.text(text);
        }
    };

    function Dynagrade(element, options) {
        var self = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        var conf = this.options.conf;

        $(element).on('dynagrade dynagrade.preload', function (e) {

            var target = $(e.target),
                scaledMark = target.attr('data-scaled-mark');
            if (scaledMark === null || scaledMark === undefined) {
                self.options.onCalculate.call(target, '')
            } else {
                switch (self.options.mode) {
                    case 'percent':
                        calcPercent.call(target, self.options.onCalculate);
                        break;
                    case 'letter':
                        calcLetter.call(target, conf, self.options.onCalculate);
                        break;
                    case 'none':
                        calcNone.call(target, self.options.onCalculate);
                        break;
                    case 'raw':
                    default:
                        calcRaw.call(target, self.options.onCalculate);
                        break;
                }
            }

        });
    }

    function calcPercent(callback) {
        var percentage = 0;

        var scaledMark = this.attr('data-scaled-mark'),
            maxFromIndicators = this.attr('data-row-max-sum'),
            capMax = parseFloat(this.attr('data-max'));

        // If capability max is 0, use the max calculated from the indicators instead
        if (capMax >= 0) {
            percentage = parseFloat(scaledMark / capMax * 100);
        } else {
            percentage = parseFloat(scaledMark / maxFromIndicators * 100);
        }

        if (isNaN(percentage)) {
            percentage = 0;
        }

        // If sum isn't a whole number: round to 2dp
        if(percentage % 1 !== 0){
            percentage = percentage.toFixed(2);
        }

        callback.call(this, percentage + ' %');
    }

    function calcLetter(conf, callback) {
        // start by getting the percentage
        var scaledMark = this.attr('data-scaled-mark'),
            max        = this.attr('data-max'),
            percentage = parseFloat(scaledMark / max * 100),
            $el        = this;

        if (isNaN(percentage)) {
            percentage = 0;
        }

        // As of SA-11115 conf for letter grades is no longer key-value:
        // it is now a list (with lowerBound and caption) and is actually in the
        // correct order.
        // Thus it no longer needs to be mapped to a list, and then reversed:
        // we'll make sure it's correct though.
        var sortedConf = Array.from(conf);
        sortedConf.sort(function (a, b) {
            return b.lowerBound - a.lowerBound;
        })

        $.each(conf, function (k, v) {

            var lowerBound = Number(v.lowerBound),
                caption    = v.caption;

            // bound is too high, ignore this one
            if (percentage < lowerBound) {
                return true; // jq:continue
            }

            // first bound that is below our percentage; apply it
            callback.call($el, caption);

            // aaand we're done
            return false; // jq.break;
        });

        // there is a caveat here that if the lowest bottom-bound is above the percentage (ie. not zero), nothing will happen
    }

    function calcRaw(callback) {
        var scaledMark = this.attr('data-scaled-mark'),
            max        = this.attr('data-max');

        callback.call(this, scaledMark + ' / ' + max);
    }

    function calcNone(callback) {
        callback.call(this, '');
    }


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Dynagrade(this, options));
            }
        });
    }

})(jQuery, window, document);
