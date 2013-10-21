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
				numPerPage: 15,
				columns: [
					{column: 'firstname', label: 'Firstname'},
					{column: 'lastname', label: 'Lastname'},
					{column: 'name', label: 'Group'},
					{column: 'bytes', label: 'Usage'}
				]
			}

			var derived = {
				element: element,
				url: defaults.url + '&orderBy=' + options.orderBy + '&order=' + options.order + '&index=' + options.index + '&numPerPage=' + options.numPerPage
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Apply sortorder controls
		 */
		function applySortorder() {
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
						numPerPage: config.numPerPage,
						numTotal: config.numTotal
					});
				});
			});
		}

		/**
		 * Paginate things
		 */
		function applyPaginator() {
			$j.each(config.element.find('li a'), function(index, element) {
				$j(element).click(function() {
					event.preventDefault();

					config.element.usageUser({
						orderBy: config.orderBy,
						order: config.order,
						index: $j(this).attr('rel'),
						numPerPage: config.numPerPage,
						numTotal: config.numTotal
					});
				});
			});
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
								{THEAD}\
							</thead>\
							<tbody>\
							{CONTENTS}\
							</tbody>\
						</table>\
						<ul class="paginator">\
							{PAGINATION}\
						</ul>\
						<div class="clearfix"></div>\
					';

					var thead = '';

					for (var i = 0; i < config.columns.length; i++) {
						var current = config.columns[i];

						thead += "<th><a class='control' rel='"+ current.column +"'>"+ current.label +"</a></th>"
					}

					var pagination = '';

					for(var i = 0; i < Math.ceil(config.numTotal/config.numPerPage); i++) {
						pagination += '<li><a rel="' + i +'">' + (i+1) + '</a></li>';
					}

					/* Table, table heading and paginator
					 */
					var inject = html.replace('{CONTENTS}', datasets).replace('{THEAD}', thead).replace('{PAGINATION}', pagination);

					config.element.html(inject);

					applySortorder();
					applyPaginator();
				}
			});
		}
	}
}(jQuery));
