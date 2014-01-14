(function($) {

	/**
	 * Add something to crocodoc for annotations
	 *
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.inlineGradingRubrics = function(options) {

		/* Optional arguments
		 */
		options = options || {}

		/* Store the config in this instance so private methods
		 * can access it, only if the chosen element exists
		 */
		if ($(this).length) {
			var config = init(options, this);

			/* Preselect existing values
			 */
			updateSelections();

			/* Add ability to change selections
			 */
			initSelectables();
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
				element: element,

				/* Tallied score goes here
				 */
				sum: '#sum'
			}

			var derived = {
				allRadios: element.find(':radio')
			}

			var merged = $j.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Add event handlers for selectable rubrics criteria elements
		 */
		function initSelectables() {

			/* Each criterion can be turned on by clicking it
			 */
			config.element.find('.criterion-value').click(function(){
				$(this).find(':radio').prop('checked', true);

				updateSelections();
			});
		}

		/**
		 * Update selected elements
		 */
		function updateSelections() {
			var sum = 0;

			/* Remove previously selected radios and decorations
			 */
			$.each(config.allRadios, function(index, element) {
				$(element).parent('.criterion-value').removeClass('focused');
			});

			/* Reapply styles to each criterion value td containing a
			 * checked radio element
			 */
			$.each(config.element.find(':checked'), function(index, element) {
				$(element).parent('.criterion-value').addClass('focused');

				sum += parseInt($(element).val());
			});

			/* Update total marks assigned by rubrics
			 */
			$('#sum').html(sum);
		}
	}
}(jQuery));