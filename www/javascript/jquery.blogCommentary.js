(function($) {

	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.blogCommentary = function(options) {
		options = options || {};

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		execute();

		/**
		 * Set up options
		 *
		 * @param options
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				id: 0,
				component: $(element)
			}

			var derived = {
			}

			return $.extend(defaults, derived, options);
		}

		/**
		 * Execute plugin
		 */
		function execute() {
			var component = config.component;

			/* For viewing comment form
			 *
			 * When you have $j(function() inside the <body> tags the onclick
			 * event runs twice. Unbind it and rebind it is a convenient solution.
			 *
			 * By Shahbaz Saleem
			 * http://yuji.wordpress.com/2010/02/22/jquery-click-event-fires-twice/
			 */
			component.find('.closeComment').unbind('click').click(function() {
				var id = $(this).attr('rel');
				component.find('#' + id).toggle();
			});

			/* For viewing comment thread
			 */
			component.find('.viewCommentThread').hide();
			component.find('.viewComments').unbind('click').click(function(event) {
				event.preventDefault();

				var matches = $(this).attr('rel').match(/^viewComments_(\d+)$/);

				if (matches) {
					var postId = matches[1];

					var element = component.find('#blogComment_' + postId + ' .viewCommentThread');
					element.toggle();

					/* Update button text when toggling
					 */
					var button = component.find('a[rel=viewComments_' + postId + ']');

					if ($(element).is(':visible')) {
						var state = 'Hide';
					}
					else {
						var state = 'Show';
					}

					button.html('<span></span>' + state + ' Comments (' + button.data('numcomments') + ')');
				}
			});
		}
	}
}(jQuery));