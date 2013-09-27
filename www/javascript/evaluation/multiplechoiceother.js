(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationMultipleChoiceOther = function(options) {
		var config = init(options);

		initSelected();
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
		 * Highlight preselected items
		 */
		function initSelected() {
			config.questionForm.find('input[type=checkbox]').each(function() {
				if ($(this).is(':checked')) {
					$(this).parent().addClass('evaluation-selected');
				}
			});

			/* Highlight the "Other" checkbox if an Other text value exists
			 */
			if (config.questionForm.find('input[value=Other]').is(':checked')) {
				config.questionForm.find('input[type=text]').parent().addClass('evaluation-selected');
			}
		}

		/**
		 * Save response upon change
		 */
		function initChange() {
			config.questionForm.find('input[type=checkbox]').change(function() {

				if ($(this).is(':checked')) {
					$(this).parent().addClass('evaluation-selected');
				}
				/* Clear Other value if unchecked
				 */
				else {
					$(this).parent().removeClass('evaluation-selected');
					config.questionForm.find('input[type=text]').val('').parent().removeClass('evaluation-selected');
				}

				$.fn.evaluationMultipleChoiceOther.saveOne(config.questionForm);
			});

			/* Detect change in Other field
			 * If the Other has content, check the Other Checkbox, and highlight
			 */
			config.questionForm.find('input[type=text]').change(function() {
				if ($(this).val().length) {
					$(this).parent().addClass('evaluation-selected');
					config.questionForm.find('.evaluation-other').prop('checked', true).parent().addClass('evaluation-selected');
				}
				else {
					$(this).parent().removeClass('evaluation-selected');
					config.questionForm.find('.evaluation-other').prop('checked', false).parent().removeClass('evaluation-selected');
				}

				$.fn.evaluationMultipleChoiceOther.saveOne(config.questionForm);
			});
		}
	}

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
}(jQuery));