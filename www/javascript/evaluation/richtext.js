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

			return $.extend(defaults, derived, options);
		}

		/**
		 * Initialise tiny
		 */
		function initChange() {
			$.fn.alaressTiny({
				mode : "exact",
                elements: config.editorSelector,
				disable_toolbars: [2, 3, 4],
				setup : function(ed) {
					ed.onInit.add(function(ed) {
						var dom = ed.dom,
							doc = ed.getDoc(),
							el = doc.content_editable ? ed.getBody() : (tinymce.isGecko ? doc : ed.getWin());

						tinymce.dom.Event.add(el, 'blur', function(e) {
							$.fn.evaluationRichText.saveOne(config.questionForm);
						});
					});
				}
			});
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 */
	$.fn.evaluationRichText.saveOne = function(element) {
		tinyMCE.triggerSave();

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
	$.fn.evaluationRichText.getValidationRules = function() {
	}
}(jQuery));