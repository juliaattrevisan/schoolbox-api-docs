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
		this.initAdd();
		this.initUpdate();
		this.initRemove();
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