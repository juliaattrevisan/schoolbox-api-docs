/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, window, document, undefined) {

    var pluginName = 'svgInline',
        cache = {};

    var defaults = {
        selector: 'img[src$=".svg"]'
    };

    /**
     * Adapted from SO answer, courtesy of Drew Baker
     * @source http://stackoverflow.com/a/11978996
     *
     * This plugin only needs to exist until @fontface replaces SVG usage in Schoolbox
     *
     * @param element
     * @param options
     */
    function SvgInline(element, options) {
        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        $(this.element).find(this.options.selector).each(function() {
            var $img = $(this);

            getCached($img, function ($svg) {
                // Replace image with new SVG
                $img.replaceWith($svg.clone());
            });

        });
    }

    /**
     * @param {jQuery} $img
     * @param {Function} completeCallback
     */
    function getCached($img, completeCallback) {

        var imgID    = $img.attr('id'),
            imgClass = $img.attr('class'),
            imgURL   = $img.attr('src'),
            imgSize  = $img.attr('width');

        if (typeof cache[imgURL] == 'undefined') {
            // promise to retrieve and cache
            cache[imgURL] = $.get(imgURL, null, 'xml').then(function (data) {
                // Get the SVG tag, ignore the rest
                var $svg = $(data).find('svg');

                // Add replaced image's ID to the new SVG
                if (typeof imgID !== 'undefined') {
                    $svg = $svg.attr('id', imgID);
                }
                // Add replaced image's classes to the new SVG
                if (typeof imgClass !== 'undefined') {
                    $svg = $svg.attr('class', imgClass+' replaced-svg');
                }
                if (typeof imgSize !== 'undefined') {
                    $svg = $svg.attr('width', imgSize);
                }

                // Remove any invalid XML tags as per http://validator.w3.org
                $svg = $svg.removeAttr('xmlns:a');

                var df = $.Deferred();

                df.resolve($svg);

                return df;

            }, null).promise();
        }

        cache[imgURL].done(completeCallback);
    }



    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new SvgInline(this, options));
            }
        });
    }

})(jQuery, window, document);