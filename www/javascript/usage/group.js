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

			/* Find the largest one
			 * Every bar will be represented as a fraction of this
			 */
			for (var i = 0, max = 0; i < datasets.length; i++) {
				var value = parseInt(datasets[i].data);

				if (value > max) {
					max = value;
				}
			}

			/* Bar graph everything
			 */
			var output = '<h2>{TITLE}</h2>';

			for (var i = 0; i < datasets.length; i++) {
				var used = parseInt(datasets[i].data);

				if (max > 0) {
					var percentage = Math.round(100*used/max).toString();
				}
				else {
					var percentage = 0;
				}

				var html = '\
					<div>\
						{BAR}\
						<p>\
							{NAME}\
						</p>\
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
			config.element.html('<h2>' + config.title + '</h2><p><strong>This user has no files.</strong></p>');
		}
	}
}(jQuery));
