(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationFillGap = function(options) {
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

			/* Prevent accidental form submission via Enter key
			 */
			config.questionForm.find('input').keydown(function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			});

			config.questionForm.find('input').change(function() {
				$.fn.evaluationFillGap.saveOne(config.questionForm);
			});
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 */
	$.fn.evaluationFillGap.saveOne = function(element) {
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
				var result = new Function('return ' + responseText);

				$.fn.evaluationPercentageComplete({
					result: result()
				});
			}
		});
	}

	/**
	 * Decide what the validation rules are for this particular
	 * question type. Optionally specify errorContainer if you want
	 * the validation messages to appear in a special location,
	 * otherwise leave it undefined for default behaviour
	 *
	 * @returns {{element: string, rules: {required: boolean, minlength: number}, errorContainer: string}}
	 */
	$.fn.evaluationFillGap.getValidationRules = function() {
	}
}(jQuery));