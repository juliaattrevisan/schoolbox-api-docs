/**
 * Update the percentage-complete bars for each section
 */
(function($) {
	$.fn.evaluation = function() {

	}

	/**
	 * Prepare some common setup activity
	 */
	$.fn.evaluation.initNavigators = function() {

		/* Submit all the questions before switching between sections
		 */
		$('[data-evaluation-navigator]').click(function(event) {
			event.preventDefault();

			$.fn.evaluation.saveAll($(this).attr('href'));

			return false;
		});

		/* When someone finishes an assessment, loop through each question
		 * and submit its values - then redirect. This avoids a wierd timing
		 * problem which sometimes happens, where it redirects before it
		 * submits all the questionForm elements. This causes, in particular,
		 * Matching Pairs to remain untouched resulting in NULL values.
		 */
		$('[data-evaluation-finish]').click(function(event) {
			event.preventDefault();
			var href = $(this).attr('href');
			$(this).confirmDialog({
				confirmEvent: function() {
					$.fn.evaluation.saveAll(href);
				}
			});
		});
	}

	/**
	 * Save each individual question
	 */
	$.fn.evaluation.saveAll = function(href) {
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
			$('#evaluation-saving').removeClass('hide');
		}

		/**
		 * Save each question form
		 */
		function saveForms() {
			var errors = false;

			$('[data-question]').each(function(index, element) {
				var id = $(element).data('question');

				var prefix = "$.fn." + $(element).data('plugin');

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

				/* If configuration exists for this question type, apply settings
				 * otherwise ignore it
				 */
				if (configuration != undefined) {

					/* If the question is mandatory, apply validation requirements
					 */
					if ($(element).find('span[data-mandatory="required"]').length) {

						/* If a custom error location is desired
						 */
						if (configuration.errorContainer != undefined) {
							$(element).validate({
								errorPlacement: function(error, inputElement) {
									$(element).find(configuration.errorContainer).html(error);
								},
								ignore: ':hidden:not(:checkbox):not(:radio)'
							});
						}
						else {
							$(element).validate({
								ignore: ':hidden:not(:checkbox):not(:radio)'
							});
						}

						/* Apply custom rules if validation is required, in accordance with
						 * http://jquery.bassistance.de/validate/demo/
						 */
						$(element).find(configuration.element).rules('add', configuration.rules);

						/* If any aren't valid, mark as bad
						 */
						if (!$(element).valid()) {
							errors = true;
						}
					}
				}
			});

            /* Remove loading gif; show success label
             */
            $('#evaluation-saving').removeClass('loader').find('[data-evaluation-progress-saved]').removeClass('hide');

			/* Detect errors, prevent redirection if any were made
			 * and hide that Saving box
			 */
			if (!errors) {
				location.href = href;
			}
			else {
				$('#evaluation-saving').addClass('loader hide').find('[data-evaluation-progress-saved]').addClass('hide');
			}
		}
	}

	/**
	 * Update percentage complete bars
	 *
	 * @param options
	 */
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

			/* For every section's progress bar, distill important values
			 * and stack them up
			 */
			for (var i = 0; i < options.result.percentageComplete.length; i++) {
				var bar = '#evaluation-percentage-bar-' + options.result.percentageComplete[i].section_id;

				var barSelector = $(bar).find('[data-meter]');
				var valueSelector = $(bar).find('[data-perc-complete]');
				var percentage = options.result.percentageComplete[i].percentageComplete.toString();

				var element = {
					barSelector: $(barSelector),
					valueSelector: $(valueSelector),
					value: percentage
				}

				derived.result.push(element);
			}

			var merged = $.extend(defaults, derived);
			return merged;
		}

		/**
		 * Update bar dimensions for every section
		 */
		function updateBar() {
			for (var i = 0; i < config.result.length; i++) {
				config.result[i].barSelector.css('width', config.result[i].value + '%');
				config.result[i].valueSelector.text(config.result[i].value);
			}
		}
	}

	/**
	 * Translate an integer to letters
	 *
	 * @param $num
	 * @return string
	 */
	$.fn.evaluation.findLetterIndex = function(num) {
		var firstLetter = Math.floor(num / 26);
		var lastLetter = num % 26;

		/* Special case for z, az, bz, etc.
		 */
		if (!lastLetter) {
			firstLetter--;
			lastLetter = 26;
		}

		/* Convert from numeric to alpha
		 */
		if (firstLetter) {
			var string = String.fromCharCode(firstLetter+64) + String.fromCharCode(lastLetter+64);
		}
		else {
			var string = String.fromCharCode(lastLetter+64);
		}

		return string;
	}
}(jQuery));




