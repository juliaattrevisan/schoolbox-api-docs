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

			/* Find the total amount
			 */
			var total = 0;

			for (var i = 0; i < datasets.length; i++) {
				total += parseInt(datasets[i].data);
			}

			/* Make every bar relative to max
			 */
			var html = $('<h3></h3>').text(config.title);
			for (var i = 0; i < datasets.length; i++) {
				var used = parseInt(datasets[i].data);
                var percentage = 0;

				if (total > 0) {
					percentage = Math.round(100*used/total).toString();
				}
				else {
					percentage = 0;
				}

				var line = $('<div><h4></h4></div>');
				line.find('h4')
					.text(datasets[i].label)
					.after($.fn.bargraphPlainAmount(used, percentage));
				html.append(line);
			}
			config.element.html(html);
		}

		/**
		 * This is used when there is no data
		 */
		function noBars() {
			config.element.html('<h3>' + config.title + '</h3><p><strong>This user has no files.</strong></p>');
		}
	}
}(jQuery));
