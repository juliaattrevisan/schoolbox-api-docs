/**
 * socialstreamThreads
 *
 * This is a plugin which loads posts into the socialstream
 * component thread container
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
	$j.fn.socialstreamThreads = function(options) {
		var self = $j.fn.socialstreamThreads;

		/* Store the config in this instance so private methods
		 * can access it
		 */
		self.config = init(options, this);

		/* Stream first batch, I guess
		 */
		self.stream();

		/* Show or hide the View More link contextually
		 */
		self.updateViewMoreLink();

		/* Allow View More to load more stuff
		 */
		self.config.viewMoreLink.click(function() {
			self.stream();
		});

		/**
		 * Initialise parameters
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				instanceId: 0,
				start: 0,
				offset: 0,

				/* Total number of posts that exist
				 */
				numPosts: 0,

				/* The url to stream from
				 */
				streamUrl: self.generateStreamUrl(options.instanceId, options.start, options.offset),

				/* The element which allows you to spawn more
				 */
				viewMoreLink: $j(element).find('.socialstreamViewMore'),

				/* Container div for a loading graphic or message
				 */
				loading: $j(element).find('.loading'),

				/* Container div for threads
				 */
				container: $j(element).find('.socialstream-threads')
			}

			/* Force integer (use the force)
			 */
			var merged = $j.extend(defaults, options);

			merged.instanceId = parseInt(merged.instanceId);
			merged.start = parseInt(merged.start);
			merged.offset = parseInt(merged.offset);
			merged.numPosts = parseInt(merged.numPosts);

			return merged;
		}
	}

	/**
	 * Generate a stream url
	 *
	 * @param instanceId
	 * @param start
	 * @param offset
	 */
	$j.fn.socialstreamThreads.generateStreamUrl = function(instanceId, start, offset) {
		return '/socialstream/threads.php?instanceId=' + instanceId + '&start=' + start + '&offset=' + offset;
	}

	/**
	 * Stream more content
	 */
	$j.fn.socialstreamThreads.stream = function() {
		var self = $j.fn.socialstreamThreads;

		$j.get(self.config.streamUrl, function(responseText) {

			/* Shove in the new content
			 */
			addThreads(responseText);

			/* Update offsets, hiding View More if there were none left
			 * after streaming
			 */
			updateStreamUrl();

			/* Show or hide the View More link
			 */
			self.updateViewMoreLink();

			/* Clear out any loading div indicators
			 */
			removeLoaderGifs();

			/* Lightbox everything!
			 */
			$j("a[rel^='lightbox']").slimbox();
		});

		/**
		 * Add new threads
		 *
		 * @param html
		 */
		function addThreads(html) {
			var self = $j.fn.socialstreamThreads;

			self.config.container.append(html);
		}

		/**
		 * Update stream url
		 */
		function updateStreamUrl() {
			var self = $j.fn.socialstreamThreads;

			var newStart = parseInt(self.config.start) + parseInt(self.config.offset);

			/* Remember updated values
			 */
			self.config.start = newStart;
			self.config.streamUrl = self.generateStreamUrl(self.config.instanceId, self.config.start, self.config.offset);
		}

		/**
		 * Clear out any loading gifs
		 */
		function removeLoaderGifs() {
			var self = $j.fn.socialstreamThreads;

			self.config.loading.remove();
		}
	}

	/**
	 * Show or hide the View More link contextually
	 */
	$j.fn.socialstreamThreads.updateViewMoreLink = function() {
		var self = $j.fn.socialstreamThreads;

		if (self.config.start < self.config.numPosts) {
			$j(self.config.viewMoreLink).show();
		}
		else {
			self.config.viewMoreLink.hide();
		}
	}
}(jQuery));