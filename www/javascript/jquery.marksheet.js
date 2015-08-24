(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.marksheet = function(options) {

		/* Optional arguments
		 */
		options = options || {}

		/* Store the config in this instance so private methods
		 * can access it, only if the chosen element exists
		 */
		var config = init(options, this);

		makeValidatable();
		cellValidation();
		selectGrouping();

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

				/* Ajax submit marks whenever a dropdown is changed, a
				 * value is entered, or a comment is added
				 */
				submitOnChange: false,

				/* The url to submit data
				 */
				submitUrl: '/submission/returnWorkForm.php',

				/* wat? This didn't do anything other than change to false
				 * when something is cilcked
				 */
				ajax: true
			}

			var derived = {
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		function makeValidatable() {

			// the validator doesn't seem to be able to handle two inputs with the same name, so they need to be
			// uniqu-ified, then ajax submitted
			var inputs = config.element.find('input[data-mark-input]');

			inputs.each(function() {
				var self = $(this);
				var pInline = self.closest('form');
				var uid = pInline.find('input.uid');

				/* wat?
				 self.attr('name', self.attr('name').substring(0, self.attr('name').length - 2) + '[' + uid.val() + ']');
				 */
//				self.attr('name', self.attr('name') + '[' + uid.val() + ']');
			});
		}

		function cellValidation() {

			/* Only ajax submit on change if this setting is on
			 */
			if (config.submitOnChange === true) {
				var allInlineForms = config.element.find('form');

				$('[data-mark-input], [data-comment]').change(function(){
					var thisInlineForm = $(this).closest('form');

					allInlineForms.not(thisInlineForm).removeClass('inline-submit');
					thisInlineForm.addClass('inline-submit');
                    thisInlineForm.submit();
				});
			}

			/*
			 * Depends on the presence of the jquery.validate library.
			 * */
			jQuery.validator.addMethod("percentage", function(value, element) {
				if(jQuery.combobox.instances.length) {
					var valid = jQuery.combobox.instances[0].selector.allSelectOptions;
					if(jQuery.inArray(value, valid) != -1) {
						return true;
					}
				}
				return this.optional(element) || (parseFloat(value) <= 100);
			}, "Please enter a value out of 100");

			jQuery.validator.addMethod("markLimit", function(value, element) {
				if(jQuery.combobox.instances.length) {
					var valid = jQuery.combobox.instances[0].selector.allSelectOptions;
					if(jQuery.inArray(value, valid) != -1) {
						return true;
					}
				}
				var markLimit = parseInt($(element).closest('form').find('[data-validation-limit]').text());
				if (value !== "") {
					var v = parseInt(value);
					return v <= markLimit && v >= 0;
				} else {
					return true;
				}
			}, "Amount must be less or equal to mark limit");


			jQuery.validator.addClassRules({
				percent: {
					percentage: true
				},

				'out-of': {
					markLimit: true
				}
			});

			config.element.each(function() {
				$(this).validate({
                    focusInvalid: false,
                    errorPlacement: function(error, element) {
                        // Attach to a table row if there is one there
                        var markingElement = element.closest('form');
                        if (markingElement.length) {
                            error.appendTo(markingElement);
                        } else {
                            error.insertAfter(element);
                        }
                    },
					submitHandler: function(form) {
						// Any inline submit forms? (there will be, if we are
						// focused on a form element.) Use them if so.
						// (Thankfully, the change event triggers before blur
						//  in all browsers.)
						var $form = $(form);
						$.post(config.submitUrl, $form.serialize())
							.done(function() {
								$form.find('[data-relation="success-message"]').labelTimeout();
							})
							.fail(function() {
								$form.find('[data-relation="error-message"]').labelTimeout();
							});
						return false;
					}
				});
			});

		}

		function selectGrouping() {
			$('#content select[data-mark-input]').each(function() {
				// if the select doesn't contain a separator for the error codes, slot it in
				var self = $(this);
				if (self.find('optgroup').length == 0) {
					// find the first error (non-numeric) option
					self.children('option').each(function() {
						if(!isNumber($(this).val())) {
							// insert before me
							$(this).before('<optgroup></optgroup>');
							// break the loop
							return false;
						}
					});
				}
			});
		}

		/**
		 * Determine if something is a number or not
		 *
		 * @param n
		 * @returns {boolean|*|HTMLElement}
		 */
		function isNumber(n) {
			return !isNaN(parseFloat(n)) && isFinite(n);
		}
	}
}(jQuery));