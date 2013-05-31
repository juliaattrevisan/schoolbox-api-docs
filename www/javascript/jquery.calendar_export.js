(function() {
	"use strict";

	var $ = jQuery;

	/**
	 * Method for exporting a calendar link.
	 *
	 * @param token  The user's token
	 * @param values A plain object, or list from jQuery.fn.serializeArray
	 *
	 * @return string
	 */
	$.getExportCalendarLink = function(token, values) {

		//
		// If from serializeArray: convert to object
		if ($.isArray(values)) {
			var new_values = {};
			$.each(values, function(k,v) {
				new_values[v.name] = v.value;
			});
			values = new_values;
		}

		//
		// Add token to object
		values.token = token;

		//
		// remove unused fields
		if (values['export'] !== 'group') {
			delete values.group;
		}
		if (values['export'] !== 'campus') {
			delete values.campus;
		}
		return window.location.protocol+'//'+window.location.host+'/calendar/export.php?'+$.param(values);
	};

})();