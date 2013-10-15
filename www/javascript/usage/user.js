(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageUser= function() {
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
				url: '/adminv2/usage/ajax?type=user'
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
				dataType: 'text',
				success: function(datasets) {

					/* Remove loader
					 */
					config.element.removeClass('loader');

					var html = '\
						<table> \
							<thead> \
								<th>Firstname</th> \
								<th>Lastname</th> \
								<th>Group</th> \
								<th>Usage</th> \
							</thead> \
							<tbody> \
							{CONTENTS}\
							</tbody> \
						</table> \
					';

					config.element.html(html.replace('{CONTENTS}', datasets));
				}
			});
		}
	}
}(jQuery));
