/**
 * socialstreamBrowser
 *
 * This is a plugin which loads nodes in a modal window.
 *
 * This requires the jquery Dialog addon and was based on
 * Doctore's original implementation.
 *
 * Usage:
 * $j('#socialstream-3174').socialstreamBrowser();
 * $j('a').socialstreamBrowser.loadNode(51);
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
				url: '/socialstream/tree.php',
				element: element,
				instanceId: $j(element).attr('id').substring(13)
			}

			return $j.extend(defaults, options);
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
				title: "Browse Resources",
				modal: true,
				resizable: false
			});
		}
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

		/* Load data into the node container
		 */
		self.config.element.load(self.config.url, args, function() {
			$j(self.config.element).dialog('open');
		});
	}
}(jQuery));