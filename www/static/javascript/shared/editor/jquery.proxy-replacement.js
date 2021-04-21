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
     * Mass-restore all media placeholders that were hidden by
     * `$.fn.schoolboxResource_ReplaceAll`.
     *
     * This will remove the replacements, but they ought to be restored by
     * calling `$.fn.schoolboxResource_ReplaceAll` on the element again.
     *
     * @param selector
     * @return {Number} the number of placeholders which got restored
     */
    $.fn.schoolboxResource_RestoreAllPlaceholders = function(selector) {
        var count = 0;
        selector.find('[data-schoolboxmedia-replacement-container]').each(function () {
            if ($.fn.schoolboxResource_RestorePlaceholder(this)) {
                count++;
            }
        });
        return count;
    };


    /**
     * Apply all the placeholders that were found in element.
     *
     * This used to replace the placeholders completely: the inline editor
     * requires that the placeholder continues to exist (as the replacement
     * breaks the inline editor), so now it hides the placeholder and adds the
     * replacement after it.
     *
     * @param element
     */
    $.fn.schoolboxResource_ReplaceOne = function(element) {
        var replacement = $.fn.schoolboxResource_GenerateReplacement(element);

        if (replacement !== false) {
            $(element).css('display', 'none').after(replacement);
            return true;
        }

        return false;
    };


    /**
     * Restores the placeholder that was replaced by
     * `$.fn.schoolboxResource_ReplaceOne`.
     *
     * This will remove the replacement, and its video player.
     *
     * @param element
     * @returns {boolean}
     */
    $.fn.schoolboxResource_RestorePlaceholder = function(element) {
        var $element = $(element),
            $placeholder = $element.prev('[data-schoolboxresource-type]');
        if ($placeholder.length) {
            // Does this actually have a placeholder to restore?
            $placeholder.css('display', '');
            $element.find('.mejs__container').each(function () {
                var playerId = this.id;
                if (playerId && mejs.players && mejs.players[playerId]) {
                    mejs.players[playerId].remove();
                }
            });
            element.remove();
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
            return '<div data-schoolboxmedia-replacement-container style="max-width:' + maxwidth + style + '">' +
                '<video width="' + width + '" height="' + height + '" data-schoolboxmedia-replacement controls preload="none" ' +
                'poster="' + thumbnail + '" class="' + classes + '" style="width:100%;height:100%;">' +
                '<source src="' + source + '" type="video/mp4">' +
                '</video>' +
                '</div>';
        } else if (type === 'audio') {
            return '<div data-schoolboxmedia-replacement-container>' +
                '<audio data-schoolboxmedia-replacement controls preload="none" style="' + style + ';width:100%;height:40px;border:0px;">' +
                '<source src="' + source + '" type="audio/mpeg">' +
                '</audio>' +
                '</div>';
        }

        return false;
    };


    /**
     * Replace all media players with mejs
     * @param selector
     */
    $.fn.schoolboxResource_InitialiseMediaElementPlayer = function (selector) {
        $(selector).find('[data-schoolboxmedia-replacement]').each(function(index, element) {

            /* Use the natural rendering mechanism when full-width class is present
             */
            if ($(element).hasClass('full-width')) {
                $(element).mediaelementplayer();
            } else {

                /* Video dimensions exceed the container's maxwidth setting for some reason
                 * https://github.com/mediaelement/mediaelement/issues/42
                 *
                 * This was also encountered in storage.vue:46 in mounted()
                 *
                 * So I guess we can set it manually and explicitly here
                 */
                $(element).mediaelementplayer({
                    videoWidth: $(element).attr('width'),
                    videoHeight: $(element).attr('height')
                });
            }
        })
    };
}(jQuery));
