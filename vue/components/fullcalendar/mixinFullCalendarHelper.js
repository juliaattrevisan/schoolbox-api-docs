/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import dayjs from 'dayjs';
import { EVENT_VARIANTS } from '@schoolboxCalendarEvent/event-variants';


export default {

    data () {
        return {
            fullCalendarHelper_eventTimeFormat: {
                hour: 'numeric',
                hour12: true,
                minute: '2-digit',
                meridiem: 'short',
                omitZeroMinute: true
            },
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
        fullCalendarHelper_onLoading (isLoading) {
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
        fullCalendarHelper_eventRender(eventRenderInfo) {

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
         * Is the given event a task or not? This affects how it's rendered.
         *
         * @param {object} event
         * @return {boolean}
         */
        fullCalendarHelper_isTask (event) {
            return event?.extendedProps?.data?.meta?.variant === EVENT_VARIANTS.TASK;
        },

        /**
         * Helper function to get the difference between two dates.
         * @param start
         * @param end
         * @param type
         * @returns {*}
         */
        fullCalendarHelper_dateDiff (start, end, type = 'days') {
            const startDate = dayjs(start);
            const endDate = dayjs(end);
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
        fullCalendarHelper_contentHeight() {
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

            // A reasonable guess at the footer height, as it doesn't yet exist.
            const footerHeight = 40;

            // A reasonable guess at the space between the start of the content
            // and the calendar content.
            const otherPadding = 180;
            const contentHeight = windowHeight - calendarOffset - footerHeight - otherPadding;

            // Force a min height of 500px
            if (contentHeight < 500) {
                return 500;
            }
            return contentHeight;
        }

    }

}
