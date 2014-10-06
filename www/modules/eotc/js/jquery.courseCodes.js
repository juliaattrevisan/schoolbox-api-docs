(function($) {
	$.fn.externalClassCodes = function(options) {
		options = options || {}

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		setup();

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
				element: element,
				url: '/modules/eotc/manage/attendance/courseCodes',
				output: null,
				campus: []
			}

			var derived = {
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Set up event handler
		 */
		function setup() {
			$(config.element).change(function(event) {
				retrieveData($(this).val());
			});
		}

		/**
		 * Retrieve a list of codes for the filetype
		 *
		 * @param id
		 */
		function retrieveData(id) {
			$.post(config.url, {id: id, campus: config.campus}, function(responseText) {
				var data = $.parseJSON(responseText);

				var allOptions = '';

				for (var i = 0; i < data.length; i++) {
					var option = '<option value="'+ data[i].ClassCode +'">'+ data[i].ClassCode +'</option>';
					allOptions += option;
				}

				/* Update select dropdown, or reset it
				 */
				if (allOptions.length) {
					config.output.html(allOptions);
					config.output.prop('disabled', false);
				}
				else {
					config.output.html('<option value="">Please select</option>');
					config.output.prop('disabled', true);
				}
			});
		}
	}
}(jQuery));