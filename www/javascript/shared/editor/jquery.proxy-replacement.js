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

            /* Ridiculous quick fix
             */
            var source = $(element).attr('data-schoolboxresource-source').replace('type=browse', 'type=sbfs');

            /* Replace video/audio placeholder elements
             */
            if (type == 'video') {
                $(element).replaceWith('<video data-schoolboxmedia-replacement controls preload="none" poster="' + thumbnail + '" class="' + classes + '" style="' + style + '"><source src="' + source + '" type="video/mp4"></video>');
            }
            else if (type == 'audio') {
                $(element).replaceWith('<audio data-schoolboxmedia-replacement controls preload="none" style="' + style + ';width:100%;height:50px;border:0px;"><source src="' + source + '" type="audio/mpeg"></audio>');
            }
        });

        /* Replace all with mejs
         */
        $('#content').find('[data-schoolboxmedia-replacement]').mediaelementplayer();
    }
}(jQuery));