(function($) {
	/**
	 * Constructor
	 *
	 * @param options
	 */
	$.fn.eventModify = function(options) {

		/* Optional parameter
		 */
		options = options || {}

		/* Store the config in this instance so private methods
		 * can access it
		 */
		var config = init(options, this);

		/* Add datepickers to various fields
		 */
		//setupDatepickers();

		/* Ensure one date is the same as, or before, the other
		 */
		//var startDateElement = config.element.find('input[name=startDate]');
		//var endDateElement = config.element.find('input[name=endDate]');
		//setupDateSync(startDateElement, endDateElement);

		/* All Day checkbox should hide the time fields
		 */
		//setupAllDay();
		//setupSelectAll();

		// Advanced options no longer exist
		///* Show/hide advanced options
		// */
		//setupAdvancedOptions();

		//setupOptionVariance();

		/* Form validator
		 */
		setupFormActions();
		setupEventValidation();

		/* Show/hide repeat event options (after validation)
		 */
		setupRepeat();

		setupDeleteActions(config.deleteButton);

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
				functionLabel: '',
				deleteButton: $()
			}

			var derived = {
			}

			var merged = $.extend(defaults, derived, options);

			return merged;
		}

		///**
		// * Assign datepickerability to everything
		// */
		//function setupDatepickers() {
		//	var allDatepickerElements = config.element.find('[data-datepicker="true"]');
        //
		//	$.each(allDatepickerElements, function(index, element) {
		//		$(element).datepicker({
		//			constrainInput: false,
		//			onClose: function(string) {
		//				$.getJSON('/ajax/strtotime.php', {string: string}, function(data) {
		//					$(element).val(data.time);
		//				});
		//			}
		//		});
		//	});
		//}

		///**
		// * Ensure that startDate is always before endDate
		// *
		// * @param startDate
		// * @param endDate
		// */
		//function setupDateSync(startDateElement, endDateElement) {
		//	$(startDateElement).change(function(event) {
		//		sync(startDateElement, endDateElement);
		//	});
        //
		//	$(endDateElement).change(function(event) {
		//		sync(startDateElement, endDateElement);
		//	});
        //
		//	/**
		//	 * Synchronise watches
		//	 *
		//	 * @param startDateElement
		//	 * @param endDateElement
		//	 */
		//	function sync(startDateElement, endDateElement) {
        //
		//		/* Sync only when two elements have values
		//		 * and when the endDate value is earlier than
		//		 * the startDate value
		//		 */
		//		if (startDateElement.val() && endDateElement.val()) {
		//			if (endDateElement.val() < startDateElement.val()) {
		//				endDateElement.val(startDateElement.val());
		//			}
		//		}
		//	}
		//}

		///**
		// * Allow All Day checkbox to toggle visibility of time fields
		// */
		//function setupAllDay() {
		//	config.element.find('[data-timeinputs]').hide();
        //
		//	config.element.find('input[name=allday]').change(function() {
		//		if ($(this).is(':checked')) {
		//			config.element.find('[data-timeinputs]').hide();
		//		}
		//		else {
		//			config.element.find('[data-timeinputs]').show();
		//		}
		//	});
		//}

		///**
		// * Set up Advanced Option toggle
		// */
		//function setupAdvancedOptions() {
		//	$('[data-customise]').click(function(event) {
		//		event.preventDefault();
        //
		//		/* Alternate between button texts
		//		 */
		//		$(this).children('span').toggle();
        //
		//		/* Alternate between option visibility
		//		 */
		//		$('[data-advanced]').toggle();
        //
		//	});
		//}

		/**
		 * Set up repeating events options
		 */
		function setupRepeat() {
			// TODO move this to hideWatch plugin (once we apply rules conditionally)
			var showRepeats = function() {
				var repeatInput = config.element.find('input[name=repeat_until]');
				if ($(this).val() !== '') {
					$('#repeatUntil').show();
					$('[data-repeat-until]').removeClass('hide');
					if (repeatInput.length) {
						repeatInput.rules('add', 'required');
					}
				}
				else {
					$('#repeatUntil, [data-repeat-until]').hide();
					$('[data-repeat-until]').addClass('hide');
					if (repeatInput.length) {
						repeatInput.rules('remove', 'required');
					}
				}
			};
			var repeatSelector = config.element.find('select[name=repeat]');
			repeatSelector.change(showRepeats);
			showRepeats.apply(repeatSelector);
		}

		///**
		// * Add "select all" capability to checkboxes
		// */
		//function setupSelectAll() {
		//	config.element.find('[data-selectall]').change(function(){
		//		if ($(this).is(':checked')) {
		//			$(this).closest('fieldset').find(':checkbox').not('[data-selectall]').prop('checked', true);
		//		}
		//		else {
		//			$(this).closest('fieldset').find(':checkbox').not('[data-selectall]').prop('checked', false);
		//		}
		//	});
		//}

		///**
		// * When you change Level, only certain other options become
		// * available to you
		// */
		//function setupOptionVariance() {
		//	$('select[name="level"]').change(function() {
		//		$('#groupDrop, #campusList, #roleList, #attendance').hide();
        //
		//		/* Personal
		//		 */
		//		if ($(this).val() == '1') {
		//			var attendance_input = $('#attendance input');
        //
		//			attendance_input.prop('checked', false);
        //
		//			if (attendance_input.parent().hasClass('active-checkbox')) {
		//				attendance_input.parent().removeClass('active-checkbox')
		//			}
		//			else {
		//				attendance_input.parent().addClass('active-checkbox');
		//			}
		//		}
		//		/* Group
		//		 */
		//		else if ($(this).val() == "2") {
		//			$('#groupDrop, #attendance').show()
		//		}
		//		/* Campus
		//		 */
		//		else if ($(this).val() == "3") {
		//			$('#campusList, #roleList, #attendance').show()
		//		}
		//	});
        //
		//	/* Defaults, I guess
		//	 */
		//	if ($('select[name="level"]').val() == "2") {
		//		$('#groupDrop, #attendance').show()
		//	}
		//	else if ($('select[name="level"]').val() == "3") {
		//		$('#campusList, #roleList, #attendance').show()
		//	}
		//}

		/**
		 * Apply button actions
		 *
		 * Now, all #b{action} change the form's action.
		 */
		function setupFormActions() {
			config.element.find(':submit[id^="b"]').on('click', function() {
				config.element.find('#action').val(this.id.substr(1));
			});
		}

		/**
		 * Applies delete actions to the delete button.
		 *
		 * * single event: show confirm dialog (which will use foundation reveals)
		 * * multi event: ask if applying to this event or all in series
		 *
		 * @param deleteButton
		 */
		function setupDeleteActions(deleteButton) {
			var multi = $('#repeat-reminder, [data-repeat-reminder]').length != 0;
			if (multi) {
				deleteButton.on('click', function(event) {
					event.preventDefault();
					var dialog = $('[data-dialog="bulk-delete"]');
					dialog.foundation('reveal', 'open');
					// Do stuff with buttons
					setupBulkDialogEvents(
						dialog,
						function() {
							// Add self to href
							location.href = deleteButton.attr('href')+'&efficacy=self';
						},
						function() {
							// Add series to href
							location.href = deleteButton.attr('href')+'&efficacy=series';
						}
					);
				});
			} else {
				deleteButton.on('click', function(event) {
					event.preventDefault();
					deleteButton.confirmDialog({
						text:         'Are you sure you want to delete this event?',
						confirmEvent: $.confirmDialog.followHref
					});
				})
			}
		}

		/**
		 * Resets events on bulk modify or delete dialog.
		 *
		 * @param dialog
		 * @param selfCallback   Call this when updating or deleting this event only
		 * @param seriesCallback Call this when updating or deleting entire series
		 */
		function setupBulkDialogEvents(dialog, selfCallback, seriesCallback)
		{
			// Rebind events on open/close/cancel
			dialog.find('[data-action="self"]')
				.off('click')
				.on('click', function(e) {
					e.preventDefault();
					dialog.foundation('reveal', 'close');
					selfCallback();
				});
			dialog.find('[data-action="series"]')
				.off('click')
				.on('click', function(e) {
					e.preventDefault();
					dialog.foundation('reveal', 'close');
					seriesCallback();
				});
			dialog.find('[data-action="cancel"]')
				.off('click')
				.on('click', function(e) {
					e.preventDefault();
					dialog.foundation('reveal', 'close');
				});
		}

		/**
		 * Validate form data
		 */
		function setupEventValidation() {
			// Campus selection validation - only required if eventType === Campus
			var validateCampusSelected = function() {
				var normalSelector = $('select[name="level"]');

				/* Normal calendar selector
				 */
				if (normalSelector.length) {
					return $('select[name="level"]').val() == 3;
				}
				/* For EOTC
				 */
				else {
					return $('input[name="campus[]"]').length;
				}
			}

			/* Check if a Group is selected when using the Group
			 * level option in the dropdown
			 */
			var validateGroupSelected = function() {
				var level = parseInt($('select[name=level]').val());

				if (level == 2) {
					return true;
				}

				return false;
			}

			/* For EOTC
			 * Disabled need for files to be uploaded, regardless of the setting
			 */
			var validateRamsFile = function() {
				return false;
			}

			/* For EOTC
			 */
			var validateTeacherInCharge = function() {
				var ticExists = $('input[name=teacherInCharge]').length;

				if (ticExists) {
					var ticValue = $('input[name=teacherInCharge]').val()

					if (ticValue == '[]' || ticValue == '') {
						return true;
					}
				}

				return false;
			}

            var validateNotAllDaySelected = function() {
                var allDaySelector = $(':checkbox[name="allday"]');
                if (allDaySelector.length && !allDaySelector.prop('checked')) {
                    return true;
                }
                // No all day selector (EOTC)? Don't apply this rule
                return false;
            };

			$(config.element).validate({
				rules: {
					'campus[]': {
						required: {
							depends: validateCampusSelected
						},
						minlength: {
							param: 1,
							depends: validateCampusSelected
						}
					},
					'ramsFile': {
						required: {
							depends: validateRamsFile
						},
						minlength: {
							param: 1
						}
					},
					'teacherInCharge': {
						required: {
							depends: validateTeacherInCharge
						},
						minlength: {
							param: 1
						}
					},
					'group[]': {
						required: {
							depends: validateGroupSelected
						}
					},
                    startHour: {
                        required: {
                            depends: validateNotAllDaySelected
                        },
                        '12hour': {
                            depends: validateNotAllDaySelected
                        }
                    },
                    startMinute: {
                        required: {
                            depends: validateNotAllDaySelected
                        },
                        minute: {
                            depends: validateNotAllDaySelected
                        }
                    },
                    endHour: {
                        required: {
                            depends: validateNotAllDaySelected
                        },
                        '12hour': {
                            depends: validateNotAllDaySelected
                        }
                    },
                    endMinute: {
                        required: {
                            depends: validateNotAllDaySelected
                        },
                        minute: {
                            depends: validateNotAllDaySelected
                        }
                    }
				},
                errorPlacement: function(error, element) {
                    var cell = element.closest('td');
                    if (cell.length) {
                        error.appendTo(cell);
                    } else {
                        error.insertAfter(element);
                    }
                },
				submitHandler: function(form) {
					var multi = $('#repeat-reminder, [data-repeat-reminder]').length != 0;
					if (multi) {
						// series - show a dialog
						var dialog = $('[data-dialog="bulk-modify"]');
						dialog.foundation('reveal', 'open');
						setupBulkDialogEvents(
							dialog,
							function() {
								$('#efficacy').val('self');
								form.submit();
							},
							function() {
								$('#efficacy').val('series');
								form.submit();
							}
						);
					}
					else {
						// single event - just submit
						form.submit();
					}
				}
			});
		}
	}
})(jQuery);