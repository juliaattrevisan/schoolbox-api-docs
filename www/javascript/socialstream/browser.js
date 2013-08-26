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
		var self = $j.fn.socialstreamBrowser;

		/* Store the config in this instance so private methods
		 * can access it
		 */
		self.config = init(options, this);

		/* Set up modal thingy
		 */
		initModal(self.config);

		/* For chaining
		 */
		return self;

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
				title: 'hoax',

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
		};

		/**
		 * Set up modal window
		 *
		 * @param config
		 */
		function initModal(config) {
			$j(config.element).dialog({
				width: 650,
				autoOpen: false,
				title: config.title,
				modal: true,
				resizable: false
			});
		}
	}

	/**
	 * Open le browser
	 */
	$j.fn.socialstreamBrowser.open = function() {
		var self = $j.fn.socialstreamBrowser;

		self.loadNode(socialstreamSession.categoryId);
	}

	/**
	 * Load a node contents
	 *
	 * @param node
	 */
	$j.fn.socialstreamBrowser.loadNode = function(node) {
		var self = $j.fn.socialstreamBrowser;

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
		self.config.element.load(self.config.url, args, function() {

			/* These allow new nodes to be loaded
			 */
			var allNavigators = $j(self.config.element).find('.socialstream-breadcrumb, .socialstream-node-category');

			$j.each(allNavigators, function(index, element) {
				$j(element).find('a').click(function() {
					$j(self.config.element).socialstreamBrowser.loadNode($j(this).attr('rel'));

					return false;
				});
			});

			/* Assign event handlers to all the files
			 */
			var allFiles = $j(self.config.element).find('.socialstream-node-file');

			$j.each(allFiles, function(index, element) {
				$j(element).find('a').click(function() {
					var url = $j(this).attr('href');

					/* Chooze and cloze
					 */
					self.config.submitCallback(url);

					$j(self.config.element).dialog('close');

					return false;
				});
			});

			/* For all the world to see
			 */
			$j(self.config.element).dialog('open');
		});
	}
}(jQuery));