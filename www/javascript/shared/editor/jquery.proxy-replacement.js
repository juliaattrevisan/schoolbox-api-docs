(function() {

    /**
     * Replace all placeholders with html elements
     */
    $.fn.schoolboxResource_ProxyReplacment = function () {
        $.each($('[data-schoolboxresource-type]'), function (index, element) {
            var type = $(element).attr('data-schoolboxresource-type');
            var source = $(element).attr('data-schoolboxresource-source');
            var thumbnail = $(element).attr('data-schoolboxresource-thumbnail');
            var style = $(element).attr('style');

            /* Replace video/audio placeholder elements
             */
            if (type == 'video') {
                $(element).replaceWith('<video data-schoolboxmedia-replacement controls preload="none" poster="' + thumbnail + '" style="' + style + '"><source src="' + source + '" type="video/mp4"></video>');
            }
            else if (type == 'audio') {
                $(element).replaceWith('<audio data-schoolboxmedia-replacement controls preload="none" style="' + style + '"><source src="' + source + '" type="audio/mpeg"></audio>');
            }
        });
    }
}(jQuery));