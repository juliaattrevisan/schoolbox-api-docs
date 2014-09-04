/**
 * $.fn.pinterest({
 *   componentInstanceId: {COMPONENT_INSTANCE_ID}
 * });
 */
(function() {

	/**
	 * Compute dimensions dynamically
	 *
	 * @param options
	 */
	$.fn.pinterestUser = function(options) {
		options = options || {};

		var config = init(options, $(this));

		var dimensions = this.pinterestResize(config);

		/* Stupid jquery
		 */
		config.element.attr('data-pin-board-width', dimensions.width);
		config.element.attr('data-pin-scale-height', dimensions.height);

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				element: element,
				componentInstanceId: 0
			}

			var derived = {
				availableColumnWidth: $(defaults.element).parent('div').width(),
				maxWidth: 518,
				maxHeight: 300
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}
	}

	/**
	 * Compute dimensions dynamically
	 *
	 * @param options
	 */
	$.fn.pinterestBoard = function(options) {
		options = options || {};

		var config = init(options, $(this));

		var dimensions = this.pinterestResize(config);

		/* Stupid jquery
		 */
		config.element.attr('data-pin-board-width', dimensions.width);
		config.element.attr('data-pin-scale-height', dimensions.height);

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {
				element: element,
				componentInstanceId: 0
			}

			var derived = {
				availableColumnWidth: $(defaults.element).parent('div').parent('div').width(),
				maxWidth: 750,
				maxHeight: 300
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}
	}

	/**
	 * Resize pinterest canvas
	 */
	$.fn.pinterestResize = function(config) {
		var maxArea = config.maxWidth * config.maxHeight;

		if (config.availableColumnWidth < config.maxWidth) {
			var dimensions = {
				width: Math.floor(config.availableColumnWidth),
				height: Math.floor(maxArea / config.availableColumnWidth)
			}
		}
		else {
			var dimensions = {
				width: Math.floor(config.maxWidth),
				height: Math.floor(config.maxHeight)
			}
		}

		return dimensions;
	}
})(jQuery);