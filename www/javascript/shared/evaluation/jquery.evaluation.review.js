(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.evaluationResponseReview = function(options) {
		var config = init(options);

		initSaveComment();
		initSaveMarks();
		initSaveReview();

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
		 * Set up review saving events
		 */
		function initSaveReview() {
			$(config.questionForm).validate({
				submitHandler: function() {
//					showAlert(config.questionForm);

					$.fn.evaluationResponseReview.saveReview(config.questionForm);

					return false;
				},
				rules: {
					reviewedMarks: {
						number:true
					}
				},
				messages: {
					reviewedMarks: "Please enter a decimal number"
				},
				errorPlacement: function(error, element) {
					offset = element.offset();
					error.insertBefore(element)
				}
			});
		}

		/**
		 * Save commentary on blur
		 */
		function initSaveComment() {
			config.questionForm.find('textarea').blur(function() {
				if ($(this).val() !== '') {
					$.fn.evaluationResponseReview.saveReview(config.questionForm);
				}
				config.questionForm.find('textarea').addClass('ajax-updated');
				setTimeout(function() {
					config.questionForm.find('textarea').removeClass('ajax-updated');
				}, 1000);
			});
		}

		/**
		 * Save marks on blur
		 */
		function initSaveMarks() {
			config.questionForm.find('input[name=reviewedMarks]').blur(function() {
				$.fn.evaluationResponseReview.saveReview(config.questionForm);
				config.questionForm.find('input[name=reviewedMarks]').addClass('ajax-updated');
				setTimeout(function() {
					config.questionForm.find('input[name=reviewedMarks]').removeClass('ajax-updated');
				}, 1000);
			});
		}
	}

	/**
	 * Save individual form contents
	 *
	 * @param element
	 */
	$.fn.evaluationResponseReview.saveReview = function(element) {
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
				result();
			}
		});
	}
}(jQuery));