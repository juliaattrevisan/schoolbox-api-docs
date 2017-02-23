(function($) {

	/**
	 * Constructor
	 */
	$.fn.usageTabulator= function(options) {
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
				title: '',
				numPerPage: 15,
				columns: [
					{column: 'firstname', label: 'Firstname'},
					{column: 'lastname', label: 'Lastname'},
					{column: 'name', label: 'Role'},
					{column: 'bytes', label: 'Usage'}
				],
				ascendingHtml:  '<img src="/images/core/tablesorter/sort-ascending.gif">',
				descendingHtml: '<img src="/images/core/tablesorter/sort-descending.gif">'
			}

			var derived = {
				element: element
			}

			var merged = $.extend(defaults, derived, options);

			/* Dynamically append params
			 */
			merged.url = merged.url + '&orderBy=' + options.orderBy + '&order=' + options.order + '&index=' + options.index + '&numPerPage=' + options.numPerPage;
			merged.index = parseInt(merged.index);

			return merged;
		}

		/**
		 * Apply sortorder controls
		 */
		function applySortorder() {
			$.each(config.element.find('[data-control]'), function(index, element) {
				$(element).click(function(event) {
					event.preventDefault();
                    var order = 'ASC';

					/* Toggle ordering upon click
					 */
					if (config.order == 'ASC') {
						order = 'DESC';
					}

					config.element.usageTabulator({
						url: config.url,
						title: config.title,
						columns: config.columns,
						orderBy: $(this).attr('rel'),
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
			$.each(config.element.find('li a'), function(index, element) {
				$(element).click(function(event) {
					event.preventDefault();

					config.element.usageTabulator({
						url: config.url,
                        title: config.title,
						columns: config.columns,
						orderBy: config.orderBy,
						order: config.order,
						index: parseInt($(this).attr('rel')),
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
			$.ajax({
				url: config.url,
				method: 'GET',
				dataType: 'text',
				success: function(datasets) {

					/* Manufacture a table only if there is data
					 */
					if (datasets.length) {
						makeTable(datasets);
					}
					else {
						noTable();
					}
				}
			});
		}

		/**
		 * Make indicator arrows for table headings
		 *
		 * @param currentColumn
		 * @returns {string}
		 */
		function makeIndicatorArrows(currentColumn) {
			var indicator = '';

			/* Asc/desc indicator arrows
			 */
			if (currentColumn == config.orderBy) {
				indicator = config.ascendingHtml;

				if (config.order == 'DESC') {
					indicator = config.descendingHtml;
				}
			}

			return indicator;
		}

		/**
		 * Make table headings
		 *
		 * @returns {string}
		 */
		function makeTableHeadings() {
			var thead = '';

			for (var i = 0; i < config.columns.length; i++) {
				var current = config.columns[i];
				var indicator = makeIndicatorArrows(current.column);

				thead += "<th><a href='#' data-control rel='"+ current.column +"'>"+ current.label + indicator + "</a></th>"
			}

			return thead;
		}

		/**
		 * Make pagination
		 */
		function makePagination() {
			var pagination = '';
			var numPages = Math.floor(config.numTotal/config.numPerPage);

			/* Previous Navigator
			 */
			if (config.index > 0) {
				pagination += '<li><a href="#" rel="' + (config.index-1) +'">&lsaquo;</a></li>';
			}

			/* Page Navigator
			 */
			for (var i = 0; i < numPages; i++) {
				var active = '';

				if (i == config.index) {
					active = 'active';
				}

				pagination += '<li class="' + active + '" ><a href="#" rel="' + i +'">' + (i+1) + '</a></li>';
			}

			/* Next Navigator
			 */
			if (config.index+1 < numPages) {
				pagination += '<li><a href="#" rel="' + (config.index+1) +'">&rsaquo;</a></li>';
			}

			return pagination;
		}

		/**
		 * Manufacture a table
		 *
		 * @param datasets
		 */
		function makeTable(datasets) {
			var html = '\
				<h2 class="subheader">{TITLE}</h2>\
				<table>\
					<thead>\
						{THEAD}\
					</thead>\
					<tbody>\
					{CONTENTS}\
					</tbody>\
				</table>\
				<ul class="pagination">\
					{PAGINATION}\
				</ul>\
			';

			/* Table, table heading and paginator
			 */
			html = html.replace('{TITLE}', config.title);
			html = html.replace('{CONTENTS}', datasets);
			html = html.replace('{THEAD}', makeTableHeadings());
			html = html.replace('{PAGINATION}', makePagination());

			config.element.html(html);

			applySortorder();
			applyPaginator();
		}

		/**
		 * This is used when there is no data
		 */
		function noTable() {
			config.element.html('<h3>' + config.title + '</h3><p>This user has no files.</p>');
		}
	}
}(jQuery));
