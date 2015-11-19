/**
 * A jquery plugin which gets a list of available textboxes
 * in a particular category id and returns them in a <select>

<script type="text/javascript" src="/textbox/javascript/availableByCategory.js"></script>
$j(function() {
	$j('a[name=go-to{COMPONENT_INSTANCE_ID}]').availableByCategory({
		categoryId: '{TEXTBOX_CATEGORY_ID}',
		target: $j('#myDiv')
	});
});

 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

(function($j) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$j.fn.availableByCategory = function(options) {

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		/* Spawn that select
		 */
		load();

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*|void|Function|extend|extend|extend}
		 */
		function init(options, element) {
			var defaults = {
				element: element,

				/* The current category id
				 */
				categoryId: 0,

				/* Ajax url for getting a list of textboxes
				 * available for a particular category
				 */
				url: '/component/ajax/availableByCategory.php',

				/* A jquery-selected element where the <select>
				 * element eventually gets put
				 */
				target: null
			}

			var derived = {
			}

			var merged = $j.extend(defaults, derived, options);

			merged.payload = $j.extend(
				merged.payload,
				{ categoryId: parseInt(merged.categoryId) }
			);

			return merged;
		}

		/**
		 * Loads a new select element into config.target
		 */
		function load() {
			$j.get(config.url, config.payload, function(allTextboxes) {
				/* If there are textboxes for this homepage, glue
				 * them all together
				 */
				var allOptions = [];
				if (allTextboxes.length) {

					/* Accumulate a list of <options>
					 */
					for (var i = 0; i < allTextboxes.length; i++) {
						allOptions.push($('<option/>').val(allTextboxes[i].value).text(allTextboxes[i].name));
					}
				}
				else {
					allOptions.push($('<option/>').val(0).text('(none)').prop('disabled', true));
				}

				/* If the target exists, replace it - since Resource Browser
				 * is shared by everything, if the textbox target doesn't exist,
				 * just do nowt
				 */
				if (config.target.length) {
					config.target.empty().append(allOptions);
				}
			}, 'json');
		}
	}
}(jQuery));