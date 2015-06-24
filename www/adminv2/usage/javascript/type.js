(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageType= function(options) {
		var config = init(this, options);

		graph();

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
		function graph() {
			$.ajax({
				url: config.url,
				method: 'GET',
				dataType: 'json',
				success: function(datasets) {

					/* Remove loader
					 */
					config.element.html('<div data-type-graph></div>');
                    config.element.removeClass('loader');

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
            $(config.element).find('[data-type-graph]').highcharts({
                chart: {
                    plotBackgroundColor: null,
                    plotBorderWidth: null,
                    plotShadow: false
                },
                title: {
                    text: config.title
                },
                tooltip: {
                    pointFormat: '<strong>{point.percentage:.1f}%</strong>'
                },
                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        cursor: 'pointer',
                        dataLabels: {
                            enabled: true,
                            format: '<strong>{point.name}</strong>: {point.percentage:.1f} %'
                        }
                    }
                },
                series: [{
                    type: 'pie',
                    data: datasets
                }]
            });
		}

		/**
		 * This is used when there is no data
		 */
		function noPie() {
			config.element.html('<h3>' + config.title + '</h3><p><strong>This user has no files.</strong></p>');
		}
	}
}(jQuery));
