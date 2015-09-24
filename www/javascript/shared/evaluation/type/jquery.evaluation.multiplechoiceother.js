(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationMultipleChoiceOther = function(options) {
		var config = init(options);

		//initSelected();
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
			config.questionForm.find('input[type=checkbox]').change(function() {
				/* Clear Other value if unchecked
				 */
				var element = $(this);
				var isChecked = element.prop('checked');
				if (!isChecked && element.data('role') == 'other-checkbox') {
					config.questionForm.find('[data-role="other-answer"]').val('');
				}
				$.fn.evaluationMultipleChoiceOther.saveOne(config.questionForm);
			});

			/* Prevent accidental form submission via Enter key
			 */
			config.questionForm.find('input[type=text]').keydown(function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			});

			/* Detect change in Other field
			 * If the Other has content, check the Other Checkbox, and highlight
			 */
			config.questionForm.find('[data-role="other-answer"]').change(function() {
				config.questionForm.find('[data-role="other-checkbox"]').prop('checked', $(this).val().length);
				$.fn.evaluationMultipleChoiceOther.saveOne(config.questionForm);
			});
		}
	};

	/**
	 * Save individual form contents
	 *
	 * @param element
	 */
	$.fn.evaluationMultipleChoiceOther.saveOne = function(element) {
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
	$.fn.evaluationMultipleChoiceOther.getValidationRules = function() {
		return {
			element: 'input[name="answer[]"]',
			rules: {
				required: true,
				minlength: 1
			},
			errorContainer: '[data-evaluationerrors="1"]'
		}
	}
}(jQuery));