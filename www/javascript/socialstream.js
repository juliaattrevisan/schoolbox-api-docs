var socialstream = {
	instanceId: 0,
	start: 0,
	offset: 0,

	/**
	 * Initialise everything
	 *
	 * @param instanceId
	 * @param start
	 * @param offset
	 */
	init: function(instanceId, start, offset) {
		this.instanceId = instanceId;
		this.start = start;
		this.offset = offset;

		this.initPostLinks();
		this.initViewMore();
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
	 * Load initial stream
	 */
	stream: function() {
		var url = '/socialstream/threads.php?instanceId=' + this.instanceId + '&start=' + this.start + '&offset=' + this.offset;
		var self = this;

		$j.get(url, function(responseText) {
			$j('div.socialstream').append(responseText);

			/* Update offsets
			 */
			self.start = parseInt(self.start) + parseInt(self.offset);
		});
	}
}