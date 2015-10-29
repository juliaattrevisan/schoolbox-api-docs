$(function() {

    /**
     * Open rel="external" links in a new tab.
     */
    $(document).on('click', 'a[rel=external]', function() {
        $(this).attr('target','_blank');
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

	$("a[rel^='lightbox']").each(function() {
		var rel = $(this).attr('rel');

		if (!lightboxGroups[rel]) {
			lightboxGroups[rel] = [];
		}

		lightboxGroups[rel].push(this);
	});

	$.each(lightboxGroups, function(i,v) {
		$(v).magnificPopup({
			type: 'image',
			removalDelay: 300,
			mainClass: 'mfp-fade',
			gallery:{
				enabled:true
			}
		});
	});

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
