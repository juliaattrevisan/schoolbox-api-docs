(function() {

    /**
     * Replace all placeholders with html elements
     */
    $.fn.schoolboxResource_ProxyReplacment = function() {
        $.each($('#content').find('[data-schoolboxresource-type]'), function (index, element) {
            var type = $(element).attr('data-schoolboxresource-type');
            var thumbnail = $(element).attr('data-schoolboxresource-thumbnail');
            var style = $(element).attr('style');
            var classes = $(element).attr('class');
            var fullwidth = $(element).attr('data-schoolboxresource-fullwidth');

            /* Ridiculous quick fix
             */
            var source = $(element).attr('data-schoolboxresource-source').replace('type=browse', 'type=sbfs').replace('type=store', 'type=storage');

            /* Replace video/audio placeholder elements
             */
            if (type == 'video') {
                var width = $(element).attr('data-schoolboxresource-width');
                var height = $(element).attr('data-schoolboxresource-height');
                var maxwidth = width;

                /* Simulate fullwidth while retaining responsiveness
                 */
                if (fullwidth == 'true') {
                    maxwidth = '9999';
                }

                $(element).replaceWith('<div style="max-width:'+maxwidth+'px;'+style+'"><video width="'+width+'" height="'+height+'" data-schoolboxmedia-replacement controls preload="none" poster="' + thumbnail + '" class="' + classes + '" style="width:100%;height:100%;"><source src="' + source + '" type="video/mp4"></video></div>');
            }
            else if (type == 'audio') {
                $(element).replaceWith('<audio data-schoolboxmedia-replacement controls preload="none" style="' + style + ';width:100%;height:30px;border:0px;"><source src="' + source + '" type="audio/mpeg"></audio>');
            }
        });

        /* Replace all with mejs
         */
        $('#content').find('[data-schoolboxmedia-replacement]').mediaelementplayer();
    }
}(jQuery));