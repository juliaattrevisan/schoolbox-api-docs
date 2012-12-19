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
    this.dom = $j('#'+id)
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
            enabled: true,
            parentSelector: '#calendar-controls',
            btnSelector: '#fc-button-filter',
            checkboxSelector: '.applyFilters',
            applyFiltersSelector: '#applyFilters',
            boxSelector: '.filter-drop',
            listSelector: '.filter-list'
        },
        fullCalendar: {
            contentHeight: $j(window).height() - this.dom.offset().top - 60,
            header: {
                left: 'prev,today,next',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            }
        },
        x: true
    }
    this.filters = {

        interaction: function(cal) {

            var calendarControls = $j(cal.options.filter.parentSelector)

            // show the filter 'dialog' when the button is clicked
            cal.dom.delegate(cal.options.filter.btnSelector, 'click', function() {
                cal.bubble.hide()
                calendarControls.find(cal.options.filter.boxSelector).toggle()
                $j(cal.options.filter.btnSelector).toggleClass('engaged')
                return false
            })

            // run the filter checker to show and hide the items
            calendarControls.delegate(cal.options.filter.checkboxSelector, 'click', function() {
                cal.filters.organise(cal, ["campus", "groups"])
            })

            calendarControls.delegate(cal.options.filter.applyFiltersSelector, 'click', function() {
                cal.filters.organise(cal, ["campus", "groups"])
                calendarControls.find(cal.options.filter.boxSelector).hide()
                $j(cal.options.filter.btnSelector).removeClass('engaged')
                return false
            })

            $j('body').click(function() {
                $j(cal.options.filter.boxSelector).removeClass('engaged').hide()
            })

            calendarControls.delegate(cal.options.filter.boxSelector, 'click', function(e) {
                e.stopPropagation()
            })

            cal.filters.organise(cal, ["campus", "groups"])
        },

        organise: function(cal, filterArray) {
            var flist = {}
            var calendarControls = $j(cal.options.filter.parentSelector)

            for (var i = 0; i < filterArray.length; i++) {
                var k = 0
                flist[i] = [filterArray[i], {}]

                calendarControls.find(cal.options.filter.listSelector + ' .' + filterArray[i] + ' input').each(function() {
                    var n = $j(this).attr('value')
                    // show related entries
                    if(this.checked) {
                        flist[i][1][k++] = n
                        cal.dom.find('div.fc-event.' + n).show()
                    }
                    // hide related entries
                    else {
                        if (cal.dom.find('div.fc-event.' + n).is(':visible')) {
                            cal.dom.find('div.fc-event.' + n).hide()
                        }
                    }
                });
            }

            k = 0;
            flist[filterArray.length] = ['events', {}];

            calendarControls.find(cal.options.filter.listSelector + ' .events input').each(function() {
                var n = $j(this).attr('value');
                // show related entries
                if(this.checked) {
                    flist[i][1][k++] = n;
                    cal.dom.find('div.fc-event.personal.' + n).show();
                }
                // hide related entries
                else {
                    if (cal.dom.find('div.fc-event.' + n).is(':visible')) {
                        cal.dom.find('div.fc-event.' + n).hide();
                    }
                }
            });

            cal.post('/ajax/calendarFilter.php', {fList : flist}, null, false);

        }
    }

    // preserve the filter and fullCalendar specs
    var filter = $j.extend({}, this.options.filter, opts.filter)
    var fullCalendar = $j.extend({}, this.options.fullCalendar, opts.fullCalendar)

    // perform the merge
    $j.extend(this.options,opts)

    // rewrite the preserved submerge back into the options
    this.options.filter = filter
    this.options.fullCalendar = fullCalendar

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

    if(this.options.filter.enabled) {
        // mash the filters in
        var headerRight = this.dom.find('td.fc-header-right')
        headerRight.prepend(headerRight.children('.fc-button')
            .first().clone().removeClass('fc-button-month fc-button-agendaWeek fc-button-agendaDay fc-state-active')
            .addClass('fc-button-filter').attr('id','fc-button-filter')
        )

        $j(this.options.filter.btnSelector).find('.fc-button-content').html('filters')

        // and now make all the buttons on the header look the way they're meant to
        var header = this.dom.children('.fc-header')
        header.addClass('calendar-controls')
        header.find('span.fc-button').each(function() {
            var self = $j(this)
            var text = self.find('span.fc-button-content').html()
            self.addClass('filter-change '+text).html('<span></span>'+text)

        })
    }


    // Event creation form listeners
    $j.each(this.getForms(), function(k, v) {

        if(k.slice(0, 6) == 'create') {
            this.submit(function() {
                var data = $j(this).serialize()

                c.create(data, function(responseText) {
                    if (responseText.length) {
                        var responseData = new Function('return ' + responseText)
                        var newEvent = responseData()

                        c.dom.fullCalendar('renderEvent', newEvent)
                    }
                })
                c.bubble.hide()
                return false
            })
        }
        else {
            this.submit(function() {
                var data = $j(this).serialize()

                c.update(data, function(responseText) {
                    if (responseText.length) {
                        var responseData = new Function('return ' + responseText)
                        var newEvent = responseData()

                        c.dom.fullCalendar('renderEvent', newEvent)
                    }
                })
                c.bubble.hide()
                return false
            })

        }

    })


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

    $j.post(url, data, function(data) {
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
Calendar.prototype.create = function(data, callback) {
    this.post(this.options.crud.c, data, callback)
}

/**
 * CRUD method: read
 * @param data
 * @param callback
 */
Calendar.prototype.read = function(data, callback) {
    this.post(this.options.crud.r, data, callback)
}

/**
 * CRUD method: update
 * @param data
 * @param callback
 */
Calendar.prototype.update = function(data, callback) {
    this.post(this.options.crud.u, data, callback)
}

/**
 * CRUD method: delete
 * @param data
 * @param callback
 */
Calendar.prototype.delete = function(data, callback) {
    this.post(this.options.crud.d, data, callback)
}



/**
 * Bubble-wrapping object
 * @constructor
 */
function Bubble(calendar, bubbleDomId) {
    this.calendar = calendar
    this.forms = {}
    this.dom = $j('#'+bubbleDomId)
    this.initForms(bubbleDomId)
}

/**
 * Go through the bubble's DOM and cache all its forms
 * @param bubbleDomId
 */
Bubble.prototype.initForms = function(bubbleDomId) {
    var bbl = $j('#'+bubbleDomId)

    // define a way for the each loop to reach the parent
    var self = this

    // cache the forms found within the bubble
    bbl.find('form').each(function() {
        var frm = $j(this)
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

    var id = method
    if(eventData !== undefined) {
        id += (eventData.eventTypeId == this.calendar.options.taskTypeId ? 'Task' : 'Event')
    }
    else {
        id += 'Event'
    }
    var xPos = mouseEvent.pageX
    var yPos = $j(targetElement).offset().top + 5
    this.fillForm(id, eventData)
    this.showForm(id, xPos, yPos)
}

/**
 * Display a form in a particular location - does not perform data loading
 * @param id
 * @param x horizontal position of the bubble speech point
 * @param y vertical position of the bubble speech point
 */
Bubble.prototype.showForm = function(id, x, y) {
    // hide the bubble, but keep it displayed
    this.dom.css({visibility:'hidden', display:'block'})
    $j.each(this.forms, function(i, elem) {
        if(i == id) {
            this.css({display:'block'})
        }
        else {
            this.css({display:'none'})
        }
    })

    // now that the correct form is filling the bubble, position appropriately
    // position the popup in the right spot!!
    var mid = this.dom.find('.middle')

    // unfortunately dimension-related things don't work when elements are not displayed
    this.dom.css('visibility', 'hidden')
    this.dom.show()

    var bubbleHeight = mid.outerHeight() + mid.find('.bottom').outerHeight()
    var topEdge = Math.max(0, y - bubbleHeight)

    var bubbleWidth = mid.outerWidth()
    var desiredX  = x - bubbleWidth + 25
    var leftEdge = Math.max(desiredX, 5)

    var talkMarkX = 22 + leftEdge - desiredX

    var pos = {top: topEdge, left: leftEdge}

    this.dom.offset(pos)
    this.dom.find('.bottom').css('right', talkMarkX)
    this.dom.css('visibility', 'visible')

}

/**
 * Make the bubble go away
 */
Bubble.prototype.hide = function() {
    this.dom.css({visibility:'visible', display:'none'})
}

/**
 * Populate the nominated form with the data supplied
 * @param formId
 * @param vars
 */
Bubble.prototype.fillForm = function(formId, vars) {
    // retrieve form from cache
    var form = this.forms[formId]

    // iterate across the variables supplied, and insert them into the appropriate form elements
    $j.each(vars, function(k, v) {
        // first test if it's an input with the name we're after
        var element = form.find('[name=' + k + ']')
        if(element.length) {
            var type = element.attr('type')
            // textboxes and textareas get filled with text...
            if(type == 'text' || element.is('textarea')) {
                element.attr('value', v)
            }
            // ...and if hidden, also populate its associated span...
            else if(type == 'hidden') {
                element.attr('value', v)
                form.find('.'+k).html(v)
            }
            // ...and checkboxes get checked or unchecked
            else if(type == 'checkbox') {
                element.prop('checked', v == 1)
            }
        }
        // if no form input element found, try for an element with the right class
        else {
            element = form.find('.'+k)
            if(element.length) {
                element.html(v)
            }
        }
    })
}

/* Helper functions */

/**
 * Copy data from one form to another
 * @param from
 * @param to
 * @param selector
 */
function cp(from, to, selector) {

    $j.each(selector, function() {
        // copy the date in the createEvent form into the createTask form
        $j(['input[name='+this+']', '.'+this]).each(function() {
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
    var S,
        p = datetime.toString().split(/[ :]/),
        hm = calendarTime(datetime)

    if(p[2] == 11 || p[2] == 12) { S = 'th'; }
    else { var i = p[2].charAt(p[2].length - 1)
        if(i == 1) { S = 'st' }
        else if(i == 2) { S = 'nd' }
        else if(i == 3) { S = 'rd' }
        else { S = 'th' }
    }
    return p[0] + ', ' + parseInt(p[2]) + S + ' ' + p[1] + ', ' + hm
}

/**
 * Convert a datetime to a 12hour time
 * @param datetime
 * @return {String}
 */
function calendarTime(datetime) {
    var p = datetime.toString().split(/[ :]/)
    if(p[4] == 0) { return '12:00am' }
    else if(p[4] > 12) { return (p[4] - 12) + ':' + p[5] + 'pm' }
    else { return parseInt(p[4]) + ':' + p[5] + 'am' }

}