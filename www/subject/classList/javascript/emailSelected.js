(function($j) {
	$j.fn.emailSelected = function(options) {
		var config;

		/* Stop the form submitting so we can do some
		 * special manipulation, inject the results,
		 * and then submit normally. This submit handler
		 * is cleared in redirect();
		 */
		$j(this).submit(function(event) {
			event.preventDefault();

			config = init(options, this);

			fetchEmails();
		});

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*|void|Function|extend|extend|extend}
		 */
		function init(options, element) {
			var defaults = {

				/* The email form belonging to this plugin instance
				 */
				element: element,

				/* The element containing all the checkboxes
				 */
				sourceForm: null,

				/* The url to access, to provide a list of recipient emails
				 */
				url: '',

				/* The class code for the current list of people
				 */
				classCode: '',

				/* A jquery-selected element in the form. All the
				 * emails get rammed into here
				 */
				target: null
			}

			var derived = {
			}

			var merged = $j.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Get a list of checked checkboxes containing externalIds
		 */
		function gatherSelectedIds() {
			var allSelected = config.sourceForm.find('input[name="selected[]"]:checked');
			var ids = [];

			for (var i = 0; i < allSelected.length; i++) {
				var currentSelected = allSelected[i];

				ids.push(currentSelected.value);
			}

			return ids;
		}

		/**
		 * A list of all the emails associated with the selected people
		 */
		function fetchEmails() {
			var payload = {
				allIds: gatherSelectedIds(),
				classCode: config.classCode
			}

			$j.post(config.url, payload, function(responseText) {
				var response = new Function('return ' + responseText);
				var allEmails = response();

				/* Inject and redirect
				 */
				inject(allEmails);
				redirect();
			});
		}

		/**
		 * Inject emails into target form element
		 */
		function inject(allEmails) {
			config.target.val(allEmails.join(','));
		}

		/**
		 * Submit the form as originally intended
		 */
		function redirect() {
			$j(config.element).unbind('submit');
			config.element.submit();
		}
	}
}(jQuery));
