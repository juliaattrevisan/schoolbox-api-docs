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
			};

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
			component.find('[data-toggle]').unbind('click').click(function() {
				$('#' + $(this).data('toggle')).toggleClass('hide');
			});

			/* For viewing comment thread
			 */
			component.find('[data-thread]').hide();
			component.find('[data-interaction=showcomments]').unbind('click').click(function(event) {
				event.preventDefault();

                var element = component.find('[data-thread]');

                element.toggle();

                /* Update button text when toggling
                 */
                var button = component.find('[data-interaction=showcomments]'),
                    state;

                if ($(element).is(':visible')) {
                    state = 'Hide';
                }
                else {
                    state = 'Show';
                }

                button.html(state + ' Comments (' + button.data('numcomments') + ')');
			});
		}
	}
}(jQuery));