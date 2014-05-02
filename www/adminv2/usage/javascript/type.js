(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageType= function(options) {
		var config = init(this, options);

		plot();

		/**
		 * Init
		 *
		 * @param element
		 * @param options
		 * @returns {*}
		 */
		function init(element, options) {
			var defaults = {
				url: '',
				title: ''
			}

			var derived = {
				element: element
			}

			var merged = $.extend(defaults, derived, options);

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
					config.element.html('');

					/* Only manufacture a pie if there is data
					 */
					if (datasets.length) {
						makePie(datasets);
					}
					else {
						noPie();
					}
				}
			});
		}

		/**
		 * Bake pie
		 *
		 * @param datasets
		 */
		function makePie(datasets) {
			config.element.css('height', '500px');

			$j.plot(config.element, datasets, {
				series: {
					pie: {
						show: true,
						label: {
							show: true,
							radius: 1,
							formatter: function(label, series) {
								return '<div style="font-size:11px; text-align:center; padding:2px; color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
							},
							background: {
								opacity: 0.8
							}
						}
					}
				},
				legend: {
					show: false
				}
			});

			config.element.prepend('<h3>' + config.title + '</h3>');
		}

		/**
		 * This is used when there is no data
		 */
		function noPie() {
			config.element.html('<h3>' + config.title + '</h3><p><strong>This user has no files.</strong></p>');
		}
	}
}(jQuery));
