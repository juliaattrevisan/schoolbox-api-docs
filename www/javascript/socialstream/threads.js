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
		initAttachmentSelectors(config.defaultForm);

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
				streamUrl: generateStreamUrlSlow(options.instanceId, options.homepageId, options.end, options.offset),

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
				attachmentUrlSelector: $j('.socialstream-attachment-url').closest('li'),
				attachmentUploadSelector:  $j('.socialstream-attachment-upload').closest('li'),
				attachmentResourceSelector: $j('.socialstream-attachment-resource').closest('li')
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
			config.addPostLink.live('click', function(e) {

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
				initAttachmentSelectors(clone);

				/* Unhide it
				 */
				clone.removeClass('hide');

				/* Send In The Clones
				 */
				$j(this).closest('li').find('.body').append(clone);

				e.preventDefault();
			});
		}

		/**
		 * Clear all the forms used to make comments and replies
		 */
		function clearAllPostForms() {
			config.threadContainer.find(config.addPostForm).remove();
		}

		/**
		 * Stream more content
		 */
		function stream() {
			$j.get(config.streamUrl, function(responseText) {

				/* Shove in the new content
				 */
				addThreads(responseText);

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
			 */
			function addThreads(html) {
				config.threadContainer.html(html);
			}

			/**
			 * Update stream url
			 * This is subject to the issue SB-3160
			 */
			function updateStreamUrl() {
				var newStart = parseInt(config.start) + parseInt(config.offset);

				/* Remember updated values
				 */
				config.start = newStart;
				config.streamUrl = generateStreamUrl(config.instanceId, config.homepageId, config.start, config.offset);
			}

			/**
			 * Update stream url
			 * This is subject to the issue SB-3160
			 */
			function updateStreamUrlSlow() {
				var newEnd = parseInt(config.end) + parseInt(config.offset);

				/* Remember updated values
				 */
				config.end = newEnd;
				config.streamUrl = generateStreamUrlSlow(config.instanceId, config.homepageId, config.end, config.offset);
			}

			/**
			 * Clear out any loading gifs
			 */
			function removeLoaderGifs() {
				config.loading.remove();
			}
		}

		/**
		 * Show or hide the View More link contextually
		 */
		function updateViewMoreLink() {
			if (config.start < config.numPosts) {
				$j(config.viewMoreLink).show();
			}
			else {
				$j(config.viewMoreLink).hide();
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
				$j(config.viewMoreLink).hide();
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
		function generateStreamUrl(instanceId, homepageId, start, offset) {
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
		function generateStreamUrlSlow(instanceId, homepageId, end, offset) {
			return '/socialstream/threadsSlow.php?instanceId=' + instanceId + '&homepageId=' + homepageId + '&start=0&end=' + (parseInt(end) + parseInt(offset));
		}

		/**
		 * Abilities granted to all attachment selectors everywhere
		 */
		function initAttachmentSelectors(form) {
			resetAttachmentSelectors(form);

			/* Url attachment options
			 */
			form.find('.socialstream-attachment-url').click(function() {
				form.find('input[name=socialstreamAttachment]').val('url');

				form.find('input[name=url]').val('').removeAttr('readonly', '').closest('li').show();
				form.find('input[name=file]').closest('li').hide();
				form.find('.socialstream-selected-file').html('').closest('li').hide();

			});

			/* Upload attachment options
			 */
			form.find('.socialstream-attachment-upload').click(function() {
				form.find('input[name=socialstreamAttachment]').val('upload');

				form.find('input[name=url]').val('').closest('li').hide();
				form.find('input[name=file]').closest('li').show();
				form.find('.socialstream-selected-file').html('').closest('li').hide();
			});

			/* Resource attachment options
			 */
			form.find('.socialstream-attachment-resource').click(function() {
				form.find('input[name=socialstreamAttachment]').val('resource');

				form.find('input[name=url]').val('').attr('readonly', 'readonly').closest('li').show();
				form.find('input[name=file]').closest('li').hide();
				form.find('.socialstream-selected-file').html('').closest('li').show();
			});

			return form;

			/**
			 * Hide certain form controls
			 *
			 * @param form
			 */
			function resetAttachmentSelectors(form) {
				form.find('.socialstream-selected-file').closest('li').hide();
				form.find('input[name=url]').closest('li').hide();
				form.find('input[name=file]').closest('li').hide();
			}
		}
	}
}(jQuery));