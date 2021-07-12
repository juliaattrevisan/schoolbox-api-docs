/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { toMoment } from '@fullcalendar/moment';
import { ATTENDANCE_STATUS } from '@schoolboxCalendarEvent/attendance-status';
import { EVENT_VARIANTS } from '@schoolboxCalendarEvent/event-variants';

const MAX_DAY_MAX_EVENT_ROWS = 3;

export const mixinFullCalendarHelper = {

    data () {
        return {
            fullCalendarHelper_eventTimeFormat: {
                hour: 'numeric',
                hour12: true,
                minute: '2-digit',
                meridiem: 'short',
                omitZeroMinute: true
            },
            test: 'ha'
        }
    },

    methods: {

        /**
         * Event loading callback: show a spinner whilst events are loading,
         * remove the spinner once they have finished.
         * @see https://fullcalendar.io/docs/loading
         *
         * @param {boolean} isLoading True when events start loading,
         *                            false once they are finished.
         */
        $_fullCalendarHelper_onLoading (isLoading) {
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
        },

        /**
         * Event render callback: manually tweaks the event's rendering, and
         * can also conditionally show/hide the event.
         * @see https://fullcalendar.io/docs/eventRender
         *
         * @param {object} eventRenderInfo
         * @return {boolean}
         */
        $_fullCalendarHelper_eventRender(eventRenderInfo) {

            const calEvent = eventRenderInfo.event;

            // Always render placeholders in the most basic manner
            if (calEvent.id === 'placeholder') {
                calEvent.setProp('display', 'block');
                return true;
            }

            // Ignore the phantom event that the API puts there
            if (!calEvent?.extendedProps?.data) {
                calEvent.setProp('display', 'none');
                return false;
            }

            // Don't render me if I'm marked as do not render
            if ('is_rendered' in calEvent && !calEvent?.is_rendered) {
                calEvent.setProp('display', 'none');
                return false;
            }

            // Apply the visible class?
            if (calEvent?.extendedProps?.data?.styles?.visible) {
                eventRenderInfo.el.classList.add(calEvent?.extendedProps?.data?.styles?.visible);
            }

            calEvent.setProp('display', 'block');

            return true;
        },

        /**
         * Helper function for converting a native JS Date to a Moment object.
         * @see https://fullcalendar.io/docs/moment-plugins
         *
         * @param {Date} date
         */
        $_fullCalendarHelper_toMoment (date) {
            // Attempt to pass in the fullCalendar API itself
            return toMoment(date, this.$refs.fullCalendar.getApi());
        },

        /**
         * Is the given event a task or not? This affects how it's rendered.
         *
         * @param {object} event
         * @return {boolean}
         */
        $_fullCalendarHelper_isTask (event) {
            return event?.extendedProps?.data?.meta?.variant === EVENT_VARIANTS.TASK;
        },

        /**
         * Helper function to get the difference between two dates.
         * @param start
         * @param end
         * @param type
         * @returns {*}
         */
        $_fullCalendarHelper_dateDiff (start, end, type = 'days') {
            const startDate = this.$_fullCalendarHelper_toMoment(start);
            const endDate = this.$_fullCalendarHelper_toMoment(end);
            return endDate.diff(startDate, type);
        },

        /**
         * Helper function to determine the FullCalendar component's content height.
         *
         * This is a straight port of the old jQuery FullCalendar content height
         * callback, updated to not break the call stack under FullCalendar 5.8.
         *
         * @return {number}
         */
        $_fullCalendarHelper_contentHeight() {
            const windowHeight = window.innerHeight;
            // FullCalendar is prone to raising a maximum call stack exceeded
            // error when:
            // * dayMaxEventRows isn't false
            // * this content height is calculated relative to the current size
            //   of the calendar's view
            // Therefore, the calculation has been adjusted to be relative to
            // elements which are known to exist independent of the calendar;
            // in this case, the content pane and the footer.
            const calendarOffset = this.jQuery('#content').offset().top;
            // <section> margin-bottom and #container padding-bottom
            const footerHeight = this.jQuery('#footer').outerHeight();

            // A reasonable guess at the space between the start of the content
            // and the calendar content.
            const otherPadding = 180;
            const contentHeight = windowHeight - calendarOffset - footerHeight - otherPadding;

            // Force a min height of 500px
            if (contentHeight < 500) {
                return 500;
            }
            return contentHeight;
        },


        /**
         * Translate the provided boolean `eventLimit` value into a sensible
         * `dayMaxEventRows` value.
         *
         * Leaving `dayMaxEventRows` as `true` maxes out the number of rows at
         * something which will fit in the height of a table cell: this causes
         * a "Maximum call stack size exceeded" error with a large enough number
         * of events.
         *
         * In experimentation, three rows of events seems to be the upper limit.
         *
         * @see https://fullcalendar.io/docs/dayMaxEventRows
         *
         * @param {Boolean} eventLimit Is the number of events limited?
         * @returns {Number|Boolean} A number of rows to display per day, or
         *                           false if the number of rows is not
         *                           constrained.
         */
        $_fullCalendarHelper_determineDayMaxEventRows(eventLimit) {
            return eventLimit ? MAX_DAY_MAX_EVENT_ROWS : false;
        },
    }
}
