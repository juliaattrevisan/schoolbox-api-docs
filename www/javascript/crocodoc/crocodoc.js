(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.crocodoc = function(options) {

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		crocodoc();

		/**
		 * Initialise various configured params and determine a bunch
		 * of ids and stuff that we'll probably use elsewhere
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				viewElement: element,
				submissionId: 0,
				view: '',
				download: '',
				downloadElement: null
			}

			var derived = {
				submissionId: parseInt(options.submissionId),
				url: {
					view: '/submission/inline/iframe?submissionId=' + parseInt(options.submissionId),
					download: '/submission/inline/download/' + parseInt(options.submissionId)
				}
			}

			var merged = $j.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Start crocodoc inline marking
		 */
		function crocodoc() {
			$.getJSON(config.url.view, function(data) {
				$(config.viewElement).attr('src', data.url);
				$(config.downloadElement).attr('href', config.url.download);
			});
		}
	}

	$.fn.crocodocNavigator = function(options) {

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		renderSelect();

		/**
		 * Initialise various configured params and determine a bunch
		 * of ids and stuff that we'll probably use elsewhere
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				element: element,
				items: [],
				currentId: 0
			}

			var derived = {
			}

			var merged = $j.extend(defaults, derived, options);
			merged.items = jQuery.parseJSON(merged.items)

			return merged;
		}

		/**
		 * Render select box
		 */
		function renderSelect() {
			var allOptions = '';

			for (var i = 0; i < config.items.length; i++) {
				var label = config.items[i].navigation.label;
				var value = config.items[i].navigation.value;

				var option = '<option value="'+ value +'">'+ label +'</option>';

				allOptions += option;
			}

			var select = '<select class="context-selector">' + allOptions + '</select>';

			config.element.append(select);
		}
	}
}(jQuery));