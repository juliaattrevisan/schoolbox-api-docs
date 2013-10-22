(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.usageTotal = function(options) {
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

					/* Use flot format to generate different bar graph
					 * after all
					 */
					var used = datasets.data[0].data[0][1];
					var total = datasets.data[1].data[0][1] + used;
					var percentage = Math.round(100*used/total).toString();

					var html = '\
						<div>\
							<h2>{TITLE}</h2>\
							<p>\
								{USED} / {TOTAL}\
							</p>\
							{BAR}\
						</div>\
					';

					var html = html.replace('{TITLE}', config.title);
					var html = html.replace('{USED}', $.fn.formatBytes(used));
					var html = html.replace('{TOTAL}', $.fn.formatBytes(total));
					var html = html.replace('{BAR}', $.fn.bargraph(percentage));

					config.element.html(html);
				}
			});
		}
	}
}(jQuery));
