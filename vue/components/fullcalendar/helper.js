import {toMoment} from '@fullcalendar/moment'

/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */
export default class FullCalendarHelper {

    /**
     * Constructor
     *
     * @param {object} component The FullCalendar component.
     * @param {object} config    Helper configuration, currently contains date
     *                           display formats.
     * @param {jQuery} jQuery    A jQuery instance, used for HTML manipulation
     *                           that FullCalendar's Vue.js can't handle.
     */
    constructor(component, config, jQuery) {
        this.component = component;
        this.config    = config;
        this.jQuery    = jQuery;

        // Does this need to take the FullCalendar as well?
        // If so, where do I create this?
        // * mounted?
        // * nextTick within mounted?
        // Alternately just pass the component in directly.
    }


    /**
     * Event loading callback: show a spinner whilst events are loading,
     * remove the spinner once they have finished.
     * @see https://fullcalendar.io/docs/loading
     *
     * @param {boolean} isLoading True when events start loading,
     *                            false once they are finished.
     */
    onLoading(isLoading) {
        // TODO find a non-jQuery dependent way to do this
        if (isLoading === true) {
            // this.$refs.toast.activate('Loading...');
            this.jQuery.magnificPopup.open({
                showCloseBtn: false,
                closeOnBgClick: false,
                enableEscapeKey: false,
                items: {
                    src: "<div class='preview loader'></div>",
                    type: 'inline'
                }
            });
        } else {
            // this.$refs.toast.timeout('Done', true, 3000, 2000);
            this.jQuery.magnificPopup.close();
        }
    }


    /**
     * Event render callback: manually tweaks the event's rendering, and
     * can also conditionally show/hide the event.
     * @see https://fullcalendar.io/docs/eventRender
     *
     * @param {object} eventRenderInfo
     * @return {boolean}
     */
    eventRender(eventRenderInfo) {
        let calEvent = eventRenderInfo.event,
            self = this;

        // Always render placeholders in the most basic manner
        if (calEvent.id === 'placeholder') {
            return true;
        }

        // Ignore the phantom event that the API puts there
        if (!calEvent?.extendedProps?.data) {
            return false;
        }

        // Don't render me if I'm marked as do not render
        if ('is_rendered' in calEvent && !calEvent?.is_rendered) {
            return false;
        }

        // Mangle the event using old-fashioned jQuery
        // TODO don't depend on jQuery for this
        let $element = self.jQuery(eventRenderInfo.el),
            inner = $element.find('.fc-content'),
            title = $element.find('.fc-title'),
            content = $element.find('.fc-event-content');

        // If appropriate, indicate this event can be attended
        if (Boolean(calEvent?.extendedProps?.data?.attendance?.icon)) {
            let span = '<span class="att">(A)</span>';
            if (content.length === 1) {
                content.append(span)
            } else {
                inner.append(span)
            }
        }

        // If this event is a task, alter the state of the checkbox accordingly.
        if (this.isTask(calEvent)) {
            inner.prepend(
                self.jQuery('<input type="checkbox" name="completed" class="plain" />')
                    .attr('id', 't' + calEvent?.extendedProps?.data?.meta?.eventId)
            );
            if (Boolean(calEvent?.extendedProps?.data?.meta?.completed)) {
                title.addClass('strike');
                inner.find(':checkbox').prop('checked', true)
            }
        }

        // Apply the visible class?
        $element.addClass(calEvent?.extendedProps?.data?.styles?.visible);
        return true;
    }


    /**
     * Helper function for converting a native JS Date to a Moment object.
     * @see https://fullcalendar.io/docs/moment-plugins
     *
     * @param {Date} date
     */
    toMoment(date) {
        // Attempt to pass in the fullCalendar API itself
        return toMoment(date, this.component.$refs.fullCalendar.getApi());
    }


    /**
     * Is the given event a task or not? This affects how it's rendered.
     *
     * @param {object} event
     * @return {boolean}
     */
    isTask(event) {
        return Number(event?.extendedProps?.data?.meta?.eventTypeId) === this.config.taskTypeId;
    }


    /**
     * Helper for rendering the title of the day view.
     * Without this the calendar renders the date twice for some reason.
     *
     * @param dateObject
     * @return {string}
     */
    dayTitleFormat(dateObject) {
        return this.toMoment(dateObject.date).format(this.config.dateDisplayFormats.momentJsDateLong);
    }

    /**
     * Helper function to get the difference between two dates.
     * @param start
     * @param end
     * @param type
     * @returns {*}
     */
    dateDiff(start, end, type = 'days') {
        var startDate = this.toMoment(start),
            endDate = this.toMoment(end);

        return endDate.diff(startDate, type);
    }


    /**
     * Helper function to determine the FullCalendar component's content height.
     *
     * This is a straight port of the old jQuery FullCalendar content height
     * callback.
     *
     * @return {number}
     */
    contentHeight() {
        // TODO minimize jQuery dependency
        let windowHeight   = this.jQuery(window).height(),
            // The amount of space between the top of the window and the start of the calendar
            calendarOffset = this.jQuery(this.component.$el).find('.fc-view-container').offset().top,
            // <section> margin-bottom and #container padding-bottom
            footerHeight   = this.jQuery('#footer').outerHeight(),
            otherPadding   = 32,
            contentHeight  = windowHeight - calendarOffset - footerHeight - otherPadding;

        // Force a min height of 500px
        if (contentHeight < 500) {
            return 500;
        }

        return contentHeight;
    }
}


/**
 * Constant definition for the formatting of an event's time.
 */
FullCalendarHelper.prototype.eventTimeFormat = {
    hour: 'numeric',
    hour12: true,
    minute: '2-digit',
    meridiem: 'short',
    omitZeroMinute: true,
};
