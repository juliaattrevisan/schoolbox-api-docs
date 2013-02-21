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
		this.updateInit();
		this.removeInit();
	},

	/**
	 * Set up Update behaviours
	 */
	updateInit: function() {
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
	removeInit: function() {
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