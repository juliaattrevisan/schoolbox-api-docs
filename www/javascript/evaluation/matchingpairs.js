(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationMatchingPairs = function(options) {
		var config = init(options);

		initChange();

		/**
		 * Set up options
		 *
		 * @param options
		 * @returns {*}
		 */
		function init(options) {
			var defaults = {
				id: 0
			}

			var derived = {
				questionForm: $('#questionForm-' + options.id)
			}

			return $.extend(defaults, derived, options);
		}

		/**
		 * Save response upon change
		 */
		function initChange() {
			var rightList = $(config.questionForm).find('.evaluation-matchingpairs-right');

			rightList.disableSelection();

			$(config.questionForm).swappable({
				items: rightList.find('li'),
				cursorAt: {
					top:-1
				},
				stop: function() {
					$.fn.evaluationMatchingPairs.saveOne(config.questionForm);
				}
			});
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 */
	$.fn.evaluationMatchingPairs.saveOne = function(element) {
		$.ajax({
			type: 'post',
			url: $(element).attr('action'),
			data: $(element).serialize(),

			/* Ensure all forms submit before redirecting
			 * Do not upgrade jquery to 1.9 or you'll lose this
			 */
			async: false,

			/* Callback on success
			 */
			success: function(responseText) {
				console.log(responseText);
			}
		});
	}
}(jQuery));