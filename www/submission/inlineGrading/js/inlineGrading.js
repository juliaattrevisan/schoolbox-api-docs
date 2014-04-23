(function($) {

	/**
	 * Add something to crocodoc for annotations
	 *
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.inlineGrading = function(options) {

		/* Store the config in this instance so private methods
		 * can access it, only if the chosen element exists
		 */
		if ($(this).length) {
			var config = init(options, this);

			inlineGrading();
		}

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
				errorElement: null,
				boxId: 0,
				submissionId: 0
			}

			var derived = {
				submissionId: parseInt(options.submissionId),
				url: {
					view: '/submission/inlineGrading/iframe/' + parseInt(options.submissionId)
				}
			}

			var merged = $j.extend(defaults, derived, options);
			merged.boxId = parseInt(merged.boxId);
			merged.submissionId = parseInt(merged.submissionId);

			return merged;
		}

		/**
		 * Start crocodoc inline marking
		 */
		function inlineGrading() {
			$.getJSON(config.url.view, function(data) {

				/* If data is empty, everything is ok
				 * Otherwise it's got errors in
				 */
				if (data.errors !== undefined && data.errors.length) {
					$(config.errorElement).append(generateErrorString(data.errors));
					$(config.errorElement).show();
					$(config.viewElement).remove();
				}
				else {
					$(config.viewElement).attr('src', data.url);
				}
			});
		}

		/**
		 * A generic return of errors
		 *
		 * @param data
		 * @returns {string}
		 */
		function generateErrorString(data) {
			var errorString = '';

			for (var i = 0; i < data.length; i++) {
				errorString += '<li>' + data[i] + '</li>';
			}

			return '<ul>' + errorString + '</ul>';
		}
	}

	/**
	 * A navigator that rotates you between people
	 *
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.inlineGradingNavigator = function(options) {

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
				disabled: [],
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
			merged.disabled = jQuery.parseJSON(merged.disabled);
			merged.direct = jQuery.parseJSON(merged.direct);
			merged.currentId = parseInt(merged.currentId);

			return merged;
		}

		/**
		 * Find all the people who can be selected
		 *
		 * @returns {Array}
		 */
		function findSelectableElements() {
			var selectableElements = new Array();

			/* Filter out everyone who has not submitted
			 */
			for (var i = 0; i < config.items.length; i++) {
				if (jQuery.inArray(config.items[i].navigation.id, config.disabled) < 0) {
					selectableElements.push(config.items[i].navigation);
				}
			}

			return selectableElements;
		}

		/**
		 * Find the selectable ids from selectableElements
		 *
		 * @param selectableElements
		 */
		function findSelectableIds(selectableElements) {
			var selectableIds = [];

			for (var i = 0; i < selectableElements.length; i++) {
				selectableIds.push(selectableElements[i].id);
			}

			return selectableIds;
		}

		/**
		 * Render previous link
		 *
		 * This is a function of the disabled people, who have not
		 * yet submitted anything
		 */
		function renderPrevious() {

			/* Direct 1:1 correspondence
			 */
			var selectableElements = findSelectableElements();
			var selectableIds = findSelectableIds(selectableElements);

			/* If there is only one selectable element,
			 * previous should just go away
			 *
			 * Otherwise find the element prior to the current
			 */
			if (selectableElements.length > 1) {
				var index = $.inArray(config.currentId, selectableIds);

				/* Not at the start of the list
				 */
				if (index > 0) {
					var previous = selectableElements[index-1];

					/* Construct link
					 */
					var value = previous.value;
					var label = previous.label;

					var option = '<a class="previous-item" href="'+ value +'" alt="'+ label +'" title="'+ label+'">&lsaquo;</a>';

					config.element.append(option);
					config.previousItemCallback();
				}
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

				/* Disable dropdown elements for those who haven't
				 * submitted (optional)
				 */
				var disabled = '';

				if (jQuery.inArray(config.items[i].navigation.id, config.disabled) > -1) {
					disabled = 'disabled';
				}

				var option = '<option ' + disabled + ' value="'+ value +'"'+ selected +'>'+ label +'</option>';

				/* Stack up
				 */
				allOptions += option;
			}

			var select = '<select class="context-select">' + allOptions + '</select>';

			config.element.append(select);
			config.selectItemCallback();
		}

		/**
		 * Render next link
		 *
		 * This is a function of the disabled people, who have not
		 * yet submitted anything
		 */
		function renderNext() {

			/* Direct 1:1 correspondence
			 */
			var selectableElements = findSelectableElements();
			var selectableIds = findSelectableIds(selectableElements);

			/* If there is only one selectable element,
			 * previous should just go away
			 *
			 * Otherwise find the element prior to the current
			 */
			if (selectableElements.length > 1) {
				var index = $.inArray(config.currentId, selectableIds);

				/* Not at the end of the list
				 */
				if (index < selectableElements.length-1) {
					var next = selectableElements[index+1];

					/* Construct link
					 */
					var value = next.value;
					var label = next.label;

					var option = '<a class="next-item" href="'+ value +'" alt="'+ label +'" title="'+ label+'">&rsaquo;</a>';

					config.element.append(option);
					config.nextItemCallback();
				}
			}
		}
	}
}(jQuery));