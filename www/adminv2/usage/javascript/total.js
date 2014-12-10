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
			$.ajax({
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
					var used = datasets[0].data;
					var total = datasets[1].data + used;
					var percentage = Math.round(100*used/total).toString();

					var html = $('<div><h3></h3><p class="amount"></p></div>');
					html.find('h3').text(config.title);
					html.find('.amount')
						.text($.fn.formatBytes(used) + ' / ' + $.fn.formatBytes(total))
						.after($.fn.bargraph(percentage));

					config.element.html(html);
				}
			});
		}
	}

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.usageTotalPlain = function(options){
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

					var html = '\
						<div>\
							<h3>{TITLE}</h3>\
							<p class="amount">\
								{USED}\
							</p>\
						</div>\
					';

					var html = html.replace('{TITLE}', config.title);
					var html = html.replace('{USED}', $.fn.formatBytes(datasets.data));

					config.element.html(html);
				}
			});
		}
	}
}(jQuery));
