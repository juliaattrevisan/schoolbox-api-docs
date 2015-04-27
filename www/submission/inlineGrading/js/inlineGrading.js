(function($) {

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

		/* Empty out the div
		 */
		//config.element.html('');

		/* Render controls
		 */
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
				useDisabled: true,

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

			var merged = $.extend(defaults, derived, options);
			merged.currentId = parseInt(merged.currentId);

			/* Force parse of array as string into actual array structure
			 */
			merged.disabled = JSON.parse(merged.disabled);

			/* Do some preprocessing first by indicating which people
			 * can be selected from the dropdown
			 */
			merged.items = setSelectableElements(merged.useDisabled, merged.items, merged.disabled);

			return merged;
		}

		/**
		 * Mark all the people who can be selected
		 *
		 * @returns {Array}
		 */
		function setSelectableElements(useDisabled, items, disabledItems) {
			var selectableElements = [];

			/* Filter out everyone who has not submitted
			 */
			for (var i = 0; i < items.length; i++) {
				var updatedObject = items[i];

				if (useDisabled == true) {
					if (jQuery.inArray(updatedObject.navigation.id, disabledItems) < 0) {
						updatedObject.selectable = true;
					}
					else {
						updatedObject.selectable = false;
					}
				}
				else {
					updatedObject.selectable = true;
				}

				selectableElements.push(updatedObject);
			}

			return selectableElements;
		}

		/**
		 * Render previous link
		 *
		 * This is a function of the disabled people, who have not
		 * yet submitted anything
		 */
		function renderPrevious() {
			var isFinished = false;
			var previous = false;
			var index = -1;

			/* Find the current element
			 */
			for (var i = 0; i < config.items.length; i++) {
				if (config.currentId == config.items[i].navigation.id) {

					index = i;

					/* Construct link
					 */
					if (previous) {
						var value = previous.navigation.value;
						var label = previous.navigation.label;

						var option = '<a class="previous-item" href="'+ value +'" alt="'+ label +'" title="'+ label+'">&lsaquo;</a>';

						config.element.append(option);
						config.previousItemCallback();

						/* Jump out
						 */
						isFinished = true;
					}
				}

				/* Mark previous
				 */
				if (config.items[i].selectable == true) {
					previous = config.items[i];
				}
			}
		}

		/**
		 * Render select box
		 */
		function renderSelect() {
			//var allOptions = '';
            //
			//for (var i = 0; i < config.items.length; i++) {
			//	var label = config.items[i].navigation.label;
			//	var value = config.items[i].navigation.value;
            //
			//	/* Preselected dropdown element
			//	 */
			//	var selected = '';
            //
			//	if (config.currentId == config.items[i].navigation.id) {
			//		var selected = 'selected="selected"';
			//	}
            //
			//	/* Disable dropdown elements for those who haven't
			//	 * submitted (optional)
			//	 */
			//	var disabled = '';
            //
			//	/* Only disable items when this flag is enabled
			//	 */
			//	if (config.useDisabled == true) {
			//		if (jQuery.inArray(config.items[i].navigation.id, config.disabled) > -1) {
			//			disabled = 'disabled';
			//		}
			//	}
            //
			//	var option = '<option ' + disabled + ' value="'+ value +'"'+ selected +'>'+ label +'</option>';
            //
			//	/* Stack up
			//	 */
			//	allOptions += option;
			//}
            //
			//var select = '<select class="context-select">' + allOptions + '</select>';
            //
			//config.element.append(select);
			config.selectItemCallback();
		}

		/**
		 * Render next link
		 *
		 * This is a function of the disabled people, who have not
		 * yet submitted anything
		 */
		function renderNext() {
			var isFinished = false;
			var useNext = false;

			/* Find the current element
			 */
			for (var i = 0; (i < config.items.length) && !isFinished; i++) {

				/* Decide whether to use the next selectable element
				 */
				if (config.currentId == config.items[i].navigation.id) {
					useNext = true;
				}
				else if (useNext && config.items[i].selectable == true) {

					/* Construct link
					 */
					var value = config.items[i].navigation.value;
					var label = config.items[i].navigation.label;

					var option = '<a class="next-item" href="'+ value +'" alt="'+ label +'" title="'+ label+'">&rsaquo;</a>';

					config.element.append(option);
					config.nextItemCallback();

					/* Jump out
					 */
					isFinished = true;
				}
			}
		}
	}
}(jQuery));