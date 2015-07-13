// We used to use another library which consumed the $ variable.
// That library is long gone, so no need to release $ anymore.
$(function() {
    /**
     * Remove the layerX and layerY console log reports from Webkit.
     */
    var all = $.event.props,
        len = all.length,
        res = [];
    while (len--) {
      var el = all[len];
      if (el != 'layerX' && el != 'layerY') res.push(el);
    }
    $.event.props = res

    /**
     * Autoclear the main site search field (works without polyfill in all but IE9)
     */
    $('#search-field').placeholder();

    /**
     * Submit the form with a href links with the classes below
     */
    $(document).on('click', '.submit, .submit-this', function(event) {
        var form = $(this).closest('form');
        if (form.length > 0) {
            event.preventDefault();
            form.submit();
        }
    });

    /**
     * Open rel="external" links in a new tab.
     */
    $(document).on('click', 'a[rel=external]', function() {
        $(this).attr('target','_blank')
    })

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
	})
});
