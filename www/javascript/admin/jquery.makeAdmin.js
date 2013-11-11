/**
 * makeAdmin
 *
 * This is a plugin which allows users to become admins of
 * groups, providing dialog warnings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

(function($j) {
	$j.fn.makeAdmin = function(options) {
		var config;

		/* For every makeAdmin element, allow functionality
		 */
		$j.each(this, function(index, element) {
			$j(element).click(function() {
				event.preventDefault();

				config = init($j(this), options);

				changeAdmin();
			});
		});

		/**
		 * Init
		 *
		 * @param element
		 * @param options
		 * @returns {*|void|Function|extend|extend|extend}
		 */
		function init(element, options) {
			var defaults = {
				element: element,
				super: 0
			}

			var derived = {
				currentUserId: parseInt(options.currentUserId),
				currentAdminId: parseInt(options.currentAdminId),
				super: parseInt(options.super),
				username: element.data('username'),
				standardWarning: 'Promote ' + element.data('username') + ' to group admin?',
				extraWarning: 'Promote ' + element.data('username') + ' to group admin?\n\nYou will lose your group admin privileges.',
				url: '/group/modifyForm.php?owner=' + element.data('userid') + '&categoryID=' + element.data('categoryid')
			}

			var merged = $j.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Change admin
		 */
		function changeAdmin() {

			/* If you are not the superuser, and you are changing ownership to someone
			 * other than yourself, throw warning
			 */
			if (!config.super) {
				if (config.element.data('userid') != config.user) {
					if (confirm(config.extraWarning)) {
						window.location.href = config.url;
					}
				}
				/* A special case where you assign yourself to being the
				 * group admin. This shouldn't actually occur.
				 */
				else {
					if (confirm(config.standardWarning)) {
						window.location.href = config.url;
					}
				}
			}
			/* Process if agreed
			 */
			else {
				if (confirm(config.standardWarning)) {
					window.location.href = config.url;
				}
			}
		}
	}
})(jQuery);