/**
 * Update the percentage-complete bars for each section
 */
(function($j) {
	$j.fn.evaluation = function() {

	}

	/**
	 * Prepare some common setup activity
	 */
	$j.fn.evaluation.initNavigators = function() {

		/* Submit all the questions before switching between sections
		 */
		$j('.evaluation-navigator').click(function(event) {
			event.preventDefault();

			$j.fn.evaluation.saveAll($j(this).attr('href'));

			return false;
		});

		/* When someone finishes an assessment, loop through each question
		 * and submit its values - then redirect. This avoids a wierd timing
		 * problem which sometimes happens, where it redirects before it
		 * submits all the questionForm elements. This causes, in particular,
		 * Matching Pairs to remain untouched resulting in NULL values.
		 */
		$j('.evaluation-finish').click(function(event) {
			event.preventDefault();
			var href = $(this).attr('href');
			$j(this).confirmDialog({
				confirmEvent: function() {
					$j.fn.evaluation.saveAll(href);
				}
			});
		});
	}

	/**
	 * Save each individual question
	 */
	$j.fn.evaluation.saveAll = function(href) {
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
			var errors = false;

			$j('.questionForm').each(function(index, element) {
				var id = $j(element).attr('id').replace('questionForm-', '');

				var prefix = "$.fn." + $j(element).data('plugin');

				/* Run saveOne() multiple times
				 */
				var plugin = prefix + ".saveOne($('#questionForm-" + id + "'))";
				var hadoken = new Function(plugin);
				hadoken();

				/* Extract validation ruleset for this particular question type
				 */
				var method = prefix + ".getValidationRules()";
				var shoryuken = new Function('return ' + method);
				var configuration = shoryuken();

				$j(element).validate();

				/* Apply custom rules
				 */
				$j(element).find(configuration.element).rules('add', configuration.rules);

				/* If any aren't valid, mark as bad
				 */
				if (!$j(element).valid()) {
					errors = true;
				}
			});

			$j('.evaluation-saving').html('<p><strong>Saved</p></strong>');

			/* Detect errors, prevent redirection if any were made
			 * and hide that Saving box
			 */
			if (!errors) {
				location.href = href;
			}
			else {
				$('.evaluation-saving').hide();
			}
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




