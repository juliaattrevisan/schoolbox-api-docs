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
	init: function() {

		/* Connector status actions - activate/deactive individual connectors
		 * by setting all their Configs to inactive
		 */
		this.initConnectorStatus();

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
	 * Set up connector activate/deactivate
	 */
	initConnectorStatus: function() {
		var self = this;

		$j('.connectorActive').click(function() {
			var parts = $j(this).attr('id').toString().replace(/connector_/, '').split('_');

			/* Ajax activate/deactivate status
			 */
			if ($j(this).is(':checked')) {
				var data = {
					id: parts,
					status: 1
				};
			}
			else {
				var data = {
					id: parts,
					status: 0
				};
			}

			$j.post('/adminv2/calendar/updateStatus', data, function(responseText) {
				self.refresh();
			});
		});
	},

	/**
	 * Set up Add behaviours
	 */
	initAdd: function() {
		var self = this;

		$j('a.addConfig').click(function() {
			var id = $j(this).attr('id').toString().replace(/add_/, '');

			/* Ajax add this config option
			 */
			console.log('add ' + id);

			/* Reload screen
			 */
			self.refresh();
		});
	},

	/**
	 * Set up Remove behaviours
	 */
	initRemove: function() {
		var self = this;

		$j('a.removeConfig').click(function() {
			var id = $j(this).attr('id').toString().replace(/remove_/, '');

			if (confirm('Are you sure you want to remove this configuration option?')) {

				/* Ajax remove this config option
				 */
				var data = {
					id: id
				};

				/* Reload screen
				 */
				$j.post('/adminv2/calendar/removeOption', data, function(responseText) {
					self.refresh();
				});
			}

			return false;
		});
	}
}