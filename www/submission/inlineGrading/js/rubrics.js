/**
 * Example usage:
 *
$('#rubric-tbody').inlineGradingRubrics({
	scoreElement: '#sum',
	formElement: '#rubricsForm',
	renderGraph: false
	graph: {
		container: '#rubric-chart',
		data: [[0,"9"],[1,"9"],[2,"9"]],
		legs: [{"label":"moxy"},{"label":"gronk"},{"label":"hmmm"}],
		max: 9
	}
});
 */
(function($) {

	/**
	 * Add something to crocodoc for annotations
	 *
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.inlineGradingRubrics = function(options) {

		/* Optional arguments
		 */
		options = options || {}

		/* Store the config in this instance so private methods
		 * can access it, only if the chosen element exists
		 */
		if ($(this).length) {
			var config = init(options, this);

			/* Set up data and objects for optional graph
			 */
			setupGraph();
			renderGraph();
			/* Preselect existing values
			 */
			updateSelections();

			/* Add ability to change selections
			 */
			initSelectables();

			/* Set up validation rules for altering rubrics
			 */
			initForm();
		}

		/**
		 * Initialise various configured params and determine a bunch
		 * of ids and stuff that we'll probably use elsewhere
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				element: element,

				/* Tallied score goes here
				 */
				scoreElement: '#sum',

				/* The form which submits rubrics data
				 */
				formElement: '#rubricsForm',

				/* Option to render graph
				 */
				renderGraph: false,

				/* Optional graphing data
				 */
				graph: {
					container: '#rubric-chart',
					data: [],
					legs: [],
					max: 0
				}
			}

			var derived = {
				allRadios: element.find(':radio')
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Boolean to determine if a graph should be spawned
		 *
		 * @returns {boolean}
		 */
		function isGraph() {
			if (config.renderGraph === true) {
				return true;
			}

			return false;
		}

		/**
		 * Set up optional graph
		 */
		function setupGraph() {
			if (isGraph()) {
				config.rubricData = setupRubricChart(config.graph.data, config.graph.legs, config.graph.max);
				var container = $(config.graph.container);
				config.plot = $.plot(container, config.rubricData.spidata.points, config.rubricData.options);
				container.find('.flot-base, .flot-overlay')
					.attr('width', container.innerWidth())
					.attr('height', container.innerHeight())

				renderGraph();
			}
		}

		/**
		 * Render optional graph
		 */
		function renderGraph() {
			if (isGraph()) {
				config.plot.draw();
			}
		}

		/**
		 * Add event handlers for selectable rubrics criteria elements
		 */
		function initSelectables() {

			/* Each criterion can be turned on by clicking it
			 */
			config.element.find('.criterion-value').click(function(){
				$(this).find(':radio').prop('checked', true);

				updateSelections();
			});
		}

		/**
		 * Update selected elements
		 */
		function updateSelections() {
			var sum = 0;

			/* Remove previously selected radios and decorations
			 */
			$.each(config.allRadios, function(index, element) {
				$(element).parent('.criterion-value').removeClass('focused');
			});

			/* Reapply styles to each criterion value td containing a
			 * checked radio element
			 */
			$.each(config.element.find(':checked'), function(index, element) {
				$(element).parent('.criterion-value').addClass('focused');

				sum += parseInt($(element).val());

				/* Update graph
				 */
				if (isGraph()) {
					config.rubricData.spidata.points[0].data[$(element).data('index')] = [$(element).data('index'), parseInt($(element).val())];
				}
			});

			/* Update total marks assigned by rubrics
			 */
			$(config.scoreElement).html(sum);

			/* Update warning element
			 */
			updateWarningElements();

			/* Update graph
			 */
			renderGraph();
		}

		/**
		 * Update warning elements
		 *
		 * @returns {boolean}
		 */
		function updateWarningElements() {
			var success = true;

			$(config.element).find('.criterion-label-group').each(function() {
				var groupName = $(this).data('class');

				var container = $(config.element).find('td[data-class="'+groupName+'"]')
				var allRadios = container.find('input');
				var selectedRadios = container.find(':checked');

				/* If radios exist for a particular criteria group but
				 * nothing was selected, invalidate
				 */
				if (allRadios.length) {
					if (!selectedRadios.length) {
						success = false;
					}
					else {
						$(config.element).find('td.warning[data-class="'+groupName+'"]').hide();
					}
				}
				else {
					$(config.element).find('td.warning[data-class="'+groupName+'"]').hide();
				}
			});

			return success;
		}

		/**
		 * Set up validation behaviours for rubrics form
		 */
		function initForm() {
			$(config.formElement).alaressValidate({
				submitHandler: function(form) {
					var success = updateWarningElements();

					if (success) {
						form.submit();
					}
					else {
						$('#errnotif').removeClass('hide');
					}
				}
			});
		}
	}
}(jQuery));