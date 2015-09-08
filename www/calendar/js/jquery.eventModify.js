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
		setupSelectAll();

		// Advanced options no longer exist
		///* Show/hide advanced options
		// */
		//setupAdvancedOptions();

		/* Show/hide repeat event options
		 */
		setupRepeat();

		//setupOptionVariance();

		/* Form validator
		 */
		setupFormActions();
		setupEventValidation();

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
				functionLabel: ''
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
			config.element.find('select[name=repeat]').change(function(event) {
				if ($(this).val() !== '') {
					$('#repeatUntil').show();
                    $('[data-repeat-until]').removeClass('hide');
					config.element.find('input[name=repeat_until]').rules('add', 'required');
				}
				else {
					$('#repeatUntil, [data-repeat-until]').hide();
                    $('[data-repeat-until]').addClass('hide');
					config.element.find('input[name=repeat_until]').rules('remove', 'required');
				}
			});
		}

		/**
		 * Add "select all" capability to checkboxes
		 */
		function setupSelectAll() {
			config.element.find('[data-selectall]').change(function(){
				if ($(this).is(':checked')) {
					$(this).closest('fieldset').find(':checkbox').not('[data-selectall]').prop('checked', true);
				}
				else {
					$(this).closest('fieldset').find(':checkbox').not('[data-selectall]').prop('checked', false);
				}
			});
		}

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

					// the desired action, and if this is a series event or not
					var act = $('#action').val();
					var multi = $('#repeat-reminder, [data-repeat-reminder]').length != 0;

					// series
					if (multi) {
						$('[data-message=bulk-update]').css('display', act === 'Update' ? 'inline' : 'none');
						$('[data-message=bulk-delete]').css('display', act === 'Delete' ? 'inline' : 'none');
						$("[data-dialog=bulk]").dialog({
							resizable: false,
							height: 180,
							width: 500,
							modal: true,
							draggable: false,
							buttons: {
								"Only this event": function() {
									$('#efficacy').val('self');
									form.submit();
								},
								"All events in the series": function() {
									$('#efficacy').val('series');
									form.submit();
								},
								"Cancel": function() {
									$(this).dialog("close");
								}
							},
							close: function() {
								return false;
							}
						});
					}
					// single event
					else {
						if (act == 'Update') {
							form.submit();
						} else if (act == 'Delete') {
							// dialog time
							$("[data-dialog=bulk]").dialog({
								resizable: false,
									height: 180,
									width: 500,
									modal: true,
									draggable: false,
									buttons: {
									"Yes": function() {
										form.submit();
									},
									"No": function() {
										$(this).dialog("close");
									}
								},
								close: function() {
									return false;
								}
							});
						} else {
							form.submit();
						}
					}
				}
			});
		}
	}
})(jQuery);