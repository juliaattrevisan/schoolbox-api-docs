(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageGroup = function(options) {
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

			/* Bar graph everything
			 */
			var output = '<h3>{TITLE}</h3>';

			/* Find the total amount
			 */
			var total = 0;

			for (var i = 0; i < datasets.length; i++) {
				total += parseInt(datasets[i].data);
			}

			/* Make every bar relative to max
			 */
			for (var i = 0; i < datasets.length; i++) {
				var used = parseInt(datasets[i].data);

				if (total > 0) {
					var percentage = Math.round(100*used/total).toString();
				}
				else {
					var percentage = 0;
				}

				var html = '\
					<div>\
						<h4>\
							{NAME}\
						</h4>\
						{BAR}\
					</div>\
				';

				var html = html.replace('{NAME}', datasets[i].label);
				var html = html.replace('{BAR}', $.fn.bargraphPlainAmount(used, percentage));

				output += html;
			}

			var output = output.replace('{TITLE}', config.title);

			config.element.html(output);
		}

		/**
		 * This is used when there is no data
		 */
		function noBars() {
			config.element.html('<h3>' + config.title + '</h3><p><strong>This user has no files.</strong></p>');
		}
	}
}(jQuery));
