(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.selectAll = function(options) {
		options = options || {};

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		start();

		/**
		 * Initialise parameters
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {

			/* key is the checkbox to control all checkboxes
			 * checkboxName is the name of all checkboxes to be manipulated
			 */
			var defaults = {
				change: function() {
				}
			}

			var derived = {
				selector: element.find('[data-' + options.key + '="true"]'),
				checkboxes: element.find('input[name="' + options.checkboxName + '"]')
			}
			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Start select-all
		 */
		function start() {
			$(config.selector).change(function(event) {
				if ($(this).is(':checked')) {
					$(config.checkboxes).prop('checked', true);
				}
				else {
					$(config.checkboxes).prop('checked', false);
				}

				config.change();
			});
		}
	}
}(jQuery));
