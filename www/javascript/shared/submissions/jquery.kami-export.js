/**
 * Generates a Kami document export, then downloads it
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {

    var pluginName = 'kamiExport';
    var defaults   = {};

    /**
     * @param {dom}    element
     * @param {object} options
     * @param {jQuery} options.toast
     * @constructor
     */
    function KamiExport(element, options) {
        var kamiExport    = this;
        this.element      = $(element);
        this.options      = $.extend(true, {}, defaults, options);

        // validate a couple things
        if (!options || !options.toast) {
            throw "'toast option required and must be a jQuery object'";
        }

        // install listeners
        this.element.on('click', function (event) {
            var $downloadButton = $(this);
            if($downloadButton.is('[download]')){
                // Permit the browser to perform a normal synchronous download
                return true;
            }
            $downloadButton.attr('disabled', true);
            event.preventDefault();
            kamiExport.options.toast.toastActivate({text: 'Requesting export...'});
            $.get($downloadButton.attr('href'))
                .done(function (data) {
                    kamiExport.options.toast.toastTimeout({
                        text: 'Export successfully generated. Downloading...',
                        css: 'success'
                    });
                    $downloadButton.attr('disabled', false);
                    /** @var {string} data.fileUrl */
                    $downloadButton.attr('href', data.fileUrl).attr('download', '').get(0).click();
                })
                .fail(function () {
                    $downloadButton.attr('disabled', false);
                    kamiExport.options.toast.toastTimeout({
                        text: 'Failed to generate export',
                        css: 'alert'
                    });
                });
            return false;
        });
    }

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new KamiExport(this, options));
            }
        });
    }
})(jQuery, window, document);
