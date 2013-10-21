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

					/* Find the largest one
					 * Every bar will be represented as a fraction of this
					 */
					for (var i = 0, max = 0; i < datasets.length; i++) {
						var value = parseInt(datasets[i].data[0][1]);

						if (value > max) {
							max = value;
						}
					}

					/* Bar graph everything
					 */
					var output = '<h2>Group Usage</h2>';

					for (var i = 0; i < datasets.length; i++) {
						var used = parseInt(datasets[i].data[0][1]);

						var percentage = Math.round(100*used/max).toString();

						var html = '\
							<div>\
								{BAR}\
								<p>\
									{NAME}\
								</p>\
							</div>\
						';

						var html = html.replace(/\{NAME\}/g, datasets[i].label);
						var html = html.replace(/\{BAR\}/g, $.fn.bargraphPlainAmount(used, percentage));

						output += html;
					}

					config.element.html(output);
				}
			});
		}
	}
}(jQuery));
