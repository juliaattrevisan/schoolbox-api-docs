(function() {
	$.fn.filterSelector = function(options) {
		options = options || {};

		var config = init(options, this);

		/* All checkboxes on by default
		 */
		applyDefaultCheckboxState();

		applyPopupToggle();
		applyCheckboxToggle();

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
				eventContainer: null
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
			$(config.element).click(function(event) {
				event.preventDefault();

				$(config.filterContainer).toggle();
			});
		}

		/**
		 * Turn events on/off based on checkbox selection
		 */
		function applyDefaultCheckboxState() {
			$.each($(config.filterContainer).find('.applyFilters'), function(index, element) {
				var attribute = $(this).val();

				$(element).attr('checked', true);
				$(config.eventContainer).find('.'+attribute).show();
			});
		}

		/**
		 * Turn events on/off based on checkbox selection
		 */
		function applyCheckboxToggle() {
			$.each($(config.filterContainer).find('.applyFilters'), function(index, element) {
				$(element).click(function(event) {
					var attribute = $(this).val();

					if ($(this).is(':checked')) {
						$(config.eventContainer).find('.'+attribute).show();
					}
					else {
						$(config.eventContainer).find('.'+attribute).hide();
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
					$(element).prev('h2').show();
				}
				else {
					$(element).prev('h2').hide();
				}
			});
		}
	}
})(jQuery);