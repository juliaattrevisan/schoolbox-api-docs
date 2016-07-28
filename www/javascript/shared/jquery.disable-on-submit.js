/**
 * Prevents a form being submitted multiple times.
 *
 * Usages:
 *   1. Include this script on a page, it will automatically attach to
 *      forms with the 'data-disable-on-submit' attribute, or
 *   2. Include this script on a page, then manually call on a form with
 *      $(selector).disableOnSubmit(options)
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {
    "use strict";

    var defaults = {
        dataAttrWorking:  'data-working',
    };

    var settings;

    $.fn.disableOnSubmit = function(options){

        // Setup
        settings = init(options || {});

        // Enable chaining and multiple forms per page
        return this.each(function(idx, elem){
            $(elem).on('submit', function(){
                // Is the submitted form valid?
                if('function' !== typeof $(elem).valid || $(elem).valid()) {
                    var $submit = $(this).find(':submit');
                    $submit.prop('disabled', true);
                    $submit.val($submit.attr(settings.dataAttrWorking));
                }
            });
        });
    }

    /**
     * Init
     *
     * @param options
     * @returns {*}
     */
    function init(options) {
        return $.extend(defaults, options, true);
    }

    // Run automatically
    $(function() {
        $('[data-disable-on-submit]').disableOnSubmit();
    });

})(jQuery, window, document);