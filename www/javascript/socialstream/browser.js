/**
 * socialstreamBrowser
 *
 * This is a plugin which loads nodes in a modal window.
 *
 * This requires the jquery Dialog addon and was based on
 * Doctore's original implementation.
 *
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
	$j.fn.socialstreamBrowser = function(options) {

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		/* Set up modal thingy
		 */
		initModal();

		/* Open Pandora's Box
		 */
		loadNode(socialstreamSession.categoryId);

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

				/* The title of the browser
				 */
				title: 'Choose a file',

				/* The url to load node contents from
				 */
				url: '/socialstream/tree.php',

				/* The default category to open
				 */
				categoryId: 0,

				/* A callback method. The file you choze from the browser is passed
				 * to this method to deal with in whatever manner you want
				 */
				submitTo: function(url) {
				},

				/* The element containing the browser
				 */
				element: element
			}

			/* Type casting
			 */
			var merged = $j.extend(defaults, options);
			merged.categoryId = parseInt(merged.categoryId);

			return merged;
		}

		/**
		 * Set up modal window
		 */
		function initModal() {
			$j(config.element).dialog({
				width: 650,
				autoOpen: false,
				title: config.title,
				modal: true,
				resizable: false
			});
		}

		/**
		 * Load a node contents
		 *
		 * @param node
		 */
		function loadNode(node) {

			/* The thing you clicked on
			 */
			var args = {
				relationID: node
			}

			/* Remember where we left off and reopen from here
			 */
			socialstreamSession.categoryId = node;

			/* Load data into the node container
			 */
			config.element.load(config.url, args, function() {

				/* These allow new nodes to be loaded
				 */
				var allNavigators = $j(config.element).find('.socialstream-breadcrumb, .socialstream-node-category');

				$j.each(allNavigators, function(index, element) {
					$j(element).find('a').click(function() {
						loadNode($j(this).attr('rel'));

						return false;
					});
				});

				/* Assign event handlers to all the files
				 */
				var allFiles = $j(config.element).find('.socialstream-node-file');

				$j.each(allFiles, function(index, element) {
					$j(element).find('a').click(function() {
						var url = $j(this).attr('href');
						var filename = $j(this).html();

						/* Chooze and cloze
						 */
						config.submitCallback(url, filename);

						$j(config.element).dialog('close');

						return false;
					});
				});

				/* For all the world to see
				 */
				$j(config.element).dialog('open');
			});
		}
	}
}(jQuery));