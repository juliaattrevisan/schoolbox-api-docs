/**
 * Update the percentage-complete bars for each section
 */
(function($j) {
	$j.fn.evaluation = function() {
	}

	/**
	 * Save each individual question
	 */
	$j.fn.evaluation.saveAll = function() {
		init();

		/* Allow display of saving indicator before locking browser up
		 * by using a delayed response
		 *
		 * http://stackoverflow.com/questions/4971062/jquery-ajax-synchronous-call-beforesend
		 */
		setTimeout(saveForms, 2500);

		/**
		 * Init - show loading div
		 */
		function init() {
			$j('.evaluation-saving').show();
		}

		/**
		 * Save each question form
		 */
		function saveForms() {
			$j('.questionForm').each(function(index, element) {
				var id = $j(element).attr('id').replace('questionForm-', '');

				var plugin = "$.fn." + $j(element).data('plugin') + ".saveOne($('#questionForm-" + id + "'))";

				var hadoken = new Function(plugin);
				hadoken();
			});

			$j('.evaluation-saving').html('<h2>Saved</h2>');

			/* TODO: Redirect upon completion
			 */
		}
	}

	/**
	 * Update percentage complete bars
	 *
	 * @param options
	 */
	$j.fn.evaluationPercentageComplete = function(options) {
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

			/* For every section's progress bar, distill important values
			 * and stack them up
			 */
			for (var i = 0; i < options.result.percentageComplete.length; i++) {
				var bar = '#evaluation-percentage-bar-' + options.result.percentageComplete[i].section_id;

				var barSelector = $j(bar).find('.percentage-bar');
				var valueSelector = $j(bar).find('.percentage-text');
				var percentage = options.result.percentageComplete[i].percentageComplete.toString();

				var element = {
					barSelector: $j(barSelector),
					valueSelector: $j(valueSelector).find('em'),
					value: percentage
				}

				derived.result.push(element);
			}

			var merged = $j.extend(defaults, derived);
			return merged;
		}

		/**
		 * Update bar dimensions for every section
		 */
		function updateBar() {
			for (var i = 0; i < config.result.length; i++) {
				config.result[i].barSelector.css('width', config.result[i].value + '%');
				config.result[i].valueSelector.html(config.result[i].value);
			}
		}
	}
}(jQuery));




