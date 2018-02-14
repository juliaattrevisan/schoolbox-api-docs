(function() {
	$.fn.filterSelector = function(options) {
		options = options || {};

		var config = init(options, this);

		applyPopupToggle();
		applyCheckboxToggle();
        applyDefaultEventState();

		updateItems();

		/**
		 * Init
		 *
		 * @param options
		 * @param element
		 * @returns {*}
		 */
		function init(options, element) {
			var defaults = {

				/* The Filter button we just clicked on
				 */
				element: element,

				/* The element containing all the filter options
				 */
				filterContainer: null,

				/* The element containing all the events
				 */
				eventContainer: null,

				/* The element containing the export container
				 */
				exportContainer: null
			}

			var derived = {
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		/**
		 * Add ability to toggle popup on/off
		 */
		function applyPopupToggle() {
			$('#applyFilters').click(function(event) {
				event.preventDefault();

				$(config.filterContainer).foundation('reveal','close');

			});
		}

		/**
		 * Turn events on/off based on checkbox selection
		 */
		function applyDefaultEventState() {
			$.each($(config.filterContainer).find('.applyFilters'), function(index, element) {
				var attribute = $(this).val();

				if ($(element).is(':checked')) {
                    $(config.eventContainer).find('.'+attribute).show();
                }
                else {
                    $(config.eventContainer).find('.'+attribute).hide();
                }
			});

            toggleHeaders();
		}

		function updateItems()
		{
			// Build list of filters
			var availableClasses = { 'personal' : true };
            $('[data-update-filter]').each(function () {
				var checked = this.checked;
                if ($(this)[0].value !== undefined) {
                    // Annoyingly some have commas in their name (eg. group1,2) need to split this
                    // into two different class names

                    var name = $(this)[0].value.replace(/[^a-zA-Z]/g, '');
                    var numbers = $(this)[0].value.replace(/[^0-9,]/g, '').split(',');

                    for (var j = 0; j < numbers.length; j++) {
                        var reconstitutedName = name + numbers[j];
                        availableClasses[reconstitutedName] = checked;
                    }
                }
            });

            // console.log(filters);

			// At least one filter was selected

			$('[data-reveal-id]').each(function ()
			{
                var $this = $(this),
					groupedClasses = {};
                if ($this[0].classList.forEach) {
                    $this[0].classList.forEach(function(className) {
                        var prefix = className.replace(/^([a-z]+).*/i, '$1');
                        if (!groupedClasses[prefix]) {
                            groupedClasses[prefix] = []
                        }
                        groupedClasses[prefix].push(className);
                    });
                }

                var isRendered = true;
                $.each(groupedClasses, function(prefix, classes) {
                    var isFilterSelected = false,
                        isGroupFilterApplicable = false;

                    for (var i in classes) {
                        if (!classes.hasOwnProperty(i)) {
                            continue;
                        }

                        // If the class is in the list of SELECTABLE classes,
                        // the filter is applicable.
                        // Test whether the filter is selected.
                        if (classes[i] in availableClasses) {
                            isGroupFilterApplicable = true;
                            if (availableClasses[classes[i]]) {
                                isFilterSelected = true;
                                break;
                            }
                        }
                    }

                    // Don't render the event if the filter applies and isn't selected.
                    if (isGroupFilterApplicable && !isFilterSelected) {
                        isRendered = false;
                        return false;
                    }
                });

                // Hide if it didn't match in any of the groups, or if nothing at all was found by the
                // '[data-update-filter]:checked' selector
                if (!isRendered) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
			});

			// Hide date headers if there are no events visible inside

			$('[data-calendar-date]').each(function() {
				if ($(this).find('[data-reveal-id]:not([style*="display: none"])').length === 0) {
                    $(this).hide();
				} else {
                    $(this).show();
				}
			});
		}

		/**
		 * Turn events on/off based on checkbox selection
		 */
		function applyCheckboxToggle()
		{
			$.each($(config.filterContainer).find('.applyFilters'), function(index, element)
			{
				$(element).change(function()
				{
					updateItems();
				});
			});

			$.each($(config.filterContainer).find('[data-select-all-filter]'), function(index, element)
			{
				$(element).change(function()
				{
					$(this).parent().find(':checkbox').prop('checked', $(this).prop('checked'));
					updateItems();
				});
			});
		}

		/**
		 * Find out if the header should also hide, because
		 * there are no other events in this day
		 *
		 * @param attribute
		 */
		function toggleHeaders() {
			$.each($(config.eventContainer).find('ul'), function(index, element) {
				if ($(element).find('li').not(':hidden').length) {
					$(element).parent().prev('h2').show();
				}
				else {
					$(element).parent().prev('h2').hide();
				}
			});
		}
	}
})(jQuery);
