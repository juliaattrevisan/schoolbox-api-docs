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
				enabled: true
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

if (!('stallSubmit' in $)) {
    /**
     * Attach a Deferred to the form, which will stall form submission until it completes
     *
     * Usage:
     *      var promise = $.get(someUrl);
     *      $('#myForm').stallSubmit(promise);
     *
     * If form.submit() is invoked while the AJAX is still pending, the form submit will be stalled
     * until the promise is resolved.
     *
     * @param {Deferred} deferred
     */
    $.stallSubmit = function (deferred) {
        var form  = this;
        var $form = $(form);

        if ('undefined' === typeof $form.data('stallSubmit')) {
            // no deferreds yet registered: register this one
            $form.data('stallSubmit', [ deferred ]);

            // register the callback
            $form.on('submit', function (e) {
                if (e.namespace && e.namespace == 'stallSubmit') {
                    return true;
                }
                // there are unresolved deferreds: do not submit yet
                e.preventDefault();

                $.when.apply(window, $form.data('stallSubmit')).done(function () {
                    // ok *now* you can submit
                    form.trigger('submit.stallSubmit')
                });
            });

        } else {
            // just append to the list
            $form.data('stallSubmit').push(deferred);
        }

    }
}

