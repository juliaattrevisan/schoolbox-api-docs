$(function () {

    /**
     * Open rel="external" links in a new tab.
     */
    $(document).on('click', 'a[rel=external]', function () {
        $(this).attr('target', '_blank');
    });

    // jQuery datepickers: close on touchstart as well as mousedown
    // (Fastclick stops mousedown from triggering?)
    $(document).on('touchstart', $.datepicker._checkExternalClick);

    /**
     * Autoclear the main site search field (works without polyfill in all but IE9)
     */
    $('#search-field').placeholder();

    // Set up lightboxes globally, group galleries by lightbox relation.
    var lightboxGroups = {};

    $("a[rel^='lightbox']").each(function () {
        var rel = $(this).attr('rel');

        if (!lightboxGroups[rel]) {
            lightboxGroups[rel] = [];
        }

        lightboxGroups[rel].push(this);
    });

    $.each(lightboxGroups, function (i, v) {
        $(v).magnificPopup({
            type: 'image',
            removalDelay: 300,
            mainClass: 'mfp-fade',
            gallery: {
                enabled: true
            }
        });
    });


    /**
     * Hack into Phpdebugbar to add a twig templates button
     */
    var $debugBar = $('.phpdebugbar .phpdebugbar-header-right');
    if ($debugBar.length > 0) {
        // Add the button to the debug bar
        var $html =
            '<span class="phpdebugbar-indicator">' +
                '<a id="showTwigNames">' +
                    '<i class="phpdebugbar-fa phpdebugbar-fa-leaf" style="color:#555"></i>' +
                '</a>' +
                '<span class="phpdebugbar-tooltip">Twig templates</span>' +
            '</span>'
        ;
        $debugBar.find('.phpdebugbar-indicator').first().before($html);

        // Toggle twig template names showing on click of the button
        $('#showTwigNames').click(function () {
            if ($('.twigName').attr('hidden')) {
                $('.twigName').removeAttr('hidden');
            } else {
                $('.twigName').attr('hidden', true);
            }
        });
    }

//    /**
//     * Remove the layerX and layerY console log reports from Webkit.
//     */
//    var all = $.event.props,
//        len = all.length,
//        res = [];
//    while (len--) {
//      var el = all[len];
//      if (el != 'layerX' && el != 'layerY') res.push(el);
//    }
//    $.event.props = res
});
