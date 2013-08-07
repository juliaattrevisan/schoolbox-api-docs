var socialstream = {

	/**
	 * Component instance
	 */
	instanceId: 0,

	/**
	 * Start and offset values
	 */
	start: 0,
	offset: 0,

	/**
	 * Maximum number of posts at the moment
	 */
	numPosts: 0,

	/**
	 * The element which allows you to spawn more
	 */
	viewMoreLink: '.socialstreamViewMore',

	/**
	 * Container div for a loading graphic or message
	 */
	loading: '.loading',

	/**
	 * Initialise everything
	 *
	 * @param instanceId
	 * @param start
	 * @param offset
 	 * @param numPosts
	 */
	init: function(instanceId, start, offset, numPosts) {
		this.instanceId = instanceId;
		this.start = start;
		this.offset = offset;
		this.numPosts = numPosts;

		this.initPostLinks();
		this.initViewMore();
		this.initAttachmentSelectors();
	},

	/**
	 * Add event handlers for .addPostLink tags
	 */
	initPostLinks: function() {
		$j('.socialstream').find('.addPostLink').live('click', function() {

			/* Clear out all instances of .addPostForm in the socialstream
			 */
			$j('.socialstream').find('.addPostForm').remove();

			/* Get the ids for this node
			 */
			var parentId = $j(this).attr('data-parentid');
			var rootId = $j(this).attr('data-rootid');

			/* Clone an instance of .addPostForm and inject ids
			 */
			var clone = $j('#originalAddPostForm').clone();
			clone.attr('id', '');
			clone.find('input[name=body]').val('').focus();
			clone.find('input[name=parentId]').val(parentId);
			clone.find('input[name=rootId]').val(rootId);

			/* Send In The Clones
			 */
			$j(this).parent('p').append(clone);
		});
	},

	/**
	 * Stream more stuff in
	 */
	initViewMore: function() {
		var self = this;

		$j('.socialstreamViewMore').click(function() {
			self.stream();
		});
	},

	/**
	 * Add event handlers for Link, Upload and Resource buttons
	 * for when you make posts. Clear it upon change
	 */
	initAttachmentSelectors: function() {
		$j('.socialstreamAttachment').change(function() {
			var urlElement = $j(this).closest('form').find('input[name=url]');

			/* Empty this upon change
			 */
			urlElement.val('');

			/* Make the input element visible or not
			 */
			if ($j(this).val() == 'link') {
				urlElement.show();
			}
			else {
				urlElement.hide();
			}
		});
	},

	/**
	 * Load initial stream
	 */
	stream: function() {
		var url = '/socialstream/threads.php?instanceId=' + this.instanceId + '&start=' + this.start + '&offset=' + this.offset;
		var self = this;

		$j.get(url, function(responseText) {
			$j('.socialstream').append(responseText);

			/* Update offsets, hiding View More if there were none left
			 */
			self.start = parseInt(self.start) + parseInt(self.offset);

			if (self.start > self.numPosts) {
				$j(self.viewMoreLink).hide();
			}

			/* Clear out any loading div indicators
			 */
			$j('.socialstream').find(self.loading).remove();
		});
	}
}