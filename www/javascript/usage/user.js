(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageUser= function(options) {
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
				url: '/adminv2/usage/ajax?type=user',
				numPerPage: 15
			}

			var derived = {
				element: element,
				url: defaults.url + '&orderBy=' + options.orderBy + '&order=' + options.order + '&index=' + options.index + '&numPerPage=' + options.numPerPage
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
				dataType: 'text',
				success: function(datasets) {

					/* Remove loader
					 */
					config.element.removeClass('loader');

					var html = '\
						<table>\
							<thead>\
								<th><a class="control" rel="firstname">Firstname</a></th>\
								<th><a class="control" rel="lastname">Lastname</a></th>\
								<th><a class="control" rel="name">Group</a></th>\
								<th><a class="control" rel="bytes">Usage</a></th>\
							</thead>\
							<tbody>\
							{CONTENTS}\
							</tbody>\
						</table>\
						<ul>\
							<li><a rel="1">1</a></li>\
							<li><a rel="2">2</a></li>\
							<li><a rel="3">3</a></li>\
						</ul>\
					';

					config.element.html(html.replace('{CONTENTS}', datasets));

					/* Apply sortorder controls
					 */
					$j.each(config.element.find('a.control'), function(index, element) {
						$j(element).click(function() {

							/* Toggle ordering upon click
							 */
							if (config.order == 'ASC') {
								var order = 'DESC';
							}
							else {
								var order = 'ASC';
							}

							config.element.usageUser({
								orderBy: $j(this).attr('rel'),
								order: order,
								index: 0,
								numPerPage: config.numPerPage
							});
						});
					});
				}
			});
		}
	}
}(jQuery));
