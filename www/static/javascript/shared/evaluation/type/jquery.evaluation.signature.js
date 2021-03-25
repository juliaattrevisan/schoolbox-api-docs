(function($) {

	/**
	 * Constructor
	 *
	 * This is slightly different as it saves immediately upon instantiation
	 * This is used in a callback to SignaturePad
	 *
	 * @param options
	 */
	$.fn.evaluationSignature = function(options) {
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

			return $.extend(true,defaults, derived, options);
		}

		/**
		 * Initialise tiny
		 */
		function initChange() {
			$.fn.evaluationShort.saveOne(config.questionForm);
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 * @returns jqXHR
	 */
	$.fn.evaluationSignature.saveOne = function(element) {
		return $.ajax({
			type: 'post',
			url: $(element).attr('action'),
			data: $(element).serialize(),
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
	$.fn.evaluationSignature.getValidationRules = function() {
		return {
			element: 'input[name=answer]',
			rules: {
				required: true,
				minlength: 3,

				/* Custom error messages can be programatically entered this way
				 */
				messages: {
					required: 'Please sign',
					minlength: 'Please sign'
				}
			},
			errorContainer: '[data-evaluationerrors="1"]'
		}
	}
}(jQuery));