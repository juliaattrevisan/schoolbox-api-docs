(function($) {

	/**
	 * Constructor
	 */
	$.fn.bargraph = function(percentage) {
		return generateBar(percentage);

		/**
		 * Generates a bar of this length as a percentage
		 *
		 * @param percentage
		 * @returns {string}
		 */
		function generateBar(percentage) {
			var html = '\
				<div class="bar-container">\
					<div class="bar">\
						<span class="filled" style="width:{WIDTH}%"></span>\
					</div>\
					<div class="amount {GRADIENT}">\
						{WIDTH}%\
					</div>\
				</div>\
			';

			var html = html.replace(/\{WIDTH\}/g, percentage);
			var html = html.replace(/\{GRADIENT\}/g, determineGradient(percentage));

			return html;
		}

		/**
		 * Get the gradient class
		 *
		 * @param value
		 * @returns {string}
		 */
		function determineGradient(value) {
			var x = Math.floor(100-parseInt(value)).toString();
			x = x[0];

			return 'gradient-' + x;
		}
	}
}(jQuery));