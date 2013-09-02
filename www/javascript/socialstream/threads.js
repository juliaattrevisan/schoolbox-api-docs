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

		/* Set up the input form
		 */
		initPostForm();

		/* Stream first batch, I guess
		 */
		self.stream();

		/* Show or hide the View More link contextually
		 */
		self.updateViewMoreLinkSlow();

		/* Allow View More to load more stuff
		 */
		self.config.viewMoreLink.click(function() {
			self.stream();
		});

		/* Allow you to chooze the kind of attachment (if any)
		 * to accompany your post
		 */
		self.initAttachmentSelectors(self.config.defaultForm);

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

				/* The form which gets cloned per post and the one
				 * which allows you to make new posts
				 */
				defaultForm: $j('#socialstream-form-default-' + options.instanceId),

				/* The url to stream from
				 */
				streamUrl: self.generateStreamUrlSlow(options.instanceId, options.end, options.offset),

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

			self.config.addPostLink.live('click', function() {

				/* Clear out all instances of .addPostForm in the socialstream
				 */
				clearAllPostForms();

				/* Get the ids for this node
				 */
				var parentId = $j(this).attr('data-parentid');
				var rootId = $j(this).attr('data-rootid');

				/* Clone an instance of .addPostForm and inject ids
				 */
				var clone = self.config.defaultForm.clone();
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
	 * @param start
	 * @param offset
	 */
	$j.fn.socialstreamThreads.generateStreamUrl = function(instanceId, start, offset) {
		return '/socialstream/threads.php?instanceId=' + instanceId + '&start=' + start + '&offset=' + offset;
	}

	/**
	 * Generate a stream url
	 *
	 * @param instanceId
	 * @param end
	 * @param offset
	 */
	$j.fn.socialstreamThreads.generateStreamUrlSlow = function(instanceId, end, offset) {
		return '/socialstream/threadsSlow.php?instanceId=' + instanceId + '&start=0&end=' + (parseInt(end) + parseInt(offset));
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
			updateStreamUrlSlow();

			/* Show or hide the View More link
			 */
			self.updateViewMoreLinkSlow();

			/* Clear out any loading div indicators
			 */
			removeLoaderGifs();

			/* Lightbox everything!
			 * But only the images in a particular socialstream thread
			 */
			$j("a[rel='" + self.config.lightboxKey + "']").slimbox();
		});

		/**
		 * Add new threads
		 *
		 * @param html
		 */
		function addThreads(html) {
			var self = $j.fn.socialstreamThreads;

			self.config.threadContainer.html(html);
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
			self.config.streamUrl = self.generateStreamUrl(self.config.instanceId, self.config.start, self.config.offset);
		}

		/**
		 * Update stream url
		 * This is subject to the issue SB-3160
		 */
		function updateStreamUrlSlow() {
			var self = $j.fn.socialstreamThreads;

			var newEnd = parseInt(self.config.end) + parseInt(self.config.offset);

			/* Remember updated values
			 */
			self.config.end = newEnd;
			self.config.streamUrl = self.generateStreamUrlSlow(self.config.instanceId, self.config.end, self.config.offset);
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

	/**
	 * Show or hide the View More link contextually
	 */
	$j.fn.socialstreamThreads.updateViewMoreLinkSlow = function() {
		var self = $j.fn.socialstreamThreads;

		if (self.config.end < self.config.numPosts) {
			$j(self.config.viewMoreLink).show();
		}
		else {
			self.config.viewMoreLink.hide();
		}
	}
}(jQuery));