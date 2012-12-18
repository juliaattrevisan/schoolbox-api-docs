/* Alaress FullCalendar implementation
* @dep customised fullcalendar.min.js
* */

// handy string function
// http://stackoverflow.com/questions/1026069/capitalize-the-first-letter-of-string-in-javascript
String.prototype.capitalise = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}

/**
 * Calendar object
 * @param id
 * @constructor
 */
function Calendar(id, bubbleId, opts) {
    var self = this
    this.id = id
    this.dom = $j('#'+id)
    this.bubble = new Bubble(this, bubbleId)

    // default options
    this.options = {
        taskTypeId: 0,  // disabled by default
        filter: {
            enabled: true,
            btnSelector: '#fc-button-filter',
            domSelector: '.applyFilters',
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
            var filterBtn = $j(cal.options.filter.btnSelector)

            $j(filterBtn).click(function(){
                cal.bubble.hide()
                $j('.filter-drop').toggle()
                filterBtn.toggleClass('engaged')
                return false
            })

            // run the filter checker to show and hide the items
            $j(cal.options.filter.domSelector).click(function() {
                cal.filters.organise(cal, ["campus", "groups"])
            })

            $j(cal.options.filter.applyFiltersSelector).click(function() {
                cal.filters.organise(cal, ["campus", "groups"])
                $j('.filter-drop').hide()
                filterBtn.removeClass('engaged')
                return false
            })

            $j("body").click(function() {
                $j(cal.options.filter.boxSelector).hide()
                filterBtn.removeClass('engaged')
            })

            $j('.filter-drop').click(function(e) {
                e.stopPropagation()
            })

            cal.filters.organise(cal, ["campus", "groups"])
        },

        organise: function(cal, filterArray) {
            var flist = {};

            for (var i = 0; i < filterArray.length; i++) {

                var k = 0;
                flist[i] = [filterArray[i], {}];

                $j(cal.options.filter.listSelector + ' .' + filterArray[i] + ' input').each(function() {
                    var n = $j(this).attr('value');
                    // show related entries
                    if(this.checked) {
                        flist[i][1][k++] = n;
                        $j(cal.id + ' div.fc-event.' + n).show();
                    }
                    // hide related entries
                    else {
                        if ($j(cal.id + ' div.fc-event.' + n).is(':visible')) {
                            $j(cal.id + ' div.fc-event.' + n).hide();
                        }
                    }
                });
            }

            k = 0;
            flist[filterArray.length] = ['events', {}];

            $j('.filter-list .events input').each(function() {
                var n = $j(this).attr('value');
                // show related entries
                if(this.checked) {
                    flist[i][1][k++] = n;
                    $j(cal.id + ' div.fc-event.personal.' + n).show();
                }
                // hide related entries
                else {
                    if ($j(cal.id + ' div.fc-event.' + n).is(':visible')) {
                        $j(cal.id + ' div.fc-event.' + n).hide();
                    }
                }
            });

            $j.post('/ajax/calendarFilter.php', {fList : flist});

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
 *
 */
Calendar.prototype.init = function() {
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
    return this
}

Calendar.prototype.getForm = function(id) {
    if(this.bubble.forms[id] === undefined) {
        return []
    }
    return this.bubble.forms[id]
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


Bubble.prototype.display = function(method, eventData, mouseEvent, targetElement) {
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
 *
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

Bubble.prototype.hide = function() {
    this.dom.css({visibility:'visible', display:'none'})
}

Bubble.prototype.fillForm = function(formId, vars) {
    console.log(vars)

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