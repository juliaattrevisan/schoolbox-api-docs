/* Alaress FullCalendar implementation

* @dep customised fullcalendar.min.js
* */

// handy string function
// http://stackoverflow.com/questions/1026069/capitalize-the-first-letter-of-string-in-javascript
String.prototype.capitalise = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}

/**
 * Calendar object constructor
 * @param id
 * @constructor
 */
function Calendar(id, bubbleId, opts) {
    var self = this
    this.id = id
    this.dom = $('#'+id)
    this.bubble = new Bubble(this, bubbleId)
    this.suppress = false

    // default options
    this.options = {
        taskTypeId: 0,  // disabled by default
        crud: {
            c: '.',
            r: '.',
            u: '.',
            d: '.'
        },
        filter: {
            ajax: '.',
            enabled: true,
            //
            // Selectors for filter control
            parentSelector: '#calendar-controls',
            btnSelector: '#fc-button-filter',
            //
            // Selectors for export control
            exportSelector: '#calendar-export',
            exportButtonSelector: '#fc-button-export',
            //
            // Selectors within filter and export items
            checkboxSelector: '.applyFilters',
            applyFiltersSelector: '#applyFilters',
            boxSelector: '.filter-drop',
            listSelector: '.filter-list',
            listViewButton: true
        },
        fullCalendar: {
            contentHeight: $(window).height() - this.dom.offset().top - 60,
            header: {
                left: 'prev,today,next',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            buttonText: {
                prev:     '', // ◀
                next:     '', // ▶
                prevYear: '&nbsp;&laquo;&nbsp;',  // <<
                nextYear: '&nbsp;&raquo;&nbsp;',  // >>
                today:    'today',
                month:    'month',
                week:     'week',
                day:      'day'
            },
            date: null,
            month: null,
            year: null,
            eventLimit: true,

            /**
             * Generic action for when an event is clicked
             * @param calEvent
             * @param jsEvent
             * @param view
             */
            eventClick: function(calEvent, jsEvent, view) {

                // Did this event come from a checkbox?
                // If so do nothing, those events are handled elsewhere.
                if ($(jsEvent.target).is(':checkbox')) {
                    return;
                }

                // setup data to be displayed
                var data = $.extend(true, {}, calEvent.data.meta)

                // Prefix booking status for bookings
                if (calEvent.data.booking) {
                    data.bookingStatus = calEvent.data.booking.status.name;
                    $('[data-event-modal-field="bookingStatus"]').show();

                    data.note = calEvent.title
                }
                else {
                    data.note = calEvent.title;
                    $('[data-event-modal-field="bookingStatus"]').hide();
                }
                // Normalize note and title
                data.title = data.note;

                // Format the date of this event
                var format = calEvent.allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma'
                var start = calEvent.start.format(format);
                var end   = null;
                if (calEvent.end != null) {
                    if (calEvent.allDay) {
                        end = moment(calEvent.end).subtract(1, 'd').format(format);
                    } else {
                        end = calEvent.end.format(format);
                    }
                }

                if (end && start != end) {
                    // Start and end aren't the same - stitch them together.
                    if (!calEvent.allDay
                        && calEvent.start.format('YYYYMMDD') === calEvent.end.format('YYYYMMDD')
                    ) {
                        // Between two times on the same day - show the timespan but only one date
                        data.date = start + " – " + calEvent.end.format('h:mma');
                    } else {
                        // Between two different days - show the whole timespan
                        data.date = start + " – " + end;
                    }
                } else {
                    // Start and end are the same - just show the start
                    data.date = start;
                }

                // Links to Modify Screen
                if (calEvent.editable == true) {
                    var url = '';
                    if ((calEvent.data.custom != undefined) && (calEvent.data.custom.modifyLink != undefined) && (calEvent.data.custom.modifyLink.length)) {
                        url = calEvent.data.custom.modifyLink;
                        data.deleteLink = '';
                    }
                    else {
                        url = '/calendar/modify.php?eventID=' + calEvent.data.meta.eventId + '&returnTo=calendar';
                        deleteUrl = '/calendar/modifyForm.php?action=Delete&id=' + calEvent.data.meta.eventId + '&returnTo=calendar';
                        data.deleteLink = '<a class="button icon-delete" href="' + deleteUrl + '"><span></span>Delete</a>';
                    }

                    data.modifyLink = '<a class="button icon-modify" href="' + url + '"><span></span>Modify</a>';
                }
                else {
                    data.modifyLink = '';
                    data.deleteLink = '';
                }

                // Group events: have a go to homepage link
                data.homepageLink = '';
                try {
                    if (calEvent.data.links.category.id > 0) {
                        data.homepageLink =
                            $('<a class="button icon-home">Go To Homepage</a>')
                                .attr('href', '/homepage/' + String(calEvent.data.links.category.id));
                    }
                }
                catch (e) {}

                $.each(calEvent.data.attendance, function(k, v) {
                    data['attendance' + k.capitalise()] = v
                })

                // Attendance List butonye
                if (data.attendanceListButton.toString() == 'true') {
                    data.attendanceIcon = ''
                    data.attendanceListButton = '<a class="button icon-group" href="/calendar/attendance/list/'
                        + calEvent.data.meta.eventId + '">Attendees</a>'
                }
                // Attend/Unattend butonye
                if (data.attendanceRequestButton.toString() != 'false') {
                    data.attendanceRequestButton = '<a class="button icon-reply" href="#" data-attendance-request>' + data.attendanceRequestButton.toString() + '</a>'
                }

                data.bookingActions = [];
                data.relatedBookings = [];
                if (calEvent.data.booking) {
                    // Booking butonyes (if available)
                    if (calEvent.data.booking.buttons) {
                        $.each(calEvent.data.booking.buttons, function() {
                            if (this.href) {
                                data.bookingActions.push(
                                    $('<a class="button" />')
                                        .data('seriesId', calEvent.data.meta.seriesId)
                                        .attr('data-booking-button', this.class)
                                        .attr('href', this.href)
                                        .text(this.label)
                                        .prepend('<span />')
                                )
                            }
                        })
                    }

                    // Booking reviewer (if available)
                    if (calEvent.data.booking.reviewer) {
                        data.reviewer   = calEvent.data.booking.reviewer.name;
                        data.reviewerId = calEvent.data.booking.reviewer.id;
                    }

                    // Related bookings
                    data.relatedBookings = calEvent.data.booking.related;
                }

                self.bubble.display('edit', data, jsEvent, this);

                // Hide event types in the bubble
                var isTask = data.eventTypeId == c.options.taskTypeId;
                var bubble = $('#calendar-bubble');
                bubble.foundation('reveal', 'open');
                bubble.find('#editTask').toggleClass('hide', !isTask);
                bubble.find('#editEvent').toggleClass('hide', isTask);
            }
        },
        datepicker: {
            dateFormat: 'dd-mm-yy',
            onSelect: function(dateText, inst) {
                // Use fullcalendar to load events instead of redirecting to a new page.
                self.dom.fullCalendar(
                    'gotoDate',
                    moment().year(inst.currentYear).month(inst.currentMonth).date(inst.currentDay)
                )
            },
            beforeShow: function() {
                // Make sure the stupid datepicker shows above everything else
                setTimeout(function() {
                    $('.ui-datepicker').css('z-index', 99999999999999);
                }, 0);
            },
            showOn: 'button',
            buttonImage: "/images/core/calendar/calendar-click.png",
            buttonImageOnly: true,
            showAnim: 'slideDown'
        },
        x: true
    }
    this.filters = {

        interaction: function(cal) {

            // Only set up interactions that actually exist
            var calendarControls = $(cal.options.filter.parentSelector)
            var exportControls   = $(cal.options.filter.exportSelector)

            // show the filter 'dialog' when the button is clicked
            cal.dom.on('click', cal.options.filter.btnSelector, function() {
                return false
            })

            // Show the export filter, if it exists
            if (cal.options.filter.exportButtonSelector) {
                cal.dom.on('click', cal.options.filter.exportButtonSelector, function() {
                    return false
                })
            }

            // run the filter checker to show and hide the items, if it exists
            if (cal.options.filter.checkboxSelector) {
                calendarControls.on('change', cal.options.filter.checkboxSelector, function() {
                    cal.filters.organise(cal, ["campus", "groups", "sources"])
                })
            }

            if (cal.options.filter.applyFiltersSelector) {
                calendarControls.on('click', cal.options.filter.applyFiltersSelector, function() {
                    cal.filters.organise(cal, ["campus", "groups", "sources"])
                    calendarControls.foundation('reveal', 'close');
                    return false
                })
            }

            if (cal.options.filter.boxSelector) {
                calendarControls.add(exportControls).on('click', cal.options.filter.boxSelector, function(e) {
                    e.stopPropagation()
                })
            }

            cal.filters.organise(cal, ["campus", "groups", "sources"])
        },

        organise: function(cal, filterArray, performAjax) {
            performAjax = performAjax === undefined ? true : performAjax

            var flist = {}, selected_classes = ['personal'];
            var calendarControls = $(cal.options.filter.parentSelector)

            for (var i = 0; i < filterArray.length; i++) {
                // Each filter array can contain a comma-separated list of IDs
                var k = 0
                flist[i] = [filterArray[i], {}]

                calendarControls.find(cal.options.filter.listSelector + ' .' + filterArray[i] + ' input').each(function() {
                    var n = $(this).attr('value')
                    // show related entries
                    if(this.checked) {

                        var base = n.match(/^\D+/);
                        if (base) {
                            flist[i][1][k++] = n
                            var ids = n.match(/\d+/g);
                            if (ids) {
                                ids.forEach(function(id) {
                                    selected_classes.push(base + id);
                                });
                            }
                        }
                    }
                })
            }

            var k = 0
            flist[filterArray.length] = ['events', {}]

            calendarControls.find(cal.options.filter.listSelector + ' .events input').each(function() {
                var n = $(this).attr('value')
                // show related entries
                if(this.checked) {
                    flist[i][1][k++] = n
                    selected_classes.push(n);
                }
            })

            //
            // Get events, put an 'is_rendered' field on them.
            var events = cal.dom.fullCalendar('clientEvents');
            $.each(events, function(event_no, event) {
                var is_rendered = true;

                // Collate the list of classes into a list grouped by type
                // (type is the string prefix of the class: for "group14"
                //  it will be "group".)
                var groupedClasses = {};
                if (event.className.forEach) {
                    event.className.forEach(function(className) {
                        var prefix = className.replace(/^([a-z]+).*/i, '$1');
                        if (!groupedClasses[prefix]) {
                            groupedClasses[prefix] = []
                        }
                        groupedClasses[prefix].push(className);
                    });
                }

                // Is this event rendered?
                // It must match at least one selected filter in each group
                // (filters are in event.className) to be rendered.
                $.each(groupedClasses, function(prefix, classes) {
                    var is_filter_selected = false;
                    if (!classes.length) {
                        // No classes in this filter? Let's say this filter is
                        // selected.
                        is_filter_selected = true;
                    }
                    else {
                        // Check that at least one class in this_classes is in
                        // selected_classes.
                        $.each(selected_classes, function(selected_class_no, selected_class) {
                            for (var i in classes) {
                                if (classes[i] == selected_class) {
                                    is_filter_selected = true;
                                    return false;
                                }
                            }
                        });
                    }
                    if (!is_filter_selected) {
                        is_rendered = false;
                        return false;
                    }
                });
                // disabled until filters are fully fixed
                 event.is_rendered = is_rendered;
            });

            //
            // Jam them back in, and re-render.
            // The call to the base full calendar's reportEvents() method with
            // the filtered event list is to get the event renderer to draw
            // events at the right size when filtering.
            var cal_object = cal.dom.data('fullCalendar');
            if (cal_object && 'reportEvents' in cal_object) {
                cal_object.reportEvents(
                    $.grep(events, function(event) { return event.is_rendered; })
                );
            }
            else {
                cal.dom.fullCalendar('rerenderEvents');
            }

            // only send data to the server if we actually need to
            if(performAjax && cal.options.filter.ajax) {
                var view = cal.dom.fullCalendar('getView').name
                cal.post(cal.options.filter.ajax, {fList : flist, view: view}, null, false)
            }
        }
    }

    // preserve the filter and fullCalendar specs
    var filter = $.extend({}, this.options.filter, opts.filter)
    var fullCalendar = $.extend({}, this.options.fullCalendar, opts.fullCalendar)
    var datepicker   = $.extend({}, this.options.datepicker,   opts.datepicker)

    // perform the merge
    $.extend(this.options,opts)

    // rewrite the preserved submerge back into the options
    this.options.filter = filter
    this.options.fullCalendar = fullCalendar
    this.options.datepicker   = datepicker

    if(this.options.filter.enabled) {
        this.filters.interaction(this)
    }

    return this
}

/**
 * Initialise the calendar
 */
Calendar.prototype.init = function() {
    var c = this
    this.dom.fullCalendar(this.options.fullCalendar)
    /* Button for list view
     */
    var headerRight = this.dom.find('.fc-toolbar .fc-right .fc-button-group');
    var baseButton  = headerRight.find('.fc-button').first();
    if (this.options.filter.listViewButton) {
        headerRight.prepend(
            baseButton.clone()
                .html('list')
                .removeClass('fc-month-button fc-agendaWeek-button fc-agendaDay-button fc-state-active no-icon')
                .addClass(' fc-corner-left')
                .attr('id','fc-button-list')
        );
    }

    if(this.options.filter.enabled) {
        // mash the filters in (if they're enabled)
        if (this.options.filter.btnSelector) {
            headerRight.prepend(
                baseButton.clone()
                    .html('filters')
                    .removeClass('fc-month-button fc-agendaWeek-button fc-agendaDay-button fc-state-active')
                    .addClass('fc-button-filter fc-corner-left fc-corner-right')
                    .attr('id','fc-button-filter')
                    .attr('data-reveal-id',this.options.filter.parentSelector.substr(1))
            );
        }
        if (this.options.filter.exportButtonSelector) {
            headerRight.append(
                baseButton.clone()
                    .html('export')
                    .removeClass('fc-month-button fc-agendaWeek-button fc-agendaDay-button fc-state-active')
                    .addClass('fc-button-filter fc-corner-left fc-corner-right')
                    .attr('id','fc-button-export')
                    .attr('data-reveal-id',this.options.filter.exportSelector.substr(1))
            );
        }
    }
    // and now make all the buttons on the header look the way they're meant to
    var header = this.dom.find('.fc-toolbar')
    header.addClass('calendar-controls')
    header.find('.fc-button').each(function() {
        var self = $(this)
        self.addClass('filter-change');
    })
    header.find('.fc-right .fc-button').each(function() {
        var self = $(this)
        self.addClass('filter-change '+self.text()).html('<span></span>'+self.html())
    })


    /* Special case for list view, it's the only button with an anchor
     */
    $('#fc-button-list').removeAttr('unselectable').removeClass('list filters').click(function(event) {
        event.preventDefault();
        window.location.href = '/calendar/list/' + c.options.userId + '/' + c.dom.fullCalendar('getDate').format('DD-MM-YYYY');
    });

    // mash in the datepicker
    var headerCenter = header.find('.fc-center')
    headerCenter.prepend('<div id="jqdp" class="datepicker-box"><input type="hidden" id="datepicker"/></div>')

    // Event creation form listeners
    $.each(this.getForms(), function(k, v) {
        if (typeof this.data('no-calendar-listener') === 'undefined') {
            if(k.slice(0, 6) == 'create') {
                this.submit(function(event) {
                    event.preventDefault();
                    var form = $(this);
                    var data = form.serialize()

                    // Create and render a placeholder event
                    var placeholderEvent = {
                        allDay: Number(form.find('input[name="allDay"]').val()) ? true : false,
                        title: form.find('input[name="note"]').val(),
                        className: ['loading'],
                        id: 'placeholder'
                    };
                    if (placeholderEvent.allDay) {
                        placeholderEvent.start = moment(form.find('input[name="date"]').val(), "DD-MM-YYYY");
                        placeholderEvent.end   = moment(placeholderEvent.start).add(1, 'd');
                    } else {
                        placeholderEvent.start = moment(form.find('input[name="date"]').val(), "DD-MM-YYYY HH:mm");
                        placeholderEvent.end   = moment(placeholderEvent.start).add(1, 'h');
                    }
                    c.dom.fullCalendar('renderEvent', placeholderEvent);

                    c.ccreate(data, function(responseText) {
                        if (responseText.length) {
                            // Remove the placeholder and render the actual event
                            c.dom.fullCalendar('removeEvents', 'placeholder');
                            c.dom.fullCalendar('renderEvent', JSON.parse(responseText), true);
                        }
                        c.bubble.hide();
                    });
                    c.clearForms()
                })
            }
            else {
                this.submit(function(event) {
                    event.preventDefault();
                    var data = $(this).serialize()

                    var matchEvents = function(eventId) {
                        return function(event) {
                            try {
                                return event.data.meta.eventId == eventId;
                            } catch (e) {
                                return false;
                            }
                        }
                    };

                    c.cupdate(data, function(responseText) {
                        if (responseText.length) {
                            // Update the event (well, remove and replace)
                            // instead of adding a new one.
                            var newEvent = JSON.parse(responseText);
                            var eventId  = newEvent.data.meta.eventId;
                            c.dom.fullCalendar('removeEvents', matchEvents(eventId));
                            c.dom.fullCalendar('renderEvent', newEvent);
                        }
                        c.bubble.hide();
                    })
                })
            }
        }
    })

    // Allow datepicker options to be set in options, too.
    // (Get default date out of full calendar.)
    $('#datepicker').datepicker(c.options.datepicker);

    return this
}

/**
 * A more sementically-correct version of getForm({no args})
 * @return {*}
 */
Calendar.prototype.getForms = function() {
    return this.getForm()
}

/**
 * Helper method for getting a form from the bubble
 * @param id
 * @return {*}
 */
Calendar.prototype.getForm = function(id) {
    if(id === undefined) {
        return this.bubble.forms
    }
    if(this.bubble.forms[id] === undefined) {
        return []
    }
    return this.bubble.forms[id]
}

/**
 * Wrapping method for jQuery post so that a common success callback can be applied to all requests
 * @param url
 * @param data
 * @param successCallback
 */
Calendar.prototype.post = function(url, data, successCallback, refetch) {

    refetch = refetch === undefined

    var cal = this

    $.post(url, data, function(data) {
        // if a callback is defined, just use that
        if (successCallback && typeof(successCallback) === "function") {
            successCallback(data)
        }
        // if no callback defined, use a default which provides user feedback that the AJAX call worked
        else {
            // console.log('-- Default AJAX callback --')
            // console.log(data)
        }

        // refetch all the events in the view by default
        // this is horribly inefficient, but fullCalendar's cache needs to be refreshed when changes are made
        if(refetch) {
            cal.dom.fullCalendar('refetchEvents')
        }
    })
}

Calendar.prototype.clearForms = function() {
    $.each(this.getForms(), function() {
        this.find('input[type=text], textarea').val('')
    })
}


/**
 * Set the bubble suppressor to true
 */
Calendar.prototype.suppressOne = function() {
    this.suppress = true
}

/**
 * CRUD method: create
 * @param data
 * @param callback
 */
Calendar.prototype.ccreate = function(data, callback) {
    this.post(this.options.crud.c, data, callback, false)
}

/**
 * CRUD method: read
 * @param data
 * @param callback
 */
Calendar.prototype.cread = function(data, callback) {
    this.post(this.options.crud.r, data, callback, false)
}

/**
 * CRUD method: update
 * @param data
 * @param callback
 */
Calendar.prototype.cupdate = function(data, callback) {
    this.post(this.options.crud.u, data, callback, false)
}

/**
 * CRUD method: delete
 * @param data
 * @param callback
 */
Calendar.prototype.cdelete = function(data, callback) {
    this.post(this.options.crud.d, data, callback, false)
}

/**
 * Re-offsets a start or end timestamp generated by FullCalendar's events callback.
 *
 * Fullcalendar thinks that event boundaries are UTC (startDay 00:00:00 UTC)
 * instead of the current timezone (startDay 00:00:00 $timezone).
 * This method offsets them back to the current timezone.
 *
 * (This offset won't be quite right if client.timezone != server.timezone,
 *  but in most cases it will be good enough.)
 *
 * @param timestamp
 * @returns Number
 */
Calendar.reOffsetTimestamp = function(timestamp) {
    var timezoneOffset = new Date(timestamp*1000).getTimezoneOffset() * 60;
    return timestamp + timezoneOffset;
}

/**
 * Bubble-wrapping object
 * @constructor
 */
function Bubble(calendar, bubbleDomId) {
    this.calendar = calendar
    this.forms = {}
    this.dom = $('#'+bubbleDomId)
    this.initForms()
}

/**
 * Go through the bubble's DOM and cache all its forms
 * @param bubbleDomId
 */
Bubble.prototype.initForms = function() {
    var bbl = $('#content');

    // define a way for the each loop to reach the parent
    var self = this

    // cache the forms found within the bubble
    bbl.find('form').each(function() {
        var frm = $(this)
        self.forms[frm.attr('id')] = frm
    })

}

/**
 * Wrapper method for positioning a bubble and filling it with content
 * @param method
 * @param eventData
 * @param mouseEvent
 * @param targetElement
 */
Bubble.prototype.display = function(method, eventData, mouseEvent, targetElement) {

    // check that this isn't being suppressed
    if(this.calendar.suppress === true) {
        this.calendar.suppress = false
        return
    }

	/* Hide "Posted By" if there is no author
	 * (This happens on subscribed vcals for example)
	 */
	if (eventData.authorId == -1) {
		$('[data-event-modal-field="post-by"]').hide();
	}
	else {
		$('[data-event-modal-field="post-by"]').show();
	}

    // Hide reviewed by as well
    if (!('reviewerId' in eventData) || eventData.reviewerId == -1) {
        $('[data-event-modal-field="reviewedBy"]').hide();
    }
    else {
        $('[data-event-modal-field="reviewedBy"]').show();
    }

    if (!eventData.location || !eventData.location.length) {
        $('[data-event-modal-field="location"]').hide();
    }
    else {
        $('[data-event-modal-field="location"]').show();
    }

    // Fill in resauce borking separately
    $('ul.relatedBookings').empty();
    if ('relatedBookings' in eventData && eventData.relatedBookings.length > 0) {
        $('ul.relatedBookings').prepend('<br /><h3>Related Bookings</h3>');
        eventData.relatedBookings.forEach(function(v) {
            var row = $('<li />').html('('+v.status.name+') <a href="'+v.href+'">'+v.title+'</a>');
            $('ul.relatedBookings').append(row);
        });
    }
    delete eventData.relatedBookings;

    this.fillForm(method+'Event', eventData);
    this.fillForm(method+'Task', eventData);
}


/**
 * Populate the nominated form with the data supplied
 * @param formId
 * @param vars
 */
Bubble.prototype.fillForm = function(formId, vars) {
    // retrieve form from cache
    var form = $(this.forms[formId]);

    /**
     * Fill element helper: if the element that is getting filled has a
     * 'is-html' data element, then it gets filled with HTML - otherwise with
     * text.
     *
     * @param element The bubble element
     * @param key     The key that is getting updated
     * @param value   The value that is added
     * @param allVars All variables that are getting updated
     */
    var fillElement = function(element, key, value, allVars) {
        if (element) {
            if (element.length && value && value.length) {
                if (element.data('is-html')) {
                    element.html(value)
                }
                else {
                    element.text(value)
                }
                // Maybe this element is a link?
                // If so, fill its URL in.
                if (element.data('is-user-link')) {
                    if (key + 'Id' in allVars) {
                        element.attr('href', '/search/user/'+allVars[key+'Id']).prop('disabled', true);
                    } else {
                        element.attr('href', '#').prop('disabled', true);
                    }
                }
            }
            else {
                element.text('')
            }
        }
    }

    // iterate across the variables supplied, and insert them into the appropriate form elements
    $.each(vars, function(k, v) {
        // first test if it's an input with the name we're after
        var element = form.find('[name=' + k + ']')

        if(element.length) {
            var type = element.attr('type')
            // textboxes and textareas get filled with text...
            if(type == 'text' || element.is('textarea')) {
                element.val(v)
            }
            // ...and if hidden, also populate its associated span...
            else if(type == 'hidden') {
                element.attr('value', v)
                fillElement(form.find('.'+k), v)
            }
            // ...and checkboxes get checked or unchecked
            else if(type == 'checkbox') {
                element.prop('checked', v == 1)
            }
        }
        // if no form input element found, try for an element with the right class
        else {
            /* Check for data attribute first
             */
            var dataSelector = $('[data-event-modal-field="'+k+'"]');
            if (dataSelector.length) {
                fillElement(dataSelector, k, v, vars)
            }
            /* Otherwise assume classes
             */
            else {
                fillElement(form.find('.'+k), k, v, vars)
            }
        }
    })
};


Bubble.prototype.hide = function() {
    $(document).foundation('reveal', 'close');
};

/* Helper functions */

/**
 * Copy data from one form to another
 * @param from
 * @param to
 * @param selector
 */
function cp(from, to, selector) {
    $.each(selector, function() {
        // copy the date in the createEvent form into the createTask form
        $(['input[name='+this+']', '[data-event-modal-field="'+this+'"]']).each(function() {
            var orig = c.bubble.forms['create'+from.capitalise()].find(this.toString())
            var targ = c.bubble.forms['create'+to.capitalise()].find(this.toString())

            if(orig.is('input')) {
                targ.val(orig.val())
            }
            else {
                targ.html(orig.html())
            }
        })
    })
}

/**
 * Convert a datetime to sentence format
 * @param datetime
 * @return {String}
 */
function calendarDate(datetime) {
    var hm = calendarTime(datetime)
    var d = calendarDateOnly(datetime)
    return d + ', ' + hm
}

/**
 * Convert a datetime into a date only
 * @param datetime
 * @return {String}
 */
function calendarDateOnly(datetime) {
    return datetime.format('ddd, Do MMM')
    //return $j.fullCalendar.formatDate(datetime, 'ddd, dS MMM')
}

/**
 * Convert a datetime to a 12hour time
 * @param datetime
 * @return {String}
 */
function calendarTime(datetime) {
    return datetime.format('h:mma')
    //return $j.fullCalendar.formatDate(datetime, 'h:mmtt')
}