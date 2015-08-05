/* Set up countdown
 */
(function($j) {
	$j.fn.evaluationCountdown = function(options) {
		var config = init(options, $j(this));

		renderCountdown();

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				evaluationId: 0,
				evaluationType: 'assess',
				element: element,
				timeLeft: 0
			}
			var mergedOptions = $j.extend({}, defaults, options);

			var derived = {
				endUrl: '/' + mergedOptions.evaluationType + '/end/' + mergedOptions.evaluationId
			}

			var merged = $j.extend({}, defaults, derived, mergedOptions);
			merged.timeLeft = parseInt(merged.timeLeft);

			return merged;
		}

		/**
		 * Render countdown timer
		 */
		function renderCountdown() {
			if (config.timeLeft > 0) {
				// TODO this needs to change to use server time, not device time [SB-9094]
				var endTime = new Date();
				endTime.setSeconds(endTime.getSeconds()+config.timeLeft);
				config.element.countdown(endTime)
					.on('update.countdown', function(event) {
						$(this).html(event.strftime('%H : %M : %S'));
					})
					.on('finish.countdown', function() {
						window.location = config.endUrl;
					})
			}
		}
	}
}(jQuery));



