/**
 * The Bargraph plugin I maked
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

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
            var html = '<div class="large-12 progress"><span class="meter" style="width: '+percentage+'%"></span></div>';

			return html;
		}
	}

	/**
	 * Constructor
	 */
	$.fn.bargraphPlainAmount = function(used, percentage) {
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
						{USED}\
					</div>\
				</div>\
			';

			var html = html.replace(/\{WIDTH\}/g, percentage);
			var html = html.replace(/\{USED\}/g, $.fn.formatBytes(used));

			return html;
		}
	}
}(jQuery));