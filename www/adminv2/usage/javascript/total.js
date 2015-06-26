(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.usageTotal = function(options) {
		var config = init(options, this);

		graph();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {console.log(options);
            var defaults = {
                element: element,

                /* Graph title
                 */
                title: '',

                /* The ajax url
                 */
                url: ''
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Graph it
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

                    /* Progress bars
                     */
                    var used = datasets[0].data;
                    var total = datasets[1].data + used;
                    var percentage = Math.round(100*used/total).toString();

                    var html = $('<div><h3></h3><p></p></div>');
                    html.find('h3').text(config.title);
                    html.find('p')
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
