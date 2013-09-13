/**
 * Update the percentage-complete bars for each section
 */
(function($) {
	$.fn.evaluationPercentageComplete = function(options) {
		var config = init(options);

		updateBar();

		/**
		 * Init
		 *
		 * @param options
		 * @returns {*}
		 */
		function init(options) {
			var defaults = {
			}

			var derived = {
				result: []
			}

			for (var i = 0; i < options.result.percentageComplete.length; i++) {
				var selector = '#evaluation-percentage-bar-' + options.result.percentageComplete[i].section_id;
				var percentage = options.result.percentageComplete[i].percentageComplete.toString();

				var element = {
					element: $(selector),
					value: percentage
				}

				derived.result.push(element);
			}

			var merged = $.extend(defaults, derived);
			return merged;
		}

		/**
		 * Update bar dimensions
		 */
		function updateBar() {
			for (var i = 0; i < config.result.length; i++) {
				config.result[i].element.css('width', config.result[i].value + '%');
			}
		}
	}
}(jQuery));


$(function() {
	$('#trigger').click(function() {
		$('.questionForm').each(function(index, element) {
			var id = $(element).attr('id').replace('questionForm-', '');

			var plugin = "$.fn." + $(element).data('plugin') + ".saveOne($('#questionForm-" + id + "'))";

			var hadoken = new Function(plugin);
			hadoken();
		});
	});
});


