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
		var config = init(options, this);

		/* Set up the input form
		 */
		initPostForm();

		/* Stream first batch, I guess
		 */
		stream();

		/* Show or hide the View More link contextually
		 */
		updateViewMoreLinkSlow();

		/* Allow View More to load more stuff
		 */
		config.viewMoreLink.click(function() {
			stream();
		});

		/* Allow you to chooze the kind of attachment (if any)
		 * to accompany your post
		 */
		self.initAttachmentSelectors(config.defaultForm);

		/**
		 * Initialise parameters
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {

			/* Default values
			 */
			var defaults = {
				instanceId: 0,
				homepageId: 0,
				start: 0,
				end: 0,
				offset: 0,

				/* Total number of posts that exist
				 */
				numPosts: 0
			}

			/* Derived values
			 */
			var derived = {
				element: element,

				/* The form which gets cloned per post and the one
				 * which allows you to make new posts
				 */
				defaultForm: $j('#socialstream-form-default-' + options.instanceId),

				/* The url to stream from
				 */
				streamUrl: self.generateStreamUrlSlow(options.instanceId, options.homepageId, options.end, options.offset),

				/* The element which allows you to make comments and replies
				 */
				addPostLink: $j(element).find('.addPostLink'),

				/* The form which spawns when making comments and replies
				 */
				addPostForm: '.addPostForm',

				/* The element which allows you to spawn more
				 */
				viewMoreLink: $j(element).find('.socialstreamViewMore'),

				/* Lightboxed attachments are found using this key
				 */
				lightboxKey: 'socialstream-' + options.instanceId + '-lightbox',

				/* Container div for a loading graphic or message
				 */
				loading: $j(element).find('.loading'),

				/* Container div for threads
				 */
				threadContainer: $j(element).find('.socialstream-threads'),

				/* Attachment selectors everywhere in the entire stream
				 */
				attachment: $j('.socialstream-attachment'),
				attachmentUrlSelector: $j('.socialstream-attachment-url').parent('label'),
				attachmentUploadSelector:  $j('.socialstream-attachment-upload').parent('label'),
				attachmentResourceSelector: $j('.socialstream-attachment-resource').parent('label')
			}

			/* Force integer (use the force)
			 */
			var merged = $j.extend(defaults, derived, options);

			merged.instanceId = parseInt(merged.instanceId);
			merged.start = parseInt(merged.start);
			merged.end = parseInt(merged.end);
			merged.offset = parseInt(merged.offset);
			merged.numPosts = parseInt(merged.numPosts);

			return merged;
		}

		/**
		 * Allow input forms to be spawned
		 */
		function initPostForm() {
			var self = $j.fn.socialstreamThreads;

			config.addPostLink.live('click', function() {

				/* Clear out all instances of .addPostForm in the socialstream
				 */
				clearAllPostForms();

				/* Get the ids for this node
				 */
				var parentId = $j(this).attr('data-parentid');
				var rootId = $j(this).attr('data-rootid');

				/* Clone an instance of .addPostForm and inject ids
				 */
				var clone = config.defaultForm.clone();
				clone.attr('id', '');
				clone.find('input[name=body]').val('').focus();
				clone.find('input[name=parentId]').val(parentId);
				clone.find('input[name=rootId]').val(rootId);
				clone.find('input[name=url]').val('');

				/* Show specific ones
				 */
				self.initAttachmentSelectors(clone);

				/* Unhide it
				 */
				clone.removeClass('hide');

				/* Send In The Clones
				 */
				$j(this).parent('p').append(clone);
			});
		}

		/**
		 * Clear all the forms used to make comments and replies
		 */
		function clearAllPostForms() {
			var self = $j.fn.socialstreamThreads;

			self.config.threadContainer.find(self.config.addPostForm).remove();
		}

		/**
		 * Stream more content
		 */
		function stream() {
			var self = $j.fn.socialstreamThreads;
			var threadContainer = config.threadContainer;

			$j.get(config.streamUrl, function(responseText) {

				/* Shove in the new content
				 */
				addThreads(responseText, threadContainer);

				/* Update offsets, hiding View More if there were none left
				 * after streaming
				 */
				updateStreamUrlSlow();

				/* Show or hide the View More link
				 */
				updateViewMoreLinkSlow();

				/* Clear out any loading div indicators
				 */
				removeLoaderGifs();

				/* Lightbox everything!
				 * But only the images in a particular socialstream thread
				 */
				$j("a[rel='" + config.lightboxKey + "']").slimbox();
			});

			/**
			 * Add new threads
			 *
			 * @param html
			 * @param threadContainer
			 */
			function addThreads(html, threadContainer) {
				var self = $j.fn.socialstreamThreads;

				threadContainer.html(html);
			}

			/**
			 * Update stream url
			 * This is subject to the issue SB-3160
			 */
			function updateStreamUrl() {
				var self = $j.fn.socialstreamThreads;

				var newStart = parseInt(self.config.start) + parseInt(self.config.offset);

				/* Remember updated values
				 */
				self.config.start = newStart;
				self.config.streamUrl = self.generateStreamUrl(self.config.instanceId, self.config.homepageId, self.config.start, self.config.offset);
			}

			/**
			 * Update stream url
			 * This is subject to the issue SB-3160
			 */
			function updateStreamUrlSlow() {
				var self = $j.fn.socialstreamThreads;

				var newEnd = parseInt(config.end) + parseInt(config.offset);

				/* Remember updated values
				 */
				config.end = newEnd;
				config.streamUrl = self.generateStreamUrlSlow(config.instanceId, config.homepageId, config.end, config.offset);
			}

			/**
			 * Clear out any loading gifs
			 */
			function removeLoaderGifs() {
				var self = $j.fn.socialstreamThreads;

				config.loading.remove();
			}
		}

		/**
		 * Show or hide the View More link contextually
		 */
		function updateViewMoreLinkSlow() {
			if (config.end < config.numPosts) {
				$j(config.viewMoreLink).show();
			}
			else {
				config.viewMoreLink.hide();
			}
		}
	}

	/**
	 * Abilities granted to all attachment selectors everywhere
	 */
	$j.fn.socialstreamThreads.initAttachmentSelectors = function(form) {
		resetAttachmentSelectors(form);

		/* Url attachment options
		 */
		form.find('.socialstream-attachment-url').click(function() {
			form.find('input[name=socialstreamAttachment]').val('url');

			form.find('input[name=url]').val('').removeAttr('readonly', '').parent('label').show();
			form.find('input[name=file]').parent('label').hide();
			form.find('.socialstream-selected-file').html('').parent('label').hide();

		});

		/* Upload attachment options
		 */
		form.find('.socialstream-attachment-upload').click(function() {
			form.find('input[name=socialstreamAttachment]').val('upload');

			form.find('input[name=url]').val('').parent('label').hide();
			form.find('input[name=file]').parent('label').show();
			form.find('.socialstream-selected-file').html('').parent('label').hide();
		});

		/* Resource attachment options
		 */
		form.find('.socialstream-attachment-resource').click(function() {
			form.find('input[name=socialstreamAttachment]').val('resource');

			form.find('input[name=url]').val('').attr('readonly', 'readonly').parent('label').show();
			form.find('input[name=file]').parent('label').hide();
			form.find('.socialstream-selected-file').html('').parent('label').show();
		});

		return form;

		/**
		 * Hide certain form controls
		 *
		 * @param form
		 */
		function resetAttachmentSelectors(form) {
			form.find('.socialstream-selected-file').parent('label').hide();
			form.find('input[name=url]').parent('label').hide();
			form.find('input[name=file]').parent('label').hide();
		}
	}

	/**
	 * Generate a stream url
	 *
	 * @param instanceId
	 * @param homepageId
	 * @param start
	 * @param offset
	 */
	$j.fn.socialstreamThreads.generateStreamUrl = function(instanceId, homepageId, start, offset) {
		return '/socialstream/threads.php?instanceId=' + instanceId + '&homepageId=' + homepageId + '&start=' + start + '&offset=' + offset;
	}

	/**
	 * Generate a stream url
	 *
	 * @param instanceId
	 * @param homepageId
	 * @param end
	 * @param offset
	 */
	$j.fn.socialstreamThreads.generateStreamUrlSlow = function(instanceId, homepageId, end, offset) {
		return '/socialstream/threadsSlow.php?instanceId=' + instanceId + '&homepageId=' + homepageId + '&start=0&end=' + (parseInt(end) + parseInt(offset));
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