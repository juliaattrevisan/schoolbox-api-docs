(function(adtp, $, undefined) {
  // -----------------------------------------------------------------------
  // Public vars & methods
  // -----------------------------------------------------------------------
  adtp.momentFormats = {
    database: "YYYY-MM-DD HH:mm:ss",
    databaseShort: "YYYY-MM-DD",
    nice: "dddd, MMM D, Y"
  };

  // Use global counter in case multiple alaressDateTimePicker() have been called from one page
  var pickerCounter = 0;
  adtp.countPickers = function() {
    pickerCounter++;
    return pickerCounter;
  };

  /**
   * To keep the date format string the same between the flatpickr format string, and MomentJS need to create a mapping function
   *
   * @param format
   */
  adtp.convertFormat = function(format) {
    var formatMap = {
        d: "DD",
        D: "ddd",
        j: "D",
        J: "Do",
        l: "dddd",
        N: "E",
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
        s: "ss",
        O: "ZZ",
        P: "Z"
      },
      formatEx = /[dDjJlNKSwWFmMnoYyaAgGhHisOP]/g;

    // Just return the converted format
    return format.replace(formatEx, function(format) {
      return formatMap[format];
    });
  };

  /**
   *
   * @param input
   * @param attribute
   * @returns {boolean}
   */
  adtp.getValue = function(input, attribute) {
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
  adtp.getOptions = function(element) {
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
      staller: null
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
  adtp.syncDatesOnElement = function($element, options, from) {
    var dateFormat = fieldHasTime(options)
      ? dateDisplayFormats.momentJsDatetime
      : dateDisplayFormats.momentJs;

    var syncOptions = {
      dateFormat: dateFormat
    };

    if (
      $element.attr("data-datesync-startdate") !== undefined &&
      from !== "end"
    ) {
      syncOptions.startDate = $element.attr("data-datesync-startdate");
      syncOptions.endDate = $element;

      $element.syncDatesNow(syncOptions);

      // Run this again for the start element
      adtp.syncDatesOnElement(
        $($element.attr("data-datesync-startdate")),
        options,
        "start"
      );
    }

    if (
      $element.attr("data-datesync-endDate") !== undefined &&
      from !== "start"
    ) {
      syncOptions.startDate = $element;
      syncOptions.endDate = $element.attr("data-datesync-endDate");

      $element.syncDatesNow(syncOptions);

      // Run this again for the end element
      adtp.syncDatesOnElement(
        $($element.attr("data-datesync-endDate")),
        options,
        "end"
      );
    }
  };

  /**
   *
   * @param state
   * @param pickerId
   */
  adtp.setCalendarAllDay = function(state, pickerId) {
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
$.fn.alaressDateTimePicker = function(optionsOverride) {
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

    element
      .data("dateButton", $datebtn)
      .after($datebtn.append($('<span class="splash animate"/>')));
  }

  var $datepickerButton = $(this).siblings("[data-adtp-btn]");

  window.pickerInstance = [];

  // Count this picker in
  var pickerId = adtp.countPickers();

  // Set pickerID to the element attr
  element.attr("data-datepicker-id", pickerId);

  var modalSize =
    options.type == "event" || options.type == "duework"
      ? "pickerDouble"
      : "pickerSingle";
  $("body").append(
    '<div id="adtp-modal-wrapper-' +
      pickerId +
      '"><div data-reveal id="adtp-modal-' +
      pickerId +
      '" class="adtp reveal-modal no-pad ' +
      modalSize +
      '" data-adtp></div></div>'
  );

  // On focus of the element- store the original value in a data var, so that we can compare before 'massaging' (strtotime)
  element.on("focus", function() {
    element.data("originalValue", this.value);
  });

  // Listen for blur or enter press- apply strtotime
  element
    .on("blur", function(e) {
      massageDateValue(e, options.staller);
    })
    .on("keydown", function(e) {
      if (e.keyCode === 13) {
        massageDateValue(e, options.staller);
        return true;
      }
    });

  // Create click event to open the modal for the element
  $datepickerButton.click(function() {
    var originalValue;

    if (element.val().length > 0) {
      originalValue = moment(
        element.val(),
        adtp.convertFormat(options.displayFormat)
      );
    }
    // if no value has been passed, use today
    else {
      originalValue = moment();
    }
    if (
      options.allDayInput !== false &&
      $("#" + options.allDayInput).val() == 1
    ) {
      originalValue = moment(
        originalValue,
        adtp.convertFormat(options.displayFormatDate)
      );
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
          encodeURIComponent(originalValue.format(adtp.momentFormats.database)),
        function() {
          // Capture closure of modal
          $(document).on(
            "closed.fndtn.reveal",
            "#adtp-modal-" + pickerId,
            function() {
              // Open back the parent modal, if any
              if ($modal.data("reveal").length > 0) {
                $("#" + $modal.data("reveal")).foundation("reveal", "open");
              }
            }
          );
          // Update picker status
          $modal.data("status", "loaded");

          // Display the datetime picker modal
          $modal.foundation("reveal", "open");
          // Initialise tabs
          $modal.foundation("reflow", "tab");

          var $datePicker = $("#byDate-" + pickerId),
            $timetablePicker = $("#byTimetable-" + pickerId);

          $datePicker.find(".picker-submit").click(function() {
            if ($("#hideTime" + pickerId).is(":checked")) {
              setValue(element, options, true, true);
            } else {
              setValue(element, options, false, true);
            }

            if (options.allDayInput !== false) {
              var linkedElements = getLinkedElements(element, options);
              linkedElements.forEach(function(item) {
                setValue(
                  item,
                  adtp.getOptions(item),
                  $("#hideTime" + pickerId).is(":checked"),
                  true
                );
              });
            }

            // Trigger a custom event. This is listened on draft.js to check for changes.
            element.trigger("datepickerChange");

            // Sync dates if applicable
            adtp.syncDatesOnElement(element, options, "");

            // Close modal
            $modal.foundation("reveal", "close");
          });

          $timetablePicker.find("[data-adtp-tt-submit]").click(function() {
            var newDate = moment($(this).attr("data-timetable-value"));
            var selectedCampus = $timetablePicker
              .find("[data-adtp-tt-campus]")
              .val();
            var timetableAllDay = false;

            if (options.enableTime === true) {
              var selectedPeriod = $timetablePicker
                .find(
                  "[data-adtp-tt-period][data-campus=" + selectedCampus + "]"
                )
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
                var newTime = moment(
                  $timetablePicker
                    .find(
                      "[data-adtp-tt-period][data-campus=" +
                        selectedCampus +
                        "] option:selected"
                    )
                    .attr("data-" + periodTime + "-time"),
                  "h:mma"
                );
                newDate = newDate
                  .hours(newTime.hours())
                  .minutes(newTime.minutes())
                  .seconds(0);
              }
            }

            setValue(element, options, timetableAllDay, true, newDate);
            // Set allday state on the calendar picker
            adtp.setCalendarAllDay(timetableAllDay, pickerId);

            if (options.allDayInput !== false) {
              var linkedElements = getLinkedElements(element, options);
              linkedElements.forEach(function(item) {
                setValue(item, adtp.getOptions(item), timetableAllDay, true);
                adtp.setCalendarAllDay(
                  timetableAllDay,
                  adtp.getOptions(item).id
                );
              });
            }

            // Sync dates if applicable
            adtp.syncDatesOnElement(element, options, "");

            // Close the modal
            $modal.foundation("reveal", "close");
          });
        }
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
      convertTo: to
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
    var elements = $(
      "[data-datepicker-alldayinput = " + options.allDayInput + "]"
    );
    var linkedElements = [];
    elements.each(function() {
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
      value = moment(date);
    } else if (
      window.pickerInstance[pId] !== undefined &&
      window.pickerInstance[pId].input.value
    ) {
      value = moment(window.pickerInstance[pId].input.value);
    } else if (element.val() !== undefined && element.val()) {
      value = moment(
        element.val(),
        adtp.convertFormat(pickerFormats.convertFrom)
      );
    } else {
      value = moment();
    }

    // All Day is on
    var v;
    if (state === true) {
      if (options.endDay) {
        v = value
          .endOf("day")
          .format(adtp.convertFormat(pickerFormats.convertTo));
      } else {
        v = value
          .startOf("day")
          .format(adtp.convertFormat(pickerFormats.convertTo));
      }

      if (window.pickerInstance[pId] !== undefined) {
        window.pickerInstance[pId].setDate(v.toISOString);
      }
      element.val(v);
    } else {
      v = moment(value).format(adtp.convertFormat(pickerFormats.convertTo));
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
   * This uses an AJAX call to /ajax/strtotime.php, which uses method strtotime()
   * to convert the input's value to a recognisable date value.
   *
   * @param {jQuery.Event} e       a jQuery event
   * @param {jQuery}       staller if given, the element to attach the AJAX promise to
   */
  function massageDateValue(e, staller) {
    var t = e.target;

    if (
      $.trim($(t).val()) != "" &&
      $.trim($(t).val()) !== $(t).data("originalValue")
    ) {
      // set up params to guide server-side parsing
      var opts = adtp.getOptions($(t));
      // var params = {
      //   string: t.value
      // };
      // var xhr = $.get("/ajax/strtotime.php", params);
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
            $(t)
              .closest(".input-group")
              .after(error);
          } else {
            $(t).after(error);
          }
        } else {
          // It might be hidden - show
          $fieldError.show();
        }
      } else {
        var newDate = moment.unix(timestamp);

        // If field has all day switch data param and is end or start of day - make all day
        var state =
          (newDate.format("HHmm") == "0000" ||
            newDate.format("HHmm") == "2359") &&
          opts.allDaySwitch;

        // Does the linked element (if any) have time?
        if (opts.allDayInput) {
          var linkedElements = getLinkedElements($(t), opts);

          linkedElements.forEach(function(item) {
            var itemOptions = adtp.getOptions($(item));
            var itemDate = moment(
              item.val(),
              adtp.convertFormat(itemOptions.displayFormat)
            );
            var itemState =
              (itemDate.format("HHmm") == "0000" ||
                itemDate.format("HHmm") == "2359") &&
              state;

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

      // Fire a custom event for change of this
      // This has to happen here (rather than on xhr done above) so that the staller can pick up on this
      $(t).trigger("datepickerChange");

      // // this listener needs to be installed last in this case
      // if (staller) {
      //   // install this XHR as stalling form submit
      //   staller.stallSubmit(xhr);
      // }
    }
  }


    /**
     * JS port of /ajax/strtotime.php.
     *
     * @param text
     * @returns {*}
     */
    function parseTime(text) {
        return strtotime(denaturalize(text));
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
            .toLowerCase()
            .replace(/^in\s+/g, '')
            .replace(/from\s+now$/g, '')
            .replace(/at/g, '')
            .replace(/on/g, '')
            .replace(/,/g, '')
            .replace(/zero/g, '0')
            .replace(/one/g, '1')
            .replace(/two/g, '2')
            .replace(/three/g, '3')
            .replace(/four/g, '4')
            .replace(/five/g, '5')
            .replace(/six/g, '6')
            .replace(/seven/g, '7')
            .replace(/eight/g, '8')
            .replace(/nine/g, '9')
            .replace(/ten/g, '10')
            .replace(/eleven/g, '11')
            .replace(/twelve/g, '12')
            .replace(/\//g, '-')
            .replace(/\s+/g, ' ')
        ;
    }


    /**
     * JS port of PHP strtotime.
     *
     * https://github.com/kvz/locutus/blob/master/src/php/datetime/strtotime.js
     *
     * @param text
     * @param now
     * @returns {*}
     */
    function strtotime (text, now) {
        //  discuss at: http://locutus.io/php/strtotime/
        // original by: Caio Ariede (http://caioariede.com)
        // improved by: Kevin van Zonneveld (http://kvz.io)
        // improved by: Caio Ariede (http://caioariede.com)
        // improved by: A. Matías Quezada (http://amatiasq.com)
        // improved by: preuter
        // improved by: Brett Zamir (http://brett-zamir.me)
        // improved by: Mirko Faber
        //    input by: David
        // bugfixed by: Wagner B. Soares
        // bugfixed by: Artur Tchernychev
        // bugfixed by: Stephan Bösch-Plepelits (http://github.com/plepe)
        //      note 1: Examples all have a fixed timestamp to prevent
        //      note 1: tests to fail because of variable time(zones)
        //   example 1: strtotime('+1 day', 1129633200)
        //   returns 1: 1129719600
        //   example 2: strtotime('+1 week 2 days 4 hours 2 seconds', 1129633200)
        //   returns 2: 1130425202
        //   example 3: strtotime('last month', 1129633200)
        //   returns 3: 1127041200
        //   example 4: strtotime('2009-05-04 08:30:00 GMT')
        //   returns 4: 1241425800
        //   example 5: strtotime('2009-05-04 08:30:00+00')
        //   returns 5: 1241425800
        //   example 6: strtotime('2009-05-04 08:30:00+02:00')
        //   returns 6: 1241418600
        //   example 7: strtotime('2009-05-04T08:30:00Z')
        //   returns 7: 1241425800

        var parsed;
        var match;
        var today;
        var year;
        var date;
        var days;
        var ranges;
        var len;
        var times;
        var regex;
        var i;
        var fail = false;

        if (!text) {
            return fail
        }

        // Unecessary spaces
        text = text.replace(/^\s+|\s+$/g, '')
            .replace(/\s{2,}/g, ' ')
            .replace(/[\t\r\n]/g, '')
            .toLowerCase();

        // in contrast to php, js Date.parse function interprets:
        // dates given as yyyy-mm-dd as in timezone: UTC,
        // dates with "." or "-" as MDY instead of DMY
        // dates with two-digit years differently
        // etc...etc...
        // ...therefore we manually parse lots of common date formats
        var pattern = new RegExp([
            '^(\\d{1,4})',
            '([\\-\\.\\/:])',
            '(\\d{1,2})',
            '([\\-\\.\\/:])',
            '(\\d{1,4})',
            '(?:\\s(\\d{1,2}):(\\d{2})?:?(\\d{2})?)?',
            '(?:\\s([A-Z]+)?)?$'
        ].join(''));
        match = text.match(pattern);

        if (match && match[2] === match[4]) {
            if (match[1] > 1901) {
                switch (match[2]) {
                    case '-':
                        // YYYY-M-D
                        if (match[3] > 12 || match[5] > 31) {
                            return fail
                        }

                        return new Date(match[1], parseInt(match[3], 10) - 1, match[5],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000;
                    case '.':
                        // YYYY.M.D is not parsed by strtotime()
                        return fail;
                    case '/':
                        // YYYY/M/D
                        if (match[3] > 12 || match[5] > 31) {
                            return fail
                        }

                        return new Date(match[1], parseInt(match[3], 10) - 1, match[5],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000
                }
            } else if (match[5] > 1901) {
                switch (match[2]) {
                    case '-':
                        // D-M-YYYY
                        if (match[3] > 12 || match[1] > 31) {
                            return fail
                        }

                        return new Date(match[5], parseInt(match[3], 10) - 1, match[1],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000;
                    case '.':
                        // D.M.YYYY
                        if (match[3] > 12 || match[1] > 31) {
                            return fail
                        }

                        return new Date(match[5], parseInt(match[3], 10) - 1, match[1],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000;
                    case '/':
                        // M/D/YYYY
                        if (match[1] > 12 || match[3] > 31) {
                            return fail
                        }

                        return new Date(match[5], parseInt(match[1], 10) - 1, match[3],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000
                }
            } else {
                switch (match[2]) {
                    case '-':
                        // YY-M-D
                        if (match[3] > 12 || match[5] > 31 || (match[1] < 70 && match[1] > 38)) {
                            return fail
                        }

                        year = match[1] >= 0 && match[1] <= 38 ? +match[1] + 2000 : match[1];
                        return new Date(year, parseInt(match[3], 10) - 1, match[5],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000;
                    case '.':
                        // D.M.YY or H.MM.SS
                        if (match[5] >= 70) {
                            // D.M.YY
                            if (match[3] > 12 || match[1] > 31) {
                                return fail
                            }

                            return new Date(match[5], parseInt(match[3], 10) - 1, match[1],
                                match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000
                        }
                        if (match[5] < 60 && !match[6]) {
                            // H.MM.SS
                            if (match[1] > 23 || match[3] > 59) {
                                return fail
                            }

                            today = new Date();
                            return new Date(today.getFullYear(), today.getMonth(), today.getDate(),
                                match[1] || 0, match[3] || 0, match[5] || 0, match[9] || 0) / 1000
                        }

                        // invalid format, cannot be parsed
                        return fail;
                    case '/':
                        // M/D/YY
                        if (match[1] > 12 || match[3] > 31 || (match[5] < 70 && match[5] > 38)) {
                            return fail
                        }

                        year = match[5] >= 0 && match[5] <= 38 ? +match[5] + 2000 : match[5];
                        return new Date(year, parseInt(match[1], 10) - 1, match[3],
                            match[6] || 0, match[7] || 0, match[8] || 0, match[9] || 0) / 1000;
                    case ':':
                        // HH:MM:SS
                        if (match[1] > 23 || match[3] > 59 || match[5] > 59) {
                            return fail
                        }

                        today = new Date();
                        return new Date(today.getFullYear(), today.getMonth(), today.getDate(),
                            match[1] || 0, match[3] || 0, match[5] || 0) / 1000
                }
            }
        }

        // other formats and "now" should be parsed by Date.parse()
        if (text === 'now') {
            return now === null || isNaN(now)
                ? new Date().getTime() / 1000 | 0
                : now | 0
        }
        if (!isNaN(parsed = Date.parse(text))) {
            return parsed / 1000 | 0
        }
        // Browsers !== Chrome have problems parsing ISO 8601 date strings, as they do
        // not accept lower case characters, space, or shortened time zones.
        // Therefore, fix these problems and try again.
        // Examples:
        //   2015-04-15 20:33:59+02
        //   2015-04-15 20:33:59z
        //   2015-04-15t20:33:59+02:00
        pattern = new RegExp([
            '^([0-9]{4}-[0-9]{2}-[0-9]{2})',
            '[ t]',
            '([0-9]{2}:[0-9]{2}:[0-9]{2}(\\.[0-9]+)?)',
            '([\\+-][0-9]{2}(:[0-9]{2})?|z)'
        ].join(''));
        match = text.match(pattern);
        if (match) {
            // @todo: time zone information
            if (match[4] === 'z') {
                match[4] = 'Z'
            } else if (match[4].match(/^([+-][0-9]{2})$/)) {
                match[4] = match[4] + ':00'
            }

            if (!isNaN(parsed = Date.parse(match[1] + 'T' + match[2] + match[4]))) {
                return parsed / 1000 | 0
            }
        }

        date = now ? new Date(now * 1000) : new Date();
        days = {
            'sun': 0,
            'mon': 1,
            'tue': 2,
            'wed': 3,
            'thu': 4,
            'fri': 5,
            'sat': 6
        };
        ranges = {
            'yea': 'FullYear',
            'mon': 'Month',
            'day': 'Date',
            'hou': 'Hours',
            'min': 'Minutes',
            'sec': 'Seconds'
        };

        function lastNext (type, range, modifier) {
            var diff;
            var day = days[range];

            if (typeof day !== 'undefined') {
                diff = day - date.getDay();

                if (diff === 0) {
                    diff = 7 * modifier
                } else if (diff > 0 && type === 'last') {
                    diff -= 7
                } else if (diff < 0 && type === 'next') {
                    diff += 7
                }

                date.setDate(date.getDate() + diff)
            }
        }

        function process (val) {
            // @todo: Reconcile this with regex using \s, taking into account
            // browser issues with split and regexes
            var splt = val.split(' ');
            var type = splt[0];
            var range = splt[1].substring(0, 3);
            var typeIsNumber = /\d+/.test(type);
            var ago = splt[2] === 'ago';
            var num = (type === 'last' ? -1 : 1) * (ago ? -1 : 1);

            if (typeIsNumber) {
                num *= parseInt(type, 10)
            }

            if (ranges.hasOwnProperty(range) && !splt[1].match(/^mon(day|\.)?$/i)) {
                return date['set' + ranges[range]](date['get' + ranges[range]]() + num)
            }

            if (range === 'wee') {
                return date.setDate(date.getDate() + (num * 7))
            }

            if (type === 'next' || type === 'last') {
                lastNext(type, range, num)
            } else if (!typeIsNumber) {
                return false
            }

            return true
        }

        times = '(years?|months?|weeks?|days?|hours?|minutes?|min|seconds?|sec' +
            '|sunday|sun\\.?|monday|mon\\.?|tuesday|tue\\.?|wednesday|wed\\.?' +
            '|thursday|thu\\.?|friday|fri\\.?|saturday|sat\\.?)';
        regex = '([+-]?\\d+\\s' + times + '|' + '(last|next)\\s' + times + ')(\\sago)?';

        match = text.match(new RegExp(regex, 'gi'));
        if (!match) {
            return fail
        }

        for (i = 0, len = match.length; i < len; i++) {
            if (!process(match[i])) {
                return fail
            }
        }

        return (date.getTime() / 1000)
    }
};
