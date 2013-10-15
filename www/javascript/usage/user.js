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
						numUsers: config.numUsers
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
						numUsers: config.numUsers
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
								<th><a class="control" rel="firstname">Firstname</a></th>\
								<th><a class="control" rel="lastname">Lastname</a></th>\
								<th><a class="control" rel="name">Group</a></th>\
								<th><a class="control" rel="bytes">Usage</a></th>\
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

					var pagination = '';

					for(var i = 0; i < Math.ceil(config.numUsers/config.numPerPage); i++) {
						pagination += '<li><a rel="' + i +'">' + (i+1) + '</a></li>';
					}

					/* Table and paginator
					 */
					var inject = html.replace('{CONTENTS}', datasets).replace('{PAGINATION}', pagination);

					config.element.html(inject);

					applySortorder();
					applyPaginator();
				}
			});
		}
	}
}(jQuery));
