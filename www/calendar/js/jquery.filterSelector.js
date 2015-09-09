(function() {
	$.fn.filterSelector = function(options) {
		options = options || {};

		var config = init(options, this);

		applyPopupToggle();
		applyCheckboxToggle();
        applyDefaultEventState();

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {

				/* The Filter button we just clicked on
				 */
				element: element,

				/* The element containing all the filter options
				 */
				filterContainer: null,

				/* The element containing all the events
				 */
				eventContainer: null,

				/* The element containing the export container
				 */
				exportContainer: null
			}

			var derived = {
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Add ability to toggle popup on/off
		 */
		function applyPopupToggle() {
			$('#applyFilters').click(function(event) {
				event.preventDefault();

				$(config.filterContainer).foundation('reveal','close');

			});
		}

		/**
		 * Turn events on/off based on checkbox selection
		 */
		function applyDefaultEventState() {
			$.each($(config.filterContainer).find('.applyFilters'), function(index, element) {
				var attribute = $(this).val();

				if ($(element).is(':checked')) {
                    $(config.eventContainer).find('.'+attribute).show();
                }
                else {
                    $(config.eventContainer).find('.'+attribute).hide();
                }
			});

            toggleHeaders();
		}

		/**
		 * Turn events on/off based on checkbox selection
		 */
		function applyCheckboxToggle() {
			$.each($(config.filterContainer).find('.applyFilters'), function(index, element) {
				$(element).change(function(event) {
					var attribute = $(this).val();
                    var checked   = $(this).is(':checked');
                    var base      = attribute.match(/^\D+/);
                    if (base.length) {
                        var ids = attribute.match(/\d+/g);
                        if (ids) {
                            ids.forEach(function(id) {
                                $(config.eventContainer).find('.' + base + id).toggle(checked);
                            });
                        }
                    }
					toggleHeaders();
				});
			});
		}

		/**
		 * Find out if the header should also hide, because
		 * there are no other events in this day
		 *
		 * @param attribute
		 */
		function toggleHeaders() {
			$.each($(config.eventContainer).find('ul'), function(index, element) {
				if ($(element).find('li').not(':hidden').length) {
					$(element).parent().prev('h2').show();
				}
				else {
					$(element).parent().prev('h2').hide();
				}
			});
		}
	}
})(jQuery);