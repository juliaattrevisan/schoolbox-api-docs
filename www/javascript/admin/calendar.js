/**
 * Admin UI behaviours for editing calendar subscriptions
 *
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
		this.initUpdate();
		this.initRemove();
	},

	initConnectorStatus: function() {
		$j('.connectorActive').click(function() {
			var suffix = $j(this).attr('id').toString().replace(/connector_/, '');

			var parts = suffix.split('_');

			/* Ajax activate/deactivate status
			 */
			if ($j(this).is(':checked')) {
				console.log('activate all associated with ' + suffix);
			}
			else {
				console.log('deactivate all associated with ' + suffix);
			}
		});
	},

	/**
	 * Set up Add behaviours
	 */
	initAdd: function() {
		$j('a.addConfig').click(function() {
			var id = $j(this).attr('id').toString().replace(/add_/, '');

			/* Ajax add this config option
			 */
			console.log('add ' + id);

			/* Reload screen
			 */
		});
	},

	/**
	 * Set up Update behaviours
	 */
	initUpdate: function() {
		$j('input.updateConfig').change(function() {
			var id = $j(this).attr('id').toString().replace(/update_/, '');

			/* Ajax update this config option
			 */
			console.log('update ' + id);

			/* Reload screen
			 */
		});
	},

	/**
	 * Set up Remove behaviours
	 */
	initRemove: function() {
		$j('a.removeConfig').click(function() {
			var id = $j(this).attr('id').toString().replace(/remove_/, '');

			if (confirm('Are you sure you want to remove this configuration option?')) {
				/* Ajax remove this config option
				 */
				console.log('remove ' + id);

				/* Reload screen
				 */
			}

			return false;
		});
	}
}