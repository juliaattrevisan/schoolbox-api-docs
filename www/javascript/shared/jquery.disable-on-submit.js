/**
 * Prevents multiple form submission
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {
    "use strict";

    var defaults = {
        attrDataWorking:  'data-working',
    };


    $.fn.disableOnSubmit = function(options){
        options = options || {};

        // Init
        this.settings = init(options);

        // Enable chaining
        return this.each(function(idx, elem){

            $(elem).on('submit', function(){
                var $submit = $(this).find(':submit');
                $submit.prop('disabled', true);
                $submit.val($submit.attr(settings.attrDataWorking));
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
    $('[data-disable-on-submit]').disableOnSubmit();

})(jQuery, window, document);