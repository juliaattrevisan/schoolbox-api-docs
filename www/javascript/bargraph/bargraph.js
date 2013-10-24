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

	/**
	 * Convert bytes to kb, mb, gb etc
	 * http://codeaid.net/javascript/convert-size-in-bytes-to-human-readable-format-(javascript)
	 *
	 * ...and mangled by me
	 *
	 * @param bytes
	 * @returns {*}
	 */
	$.fn.formatBytes = function(bytes) {
		var kilobyte = 1024;
		var megabyte = kilobyte * 1024;
		var gigabyte = megabyte * 1024;
		var terabyte = gigabyte * 1024;
		var precision = 2;

		if ((bytes >= 0) && (bytes < kilobyte)) {
			return bytes + 'B';

		} else if ((bytes >= kilobyte) && (bytes < megabyte)) {
			return (bytes / kilobyte).toFixed(precision) + 'KB';

		} else if ((bytes >= megabyte) && (bytes < gigabyte)) {
			return (bytes / megabyte).toFixed(precision) + 'MB';

		} else if ((bytes >= gigabyte) && (bytes < terabyte)) {
			return (bytes / gigabyte).toFixed(precision) + 'GB';

		} else if (bytes >= terabyte) {
			return (bytes / terabyte).toFixed(precision) + 'TB';

		} else {
			return bytes + 'B';
		}
	}
}(jQuery));