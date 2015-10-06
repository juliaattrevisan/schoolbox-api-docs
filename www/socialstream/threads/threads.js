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
		updateViewMoreLink();

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
				streamUrl: generateStreamUrl(options.instanceId, options.homepageId, options.start, options.offset),

				/* The element which allows you to make comments and replies
				 */
				addPostLink: '[data-add-post-link]',

				/* The form which spawns when making comments and replies
				 */
				addPostForm: '[data-add-post-form]',

				/* The element which allows you to spawn more
				 */
				viewMoreLink: $j(element).find('[data-socialstream-view-more]'),

				/* Lightboxed attachments are found using this key
				 */
				lightboxKey: 'socialstream-' + options.instanceId + '-lightbox',

				/* Container div for a loading graphic or message
				 */
				loading: $j(element).find('[data-loader]'),

				/* Container div for threads
				 */
				threadContainer: $j(element).find('[data-socialstream-threads]'),

				/* Attachment selectors everywhere in the entire stream
				 */
				attachment: $j('[data-socialstream-attachment]')
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
			config.element.on('click', config.addPostLink, function(e) {
				e.preventDefault();

				// Does this have a post form already? If so, don't add it back
				// after it gets removed.
                var row = $j(this).closest('[data-socialstream-row]');
				var body = row.find('[data-body]').first();
				var hasPostForm = (body.find('[data-add-post-form]').length > 0)

				/* Clear out all instances of .addPostForm in the socialstream
				 */
				clearAllPostForms();

				/* Get the ids for this node
				 */
				var parentId = $j(this).data('parentid');
				var rootId = $j(this).data('rootid');

				/* Clone an instance of .addPostForm and inject ids
				 */
				var clone = config.defaultForm.clone();
				clone.attr('id', '');
				clone.find('textarea[name=body]').val('');
				clone.find('input[name=parentId]').val(parentId);
				clone.find('input[name=rootId]').val(rootId);
				clone.find('input[name=url]').val('');

                var dzone = clone.find('div.dzone');
                dzone.attr('id', dzone.attr('id') + parentId);

                var azone = clone.find('div.attachzone');
                azone.attr('id', azone.attr('id') + parentId);

                azone.removeClass('dz-max-files-reached').find('ul.az-list').html('');

				/* Show specific ones
				 */
                azone.resourceAttachClone(config.defaultForm.find('div.attachzone'));
				initAttachmentSelectors(clone);

				/* Unhide it
				 */
				clone.removeClass('hide');

				/* Send In The (singular) Clone
				 */
				if (!hasPostForm) {
					body.append(clone);
				}

				/* Steal focus
				 */
				row.find('[data-socialstream-form]').find('textarea').focus();
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
				updateStreamUrl();

				/* Show or hide the View More link
				 */
				updateViewMoreLink();

				/* Clear out any loading div indicators
				 */
				removeLoaderGifs();

				/* Lightbox everything!
				 * But only the images in a particular socialstream thread
				 */
				$j("a[rel='" + config.lightboxKey + "']").magnificPopup({
                    type: 'image',
                    removalDelay: 300,
                    mainClass: 'mfp-fade'
                });

                config.element.find('[data-socialstream-delete]').unbind('click').click(function(event) {
                    event.preventDefault();

                    var element = $(this);
                    $(this).confirmDialog({
                        text: 'Do you want to delete this item?',
                        confirmEvent: function() {
                            window.location.href = element.attr('href');
                        }
                    });
                });
			});

			/**
			 * Add new threads
			 *
			 * @param html
			 */
			function addThreads(html) {
				config.threadContainer.append(html);
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
			// Never show view more if viewing all (offset = 0)
			if (config.offset > 0 && config.start < config.numPosts) {
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
			// Never show view more if viewing all (offset = 0)
			if (config.offset > 0 && config.end < config.numPosts) {
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
			return '/socialstream/threads/threads.php?instanceId=' + instanceId + '&homepageId=' + homepageId + '&start=' + start + '&offset=' + offset;
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
			return '/socialstream/threads/threadsSlow.php?instanceId=' + instanceId + '&homepageId=' + homepageId + '&start=0&end=' + (parseInt(end) + parseInt(offset));
		}

		/**
		 * Abilities granted to all attachment selectors everywhere
		 */
		function initAttachmentSelectors(form) {
			resetAttachmentSelectors(form);

			/* Url attachment options
			 */
            form.on('click', 'a.sstream-attachment', function() {
                var type = $(this).attr('data-value');

                form.find('input[name=type]').val(type);

                // show only the one we want
                var attachers = form.find('div.post-attachment');
                attachers.hide().filter('.'+type).show();

                form.find('input[name=url]').val('').prop('readonly', type == 'attach');

                if (type == 'url') {
                    // tell the attacher to clear any attachments by simulating clicking of all the (one...) delete buttons
                    // not jQuery.detach() - it can't trigger dropzone's event listener
                    form.find('.post-attachment.attach').find('p.actions a.delete').each(function() {
                        var event = new CustomEvent('click');
                        this.dispatchEvent(event);
                    });
                }

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