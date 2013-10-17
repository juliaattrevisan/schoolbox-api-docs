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

	/**
	 * Constructor
	 */
	$.fn.bargraphNoAmount = function(percentage) {
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
				</div>\
			';

			var html = html.replace(/\{WIDTH\}/g, percentage);

			return html;
		}
	}

	/**
	 * Convert bytes to kb, mb, gb etc
	 * http://stackoverflow.com/questions/15900485/correctly-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
	 *
	 * ...and mangled by me
	 *
	 * @param bytes
	 * @returns {*}
	 */
	$.fn.formatBytes = function(bytes) {
		if (( bytes >> 30 ) & 0x3FF) {
			bytes = (bytes >>> 30) + 'GB';
		}
		else if ((bytes >> 20) & 0x3FF) {
			bytes = (bytes >>> 20) + 'MB';
		}
		else if ((bytes >> 10) & 0x3FF) {
			bytes = (bytes >>> 10)+ 'KB';
		}
		else if ((bytes >> 1) & 0x3FF) {
			bytes = (bytes >>> 1) + 'B';
		}
		else {
			bytes = bytes + 'B';
		}

		return bytes;
	}
}(jQuery));