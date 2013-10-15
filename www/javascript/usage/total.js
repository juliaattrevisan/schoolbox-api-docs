(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageTotal = function() {
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
				url: '/adminv2/usage/ajax?type=total'
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
					$j.plot($j(config.element), datasets.data, {
						series: {
							stack: true,
							lines: {
								show: false,
								fill: true,
								steps: false
							},
							bars: {
								show: true,
								barWidth: 0.6
							}
						},
						xaxis: {
							show: false
						},
						yaxis: {
							show: false
						}
					});
				}
			});
		}
	}
}(jQuery));
