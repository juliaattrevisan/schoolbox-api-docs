(function (adtp, $, undefined) {
    // -----------------------------------------------------------------------
    // Public vars & methods
    // -----------------------------------------------------------------------
    adtp.dayjsFormats = {
        database: "YYYY-MM-DD HH:mm:ss",
        databaseShort: "YYYY-MM-DD",
        nice: "dddd, MMM D, YYYY",
    };

    // Use global counter in case multiple alaressDateTimePicker() have been called from one page
    var pickerCounter = 0;
    adtp.countPickers = function () {
        pickerCounter++;
        return pickerCounter;
    };

    /**
     * To keep the date format string the same between the flatpickr format string, and dayjs need to create a mapping function
     *
     * @param format
     */
    adtp.convertFormat = function (format) {
        var formatMap = {
                d: "DD",
                D: "ddd",
                j: "D",
                J: "Do",
                l: "dddd",
                K: "A",
                S: "SS",
                w: "d",
                W: "W",
                F: "MMMM",
                m: "MM",
                M: "MMM",
                n: "M",
                o: "GGGG",
                Y: "YYYY",
                y: "YY",
                a: "a",
                A: "A",
                g: "h",
                G: "H",
                h: "hh",
                H: "HH",
                i: "mm",
                O: "ZZ",
                P: "Z",
            },
            formatEx = /[dDjJlNKSwWFmMnoYyaAgGhHisOP]/g;

        // Just return the converted format
        return format.replace(formatEx, function (format) {
            return formatMap[format];
        });
    };

    /**
     *
     * @param input
     * @param attribute
     * @returns {boolean}
     */
    adtp.getValue = function (input, attribute) {
        /* input doesn't exist; was never in the dom
         */
        if (input === undefined) {
            return false;
        }

        var value = input.attr(attribute);

        // There is no such attribute, return false
        if (value === undefined) {
            return false;
        }
        // The attribute is there and has a value, return it
        else if (value.length > 0) {
            return value;
        }
        // The attribute is there, but no value is set, return true
        else {
            return true;
        }
    };

    /**
     *
     * @param element
     * @returns {object}
     */
    adtp.getOptions = function (element) {
        var buttonText = adtp.getValue(element, "data-datepicker-buttontext");

        // Build datepicker options form the element data attributes
        var elementOptions = {
            id: adtp.getValue(element, "data-datepicker-id"),
            noButton: adtp.getValue(element, "data-datepicker-nobutton"),
            buttonText: buttonText === true ? "" : buttonText || "Select Date",
            displayFormat: dateDisplayFormats.localDateTime, //Default display datetime format
            displayFormatDate: dateDisplayFormats.localDate, //Default display date format
            databaseFormat: "Y-m-d H:i:s",
            enableTime: adtp.getValue(element, "data-datepicker-time"),
            allDaySwitch: adtp.getValue(element, "data-datepicker-alldayswitch"), //Show All Day switch
            allDayInput: adtp.getValue(element, "data-datepicker-alldayinput"), //Sync with external AllDay input hidden field
            altFormat: adtp.getValue(element, "data-datepicker-format"), // false|date|datetime
            type: adtp.getValue(element, "data-datepicker-call"), //Ajax call type: events, duework or nothing
            endDay: adtp.getValue(element, "data-datepicker-endofday"), // Should allday change time to 23:59:59
            endPeriod: adtp.getValue(element, "data-datepicker-endofperiod"), // It will use end of the period to set time through timetable picker
            staller: null,
            hideTimetable: adtp.getValue(element, "data-datepicker-hide-timetable"), // Hide timetable picker when set
        };
        return elementOptions;
    };

    /**
     * Takes an element and syncs it with it's start and end date as per its data attribs
     * Will run itself in one direction until it stops finding a start or end date set
     *
     * @param {jQuery} $element
     * @param {object} options
     * @param {string} from - used to set a direction this will go in, start or end
     */
    adtp.syncDatesOnElement = function ($element, options, from) {
        var dateFormat = fieldHasTime(options) ? dateDisplayFormats.dayjsDatetime : dateDisplayFormats.dayjs;

        var syncOptions = {
            dateFormat: dateFormat,
        };

        if ($element.attr("data-datesync-startdate") !== undefined && from !== "end") {
            syncOptions.startDate = $element.attr("data-datesync-startdate");
            syncOptions.endDate = $element;

            $element.syncDatesNow(syncOptions);

            // Run this again for the start element
            adtp.syncDatesOnElement($($element.attr("data-datesync-startdate")), options, "start");
        }

        if ($element.attr("data-datesync-endDate") !== undefined && from !== "start") {
            syncOptions.startDate = $element;
            syncOptions.endDate = $element.attr("data-datesync-endDate");

            $element.syncDatesNow(syncOptions);

            // Run this again for the end element
            adtp.syncDatesOnElement($($element.attr("data-datesync-endDate")), options, "end");
        }
    };

    /**
     *
     * @param state
     * @param pickerId
     */
    adtp.setCalendarAllDay = function (state, pickerId) {
        var $timeDiv = $("#pickerCalendar" + pickerId)
            .closest("#adtp-modal-" + pickerId)
            .find(".flatpickr-time");

        // All Day is on
        if (state === true) {
            $timeDiv.hide();
            $("#hideTime" + pickerId).prop("checked", true);
        } else {
            $timeDiv.show();
            $("#hideTime" + pickerId).prop("checked", false);
        }
    };

    // -----------------------------------------------------------------------
    // Private methods
    // -----------------------------------------------------------------------

    /**
     * Check if this field should have time given its options and whether all day is on
     *
     * @param {object} options
     * @returns {boolean}
     */
    function fieldHasTime(options) {
        // Only retuning true if time is enabled, just a matter of whether all day is enabled and if so -
        // is it currently activated?
        return (
            (options.enableTime && options.allDayInput === false) ||
            (options.enableTime && $("#" + options.allDayInput).val() == 0)
        );
    }
})((window.adtp = window.adtp || {}), jQuery);

// Extend jQuery with our datepicker function
$.fn.alaressDateTimePicker = function (optionsOverride) {
    var element = this;

    // Build datepicker options form the element data attributes
    var options = adtp.getOptions(element);

    // Override with user any options --- should this stay? Todo
    for (var attribute in optionsOverride) {
        options[attribute] = optionsOverride[attribute];
    }

    if (!options.noButton && !element.data("dateButton")) {
        // install button (no more than once)

        // rearrange the DOM to install the button
        var $label = element.closest("label");

        if ($label.length) {
            // in this case, the DOM needs to be rearranged so that the flex button is adjacent to the label, not inside
            $label.attr("for", element.attr("id"));
            element.detach();
            $label.after(element);
        }

        element.wrap($('<div class="input-group" />'));

        var $datebtn = $('<a class="adtp-btn icon-calendar" data-adtp-btn />');

        if (options.buttonText) {
            $datebtn.text(options.buttonText);
        } else {
            $datebtn.addClass("no-text");
        }
        element.data("dateButton", $datebtn).after($datebtn);
    }

    var $datepickerButton = $(this).siblings("[data-adtp-btn]");

    window.pickerInstance = [];

    // Count this picker in
    var pickerId = adtp.countPickers();

    // Set pickerID to the element attr
    element.attr("data-datepicker-id", pickerId);

    var modalSize = options.type == "event" || options.type == "duework" ? "pickerDouble" : "pickerSingle";
    $("body").append(
        '<div id="adtp-modal-wrapper-' +
            pickerId +
            '"><div data-reveal id="adtp-modal-' +
            pickerId +
            '" class="adtp reveal-modal no-pad ' +
            modalSize +
            '" data-adtp></div></div>',
    );

    // On focus of the element- store the original value in a data var, so that we can compare before 'massaging' (strtotime)
    element.on("focus", function () {
        element.data("originalValue", this.value);
    });

    // Listen for blur or enter press- apply strtotime
    element
        .on("blur", function (e) {
            massageDateValue(e, options.staller);
        })
        .on("keydown", function (e) {
            if (e.keyCode === 13) {
                massageDateValue(e, options.staller);
                return true;
            }
        });

    // Set the initial date value if there is one
    if (adtp.getValue(element, "data-datepicker-initial-value")) {
        // Function getValue() returns true when the attribute value is a blank string.
        // If the attribute value is a blank string, there is no initial value to set.
        const initialDate = adtp.getValue(element, "data-datepicker-initial-value");
        if (initialDate !== true) {
            setValue(element, options, false, false, initialDate);
        }
    }

    // Create click event to open the modal for the element
    $datepickerButton.click(function () {
        var originalValue;

        if (element.val().length > 0) {
            originalValue = dayjs(element.val(), [
                adtp.convertFormat(options.displayFormat),
                adtp.convertFormat(options.displayFormatDate),
            ]);
        }
        // if no value has been passed, use today
        else {
            originalValue = dayjs();
        }
        if (options.allDayInput !== false && $("#" + options.allDayInput).val() == 1) {
            originalValue = dayjs(originalValue, [
                adtp.convertFormat(options.displayFormat),
                adtp.convertFormat(options.displayFormatDate),
            ]);
        }

        var $modal = $("#adtp-modal-" + pickerId);

        // Only AJAX in the picker if we haven't already

        if ($modal.data("status") === undefined) {
            // Update picker status
            $modal.data("status", "loading");

            //Check if the form is inside another modal
            var reveal = element.parents("*[data-reveal]");
            if (reveal.length > 0) {
                $modal.data("reveal", reveal.attr("id"));
            }

            // if datetime, pass date only
            // AJAX in the modal contents if they haven't already been loaded
            $modal.load(
                "/picker/datetime/type/" +
                    options.type +
                    "/element/" +
                    element.attr("id") +
                    "/id/" +
                    pickerId +
                    "/value/" +
                    encodeURIComponent(originalValue.format(adtp.dayjsFormats.database)) +
                    `${options.hideTimetable === "true" ? "/hideTimetable/" : ""}`,
                function () {
                    // Capture closure of modal
                    $(document).on("closed.fndtn.reveal", "#adtp-modal-" + pickerId, function () {
                        // Open back the parent modal, if any
                        if ($modal.data("reveal").length > 0) {
                            $("#" + $modal.data("reveal")).foundation("reveal", "open");
                        }
                    });
                    // Update picker status
                    $modal.data("status", "loaded");

                    // Display the datetime picker modal
                    $modal.foundation("reveal", "open");
                    // Initialise tabs
                    $modal.foundation("reflow", "tab");

                    var $datePicker = $("#byDate-" + pickerId),
                        $timetablePicker = $("#byTimetable-" + pickerId);

                    $datePicker.find(".picker-submit").click(function () {
                        if ($("#hideTime" + pickerId).is(":checked")) {
                            setValue(element, options, true, true);
                        } else {
                            setValue(element, options, false, true);
                        }

                        if (options.allDayInput !== false) {
                            var linkedElements = getLinkedElements(element, options);
                            linkedElements.forEach(function (item) {
                                var opts = adtp.getOptions(item);

                                setValue(item, opts, $("#hideTime" + pickerId).is(":checked"), true);

                                /* Entangle All Day settings for linked elements
                                 */
                                if ($("#hideTime" + pickerId).is(":checked")) {
                                    adtp.setCalendarAllDay(true, opts.id);
                                } else {
                                    adtp.setCalendarAllDay(false, opts.id);
                                }
                            });
                        }

                        // Sync dates if applicable
                        element.trigger("datepickerChange");

                        adtp.syncDatesOnElement(element, options, "");

                        // Close modal
                        $modal.foundation("reveal", "close");
                    });

                    $timetablePicker.find("[data-adtp-tt-submit]").click(function () {
                        var newDate = dayjs($(this).attr("data-timetable-value"));
                        var selectedCampus = $timetablePicker.find("[data-adtp-tt-campus]").val();
                        var timetableAllDay = false;

                        if (options.enableTime === true) {
                            var selectedPeriod = $timetablePicker
                                .find("[data-adtp-tt-period][data-campus=" + selectedCampus + "]")
                                .val();

                            if (selectedPeriod == "allday") {
                                timetableAllDay = true;
                                if (options.endDay) {
                                    newDate = newDate.endOf("day");
                                } else {
                                    newDate = newDate.startOf("day");
                                }
                            } else {
                                var periodTime = $timetablePicker
                                    .find('[name*="periodTime' + pickerId + '"]:checked')
                                    .val();
                                var newTime = dayjs(
                                    $timetablePicker
                                        .find(
                                            "[data-adtp-tt-period][data-campus=" + selectedCampus + "] option:selected",
                                        )
                                        .attr("data-" + periodTime + "-time"),
                                    "h:mma",
                                );
                                newDate = newDate.hour(newTime.hour()).minute(newTime.minute()).second(0);
                            }
                        }

                        setValue(element, options, timetableAllDay, true, newDate);
                        // Set allday state on the calendar picker
                        adtp.setCalendarAllDay(timetableAllDay, pickerId);

                        if (options.allDayInput !== false) {
                            var linkedElements = getLinkedElements(element, options);
                            linkedElements.forEach(function (item) {
                                setValue(item, adtp.getOptions(item), timetableAllDay, true);
                                adtp.setCalendarAllDay(timetableAllDay, adtp.getOptions(item).id);
                            });
                        }

                        // Sync dates if applicable
                        adtp.syncDatesOnElement(element, options, "");

                        // Close the modal
                        $modal.foundation("reveal", "close");
                    });
                },
            );
        } else if ($modal.data("status") === "loaded") {
            $modal.foundation("reveal", "open");
        }
    });

    // -----------------------------------------------------------------------
    // Private methods
    // -----------------------------------------------------------------------

    /**
     *
     * @param allDayState
     * @param options
     * @returns {{convertFrom: *, convertTo: *}}
     */
    function getPickerFormats(allDayState, options) {
        // Getting element date/datetime format settings
        var from;
        var to;

        // Format is always date or datetime
        if (options.altFormat !== false) {
            if (options.altFormat === "date") {
                from = options.displayFormatDate;
                to = options.displayFormatDate;
            } else if (options.altFormat === "datetime") {
                from = options.displayFormat;
                to = options.displayFormat;
            }
        } else {
            // If no time, format is date
            if (options.enableTime === false) {
                from = options.displayFormatDate;
                to = options.displayFormatDate;
            } else {
                // Or... looking at the allDaySwitch option
                if (options.allDaySwitch === true) {
                    // Ok, the allDaySwitch changed, changing the format accordingly
                    if (allDayState) {
                        from = options.displayFormat;
                        to = options.displayFormatDate;
                    } else {
                        from = options.displayFormat;
                        to = options.displayFormat;
                    }
                } else {
                    from = options.displayFormat;
                    to = options.displayFormat;
                }
            }
        }

        return {
            convertFrom: from,
            convertTo: to,
        };
    }

    /**
     *
     * @param element
     * @param options
     * @returns {Array}
     */
    function getLinkedElements(element, options) {
        // Get all linked inputs, e.g. startDate, endDate
        var elements = $("[data-datepicker-alldayinput = " + options.allDayInput + "]");
        var linkedElements = [];
        elements.each(function () {
            if ($(this)[0] !== element[0]) {
                linkedElements.push($(this));
            }
        });
        return linkedElements;
    }

    /**
     *
     * @param element
     * @param options
     * @param state
     * @param trigger
     * @param date
     */
    function setValue(element, options, state, trigger, date) {
        // Get their pickerIds
        var pId = element.attr("data-datepicker-id");

        // Get the format
        var pickerFormats = getPickerFormats(state, options);
        var value;

        if (date !== undefined) {
            value = dayjs(date);
        } else if (window.pickerInstance[pId] !== undefined && window.pickerInstance[pId].input.value) {
            value = dayjs(window.pickerInstance[pId].input.value);
        } else if (element.val() !== undefined && element.val()) {
            value = dayjs(element.val(), [
                adtp.convertFormat(options.displayFormat),
                adtp.convertFormat(options.displayFormatDate),
            ]);
            // debugger;
        } else {
            value = dayjs();
        }

        // All Day is on
        var v;
        if (state === true) {
            if (options.endDay) {
                v = value.endOf("day").format(adtp.convertFormat(pickerFormats.convertTo));
            } else {
                v = value.startOf("day").format(adtp.convertFormat(pickerFormats.convertTo));
            }

            if (window.pickerInstance[pId] !== undefined) {
                window.pickerInstance[pId].setDate(v.toISOString);
            }
            element.val(v);
        } else {
            v = dayjs(value).format(adtp.convertFormat(pickerFormats.convertTo));
            if (window.pickerInstance[pId] !== undefined) {
                window.pickerInstance[pId].setDate(v.toISOString);
            }
            element.val(v);
        }

        if (trigger === true) {
            element.trigger("change");
        }

        if (options.allDayInput !== false) {
            $("#" + options.allDayInput).val(state ? 1 : 0);
        }

        // Hide any possible validation error from the field
        $("#" + element.attr("id") + "-error").hide();
        $(element).removeClass("error");
    }

    /**
     * Massages the datetime picker's value into a valid format, or errors out if it cannot.
     *
     * This uses a client-side strtotime implementation to convert the input's
     * value to a recognisable date value.
     *
     * @param {jQuery.Event} e       a jQuery event
     * @param {jQuery}       staller if given, the element to attach the AJAX promise to
     */
    function massageDateValue(e, staller) {
        var t = e.target,
            $t = $(t),
            val = $t.val(),
            originalVal = $t.data("originalValue");
        if (val !== "" && val !== originalVal) {
            // set up params to guide parsing
            var opts = adtp.getOptions($(t));
            var $fieldError = $("#" + $(t).attr("id") + "-error"); // A possible validation error for this field

            // Hide any validation error while this is processing
            $fieldError.hide();

            // Make the field readonly
            $(t).prop("readonly", true);

            var timestamp = parseTime(t.value);

            // If it couldn't parse a date - show a validation error on the form
            if (timestamp === false) {
                if (!$fieldError.length) {
                    // don't create multiple errors

                    var error =
                        '<label id="' +
                        $(t).attr("id") +
                        '-error" class="error" for="' +
                        $(t).attr("id") +
                        '">Please enter a valid date</label>';

                    if ($(t).closest(".input-group").length) {
                        $(t).closest(".input-group").after(error);
                    } else {
                        $(t).after(error);
                    }
                } else {
                    // It might be hidden - show
                    $fieldError.show();
                }
            } else {
                var newDate = dayjs.unix(timestamp);

                // If field has all day switch data param and is end or start of day - make all day
                var state = (newDate.format("HHmm") == "0000" || newDate.format("HHmm") == "2359") && opts.allDaySwitch;

                // Does the linked element (if any) have time?
                if (opts.allDayInput) {
                    var linkedElements = getLinkedElements($(t), opts);

                    linkedElements.forEach(function (item) {
                        var itemOptions = adtp.getOptions($(item));
                        var itemDate = dayjs(item.val(), [
                            adtp.convertFormat(options.displayFormat),
                            adtp.convertFormat(options.displayFormatDate),
                        ]);
                        var itemState =
                            (itemDate.format("HHmm") == "0000" || itemDate.format("HHmm") == "2359") && state;

                        // Update the state of the linked field
                        setValue($(item), itemOptions, itemState, true);

                        // Update the original field's state to make consistent
                        // Note: all fields in the group must be 0000 or 2359 in order for all day to be switched on
                        state = itemState;
                    });
                }

                // Add the parsed date into the field
                // assumption: if time is 00:00:00 and the endDay flag is set, push to 23:59:59 instead
                // this causes a limitation that it is *not possible* to set a field of this nature to midnight
                setValue($(t), opts, state, true, newDate);

                // Sync dates if applicable
                adtp.syncDatesOnElement($(t), opts, "");

                // Remove any error class on the field
                $(t).removeClass("error");

                // Hide any validation error
                $fieldError.hide();
            }

            $(t).prop("readonly", false);

            // Fire a custom event for change of this value
            $(t).trigger("datepickerChange");
        }
    }

    /**
     * JS port of /ajax/strtotime.php.
     *
     * @param text
     * @returns {*}
     */
    function parseTime(text) {
        return $.fn.strtotime(denaturalize(text));
    }

    /**
     * Smooth out formats so strtotime digests times more easily
     *
     * @param text
     * @returns {string}
     */
    function denaturalize(text) {
        // normalise any whitespace
        // remove "^in " and " from now$"
        // replace all word-numbers with number-numbers
        // known limitation: 23:00am fails to parse
        // replace / with - so that strtotime recognises D/M/Y instead of heretical M/D/Y format
        return text
            .trim()
            .toLowerCase()
            .replace(/\s+/g, " ")
            .replace(/^in\s+/g, "")
            .replace(/\s+from\s+now$/g, "")
            .replace(/\s+at\s+/g, "")
            .replace(/\s+on\s+/g, "")
            .replace(/,\s/g, "")
            .replace(/zero/g, "0")
            .replace(/one/g, "1")
            .replace(/two/g, "2")
            .replace(/three/g, "3")
            .replace(/four/g, "4")
            .replace(/five/g, "5")
            .replace(/six/g, "6")
            .replace(/seven/g, "7")
            .replace(/eight/g, "8")
            .replace(/nine/g, "9")
            .replace(/ten/g, "10")
            .replace(/eleven/g, "11")
            .replace(/twelve/g, "12")
            .replace(/\//g, "-")
            .replace(/\s+/g, " ");
    }
};
