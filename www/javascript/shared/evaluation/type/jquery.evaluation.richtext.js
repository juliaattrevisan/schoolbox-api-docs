(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationRichText = function(options) {
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
				editorSelector: 'richtext-' + options.id,
				questionForm: $('#questionForm-' + options.id)
			}

			return $.extend(true,defaults, derived, options);
		}

		/**
		 * Initialise tiny
		 */
		function initChange() {
            $.fn.alaressEditor({
                targetElements: $('#' + config.editorSelector),
                blur: function(element) {
                    $.fn.evaluationRichText.saveOne(config.questionForm);
                }
            });
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 * @returns jqXHR
	 */
	$.fn.evaluationRichText.saveOne = function(element) {
		element.find('textarea[id]').each(function() {
			var identifier = this.id;
			if (identifier in CKEDITOR.instances) {
				CKEDITOR.instances[identifier].updateElement();
			}
		});

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
	$.fn.evaluationRichText.getValidationRules = function() {
	}
}(jQuery));