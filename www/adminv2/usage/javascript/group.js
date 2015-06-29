(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageGroup = function(options) {
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
					config.element.removeClass('loader');

					/* Only manufacture a bar if there is data
					 */
					if (datasets.length) {
						var hasData = false;

						for (var i = 0; i < datasets.length; i++) {
							if (datasets[i].data > 0) {
								hasData = true;
							}
						}

						if (hasData) {
							makeBars(datasets);
						}
						else {
							noBars();
						}
					}
				}
			});
		}

		/**
		 * Manufacture a bars
		 *
		 * @param datasets
		 */
		function makeBars(datasets) {
            var allTooltips = [];
            var allCategories = [];
            var allSeriesData = [];
            var i

			/* Find the total amount
			 */
			var total = 0;

			for (i = 0; i < datasets.length; i++) {
				total += parseInt(datasets[i].data);
			}

            /* Make every bar relative to max
             */
			for (i = 0; i < datasets.length; i++) {
				var used = parseInt(datasets[i].data);
                var percentage = 0;

				if (total > 0) {
					percentage = Math.round(100*used/total);
				}
				else {
					percentage = 0;
				}

                allTooltips.push($.fn.formatBytes(used) + ' / ' + $.fn.formatBytes(total));
                allCategories.push(datasets[i].label);
                allSeriesData.push(percentage);
			}

            $(config.element).serverUsage({
                title: config.title,
                height: 350,
                categories: allCategories,
                tooltip: {
                    enabled: true,
                    formatter: function() {
                        return allTooltips[this.point.x];
                    }
                },
                series: [
                    {
                        data: allSeriesData
                    }
                ],
                formatter: function() {
                    var str = '';

                    if (this.y > 0) {
                        str = this.y + '%';
                    }

                    return str;
                }
            });
		}

		/**
		 * This is used when there is no data
		 */
		function noBars() {
			config.element.html('<h3>' + config.title + '</h3><p>This user has no files.</p>');
		}
	}
}(jQuery));
