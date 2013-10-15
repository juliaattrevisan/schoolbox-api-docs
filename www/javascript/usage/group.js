(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageGroup = function() {
		var config = init(this);

		plot();

		/**
		 * Init
		 *
		 * @param element
		 * @returns {*}
		 */
		function init(element) {
			var defaults = {
				url: '/adminv2/usage/ajax?type=group'
			}

			var derived = {
				element: element
			}

			var merged = $.extend(defaults, derived);

			return merged;
		}

		/**
		 * Plot graph
		 */
		function plot() {
			$j.ajax({
				url: config.url,
				method: 'GET',
				dataType: 'json',
				success: function(datasets) {

					/* Remove loader
					 */
					config.element.removeClass('loader');

					$j.plot(config.element, datasets, {
						bars: {
							show: true
						},
						xaxis: {
							show: true
						},
						yaxis: {
							show: true
						}
					});
				}
			});
		}
	}
}(jQuery));
