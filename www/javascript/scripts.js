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

    /**
     * Each skin does NOT have a slightly different header height or a different left navigation.
     * From now on, it's always placed relative to the clicked element.
     */
    $(document).on('click', '[data-role="event"]', function(event) {
        var currentObject = $(this);
        var bubble = $('.cal-bubble');
        bubble.find('.bottom, .bottom strong').removeAttr("style");
        bubble.find('.bottom').show();
        var dynamicHTML = currentObject.find('[data-relation="eventBubble"]').html();
        bubble.find('.middle').children('.js-data').html(dynamicHTML);
        var position = currentObject.offset();
        bubble.show();
        var annoyingHeight =
            bubble.find('.middle').outerHeight()
            + bubble.find('.top').outerHeight()
            + bubble.find('.bottom').outerHeight()

        // right edge lines up with where you clicked, or right edge of currentObject
        // bottom edge lines up with top edge of currentObject, hide pointer if it's at top of screen
        var left = Math.max(
            0,
            Math.min(
                event.pageX,
                position.left + currentObject.outerWidth()
            ) - bubble.outerWidth()
        );
        var top  = Math.max(0, position.top  - annoyingHeight);
        if (top === 0) {
            bubble.find('.bottom').hide();
        }
        bubble.css("left", left);
        bubble.css("top",  top);
    });

    /**
     * Close the calendar popup no matter what!
     */
    $(document).on('click', '.cal-bubble .close', function() {
        $(this).closest('.cal-bubble').hide();
    })
});
