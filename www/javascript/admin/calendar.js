/**
 * Admin UI behaviours for editing calendar subscriptions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 * @type {Object}
 */
var calendar = {

	/**
	 * Init
	 */
	init: function(options) {

        var defaults = {
            addConfig:    'a.addConfig',
            addUri:       '/adminv2/calendar/addOption',
            removeConfig: 'a.removeConfig',
            removeUri:    '/adminv2/calendar/removeOption'
        };

        this.options = $.extend({}, defaults, options);

		/* Config actions - add, update and remove individual configs
		 */
		this.initAdd();
		this.initRemove();
	},

	/**
	 * Reload screen
	 */
	refresh: function() {
		window.location.href = window.location.href;
	},

	/**
	 * Set up Add behaviours
	 */
	initAdd: function() {
		var self = this;

		$(this.options.addConfig).click(function() {

			/* Only add if all inputs are filled
			 * Jump to latest input field when trying to add while you have an empty input
			 *
			 * This prevents you from adding a url, not hitting Update, then adding a new record
			 * which refreshes the screen - causing you to lose the url
			 */
			if (!$(this).parent().find('input[value=""]').length) {
				var id = $(this).attr('id').toString().replace(/add_/, '');

				/* Ajax add this config option
				 */
				var data = {
					id: id
				};

				$.post(self.options.addUri, data, function(responseText) {
					self.refresh();
				});
			}
			else {
				$(this).parent().find('input[value=""]').focus();
			}
		});
	},

	/**
	 * Set up Remove behaviours
	 */
	initRemove: function() {
		var self = this;

		$(self.options.removeConfig).click(function() {
			var id = $(this).attr('id').toString().replace(/remove_/, '');

			if (confirm('Are you sure you want to remove this configuration option?')) {

				/* Ajax remove this config option
				 */
				var data = {
					id: id
				};

				$.post(self.options.removeUri, data, function(responseText) {
					self.refresh();
				});
			}

			return false;
		});
	}
}