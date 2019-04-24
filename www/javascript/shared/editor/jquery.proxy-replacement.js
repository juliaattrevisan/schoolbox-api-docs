(function() {

    /**
     * Replace all placeholders with html elements
     */
    $.fn.schoolboxResource_ProxyReplacement = function() {
        $.each($('#content').find('[data-schoolboxresource-type]'), function (index, element) {
            var type = $(element).attr('data-schoolboxresource-type');
            var thumbnail = $(element).attr('data-schoolboxresource-thumbnail');
            var style = $(element).attr('style');
            var classes = $(element).attr('class');
            var fullwidth = $(element).attr('data-schoolboxresource-fullwidth');

            var width = $(element).attr('data-schoolboxresource-width');
            var height = $(element).attr('data-schoolboxresource-height');

            var source = $(element).attr('data-schoolboxresource-source');


            $(element).replaceWith($.fn.schoolboxResource_MediaPlayerReplacement({
                'style': style,
                'width': width,
                'height': height,
                'thumbnail': thumbnail,
                'classes': classes,
                'source': source,
                'type': type,
                'fullwidth': fullwidth
            }));
        });

        /* Replace all with mejs
         */
        $('#content').find('[data-schoolboxmedia-replacement]').mediaelementplayer();
    }

    $.fn.schoolboxResource_MediaPlayerReplacement = function (params) {

        var style = (params['style'] !== undefined) ? params['style'] : '';
        var width = (params['width'] !== undefined) ? params['width'] : '';
        var height = (params['height'] !== undefined) ? params['height'] : '';
        var thumbnail = (params['thumbnail'] !== undefined) ? params['thumbnail'] : '';
        var classes = (params['classes'] !== undefined) ? params['classes'] : '';
        var source = (params['source'] !== undefined) ? params['source'] : '';
        var fullwidth = (params['fullwidth'] !== undefined) ? params['fullwidth'] : 'false';
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
            return '<audio data-schoolboxmedia-replacement controls preload="none" style="' + style + ';width:100%;height:30px;border:0px;">' +
                '<source src="' + source + '" type="audio/mpeg">' +
                '</audio>';
        }
    }
    
}(jQuery));
