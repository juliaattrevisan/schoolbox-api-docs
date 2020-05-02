(function() {

    /**
     * Replace all placeholders with html elements
     * By default this replaces everything in #content, our main container
     */
    $.fn.schoolboxResource_ProxyReplacement = function(identifier) {

        /* Replace all the placeholders with html fragments that a media player can understand
         */
        $.fn.schoolboxResource_ReplaceAll($(identifier));

        /* Initialise the media player on this container
         */
        $.fn.schoolboxResource_InitialiseMediaElementPlayer(identifier);
    };


    /**
     * Mass-replace all media placeholders
     *
     * @param selector  a jquery selection. This element contains all the placeholders
     *                  that should be replaced with a media player
     * @return int      the number of things that got replaced
     */
    $.fn.schoolboxResource_ReplaceAll = function(selector) {
        var count = 0;

        $.each(selector.find('[data-schoolboxresource-type]'), function (index, element) {
            var replaced = $.fn.schoolboxResource_ReplaceOne(element);

            if (replaced) {
                count++;
            }
        });

        return count;
    };


    /**
     * Replace all the placeholders that were found in element
     *
     * @param element
     */
    $.fn.schoolboxResource_ReplaceOne = function(element) {
        var replacement = $.fn.schoolboxResource_GenerateReplacement(element);

        if (replacement !== false) {
            $(element).replaceWith(replacement);

            return true;
        }

        return false;
    };


    /**
     * Get parameters from DOM
     * @param element
     * @returns {{thumbnail: *, maxwidth: string, classes: *, width: *, style: *, source: *, type: *, height: *, fullwidth: *}}
     */
    $.fn.schoolboxResource_GetParams = function(element) {
        var e = $(element);

        return {
            'style': (e.attr('style') !== undefined) ? e.attr('style') : '',
            'width': (e.attr('data-schoolboxresource-width') !== undefined) ? e.attr('data-schoolboxresource-width') : '',
            'height': (e.attr('data-schoolboxresource-height') !== undefined) ? e.attr('data-schoolboxresource-height') : '',
            'thumbnail': (e.attr('data-schoolboxresource-thumbnail') !== undefined) ? e.attr('data-schoolboxresource-thumbnail') : '',
            'classes': (e.attr('class') !== undefined) ? e.attr('class') : '',
            'source': (e.attr('data-schoolboxresource-source') !== undefined) ? e.attr('data-schoolboxresource-source') : '',
            'type': (e.attr('data-schoolboxresource-type') !== undefined) ? e.attr('data-schoolboxresource-type') : '',
            'fullwidth': (e.attr('data-schoolboxresource-fullwidth') !== undefined) ? e.attr('data-schoolboxresource-fullwidth') : '',
            'maxwidth': (e.attr('data-schoolboxresource-fullwidth') === 'true') ? '100%' : e.attr('data-schoolboxresource-width') + 'px'
        };
    };


    /**
     * Generate html compatible with media player
     *
     * @param element a jquery selection that we must examine
     * @returns {string|boolean}
     */
    $.fn.schoolboxResource_GenerateReplacement = function (element) {
        var params = $.fn.schoolboxResource_GetParams(element);

        var style = params['style'];
        var width = params['width'];
        var height = params['height'];
        var thumbnail = params['thumbnail'];
        var classes = params['classes'];
        var source = params['source'];
        var fullwidth = params['fullwidth'];
        var type = params['type'];
        var maxwidth = width + 'px;';

        if (fullwidth == 'true') {
            maxwidth = '100%';
        }

        if (typeof source == 'undefined') {
            source = '';
        }
        source = source.replace('type=browse', 'type=sbfs').replace('type=store', 'type=storage');

        if (type === 'video') {
            return '<div style="max-width:' + maxwidth + style + '">' +
                '<video width="' + width + '" height="' + height + '" data-schoolboxmedia-replacement controls preload="none" ' +
                'poster="' + thumbnail + '" class="' + classes + '" style="width:100%;height:100%;">' +
                '<source src="' + source + '" type="video/mp4">' +
                '</video>' +
                '</div>';
        } else if (type === 'audio') {
            return '<audio data-schoolboxmedia-replacement controls preload="none" style="' + style + ';width:100%;height:40px;border:0px;">' +
                '<source src="' + source + '" type="audio/mpeg">' +
                '</audio>';
        }

        return false;
    };


    /**
     * Replace all media players with mejs
     * @param selector
     */
    $.fn.schoolboxResource_InitialiseMediaElementPlayer = function (selector) {
        $(selector).find('[data-schoolboxmedia-replacement]').mediaelementplayer();
    };

}(jQuery));
