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

			var derived = {
				endUrl: '/' + options.evaluationType + '/end/' + options.evaluationId
			}

			var merged = $j.extend(defaults, options);
			merged.timeLeft = parseInt(merged.timeLeft);

			return merged;
		}

		/**
		 * Render countdown timer
		 */
		function renderCountdown() {
			if (config.timeLeft > 0) {
				config.element.countdown({
					until: '+' + config.timeLeft,
					layout: '{hn} : {mn} : {sn}',
					alwaysExpire: true,
					onExpiry: function() {
						window.location = config.endUrl;
					}
				});
			}

			if (config.element.html() == '') {
				config.element.parent().hide();
			}
		}
	}
}(jQuery));



