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

		renderPrevious();
		renderSelect();
		renderNext();

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
				currentId: 0,

				/* Callbacks
				 */
				selectItemCallback: function() {},
				previousItemCallback: function() {},
				nextItemCallback: function() {}
			}

			var derived = {
				items: [],
				direct: []
			}

			var merged = $j.extend(defaults, derived, options);
			merged.items = jQuery.parseJSON(merged.items);
			merged.direct = jQuery.parseJSON(merged.direct);

			return merged;
		}

		/**
		 * Render previous link
		 */
		function renderPrevious() {
			if (config.direct.previous !== null) {
				var label = config.direct.previous.navigation.label;
				var value = config.direct.previous.navigation.value;

				var option = '<a href="'+ value +'">'+ label +'</a>';

				config.element.append(option);
				config.previousItemCallback();
			}
		}

		/**
		 * Render select box
		 */
		function renderSelect() {
			var allOptions = '';

			for (var i = 0; i < config.items.length; i++) {
				var label = config.items[i].navigation.label;
				var value = config.items[i].navigation.value;

				/* Preselected dropdown element
				 */
				var selected = '';

				if (config.currentId == config.items[i].navigation.id) {
					var selected = 'selected="selected"';
				}

				var option = '<option value="'+ value +'"'+ selected +'>'+ label +'</option>';

				allOptions += option;
			}

			var select = '<select class="context-select">' + allOptions + '</select>';

			config.element.append(select);
			config.selectItemCallback();
		}

		/**
		 * Render next link
		 */
		function renderNext() {
			if (config.direct.next !== null) {
				var label = config.direct.next.navigation.label;
				var value = config.direct.next.navigation.value;

				var option = '<a href="'+ value +'">'+ label +'</a>';

				config.element.append(option);
				config.nextItemCallback();
			}
		}
	}
}(jQuery));