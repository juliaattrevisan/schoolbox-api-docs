(function($j) {
	$j.fn.emailSelected = function(options) {
		var config;

		$j(this).submit(function(event) {
			event.preventDefault();

			config = init(options, this);

			var allEmails = fetchEmails();
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
				sourceForm: null,
				url: ''
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
				allIds: gatherSelectedIds()
			}

			var allEmails = '';

			$j.post(config.url, payload, function(responseText) {
				var response = new Function('return ' + responseText);
				var allEmails = response();
			});

			return allEmails;
		}
	}
}(jQuery));
