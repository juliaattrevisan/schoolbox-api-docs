(function($j) {
	$j.fn.addSelectAll = function(options) {
		var config = init(options, this);

		addSelectAll();

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*|void|Function|extend|extend|extend}
		 */
		function init(options, element) {
			var defaults = {

				/* The checkbox which selects/deselects everything
				 */
				element: element,

				/* The element containing all the checkboxes
				 */
				source: null
			}

			var derived = {
			}

			var merged = $j.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Add select-all functionality to a checkbox
		 */
		function addSelectAll() {
			config.element.click(function() {
				var elements = config.source.find('input[name="selected[]"]');

				if ($j(this).is(':checked')) {
					elements.prop('checked', true);
				}
				else {
					elements.prop('checked', false);
				}
			});
		}
	}

	$j.fn.emailSelected = function(options) {

		var config = init(options, this);

		fetchEmails();

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
				source: null,

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
			var allSelected = config.source.find('input[name="selected[]"]:checked');
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
				var allEmails = JSON.parse(responseText);

				/* Inject and redirect only if emails were found
				 */
				if (allEmails.length) {
					inject(allEmails);
					redirect();
				}
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
