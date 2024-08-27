/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { EVENT_VARIANTS } from "@schoolboxCalendarEvent/event-variants";

export default {
    methods: {
        /**
         * Event loading callback: show a spinner whilst events are loading,
         * remove the spinner once they have finished.
         * @see https://fullcalendar.io/docs/loading
         *
         * @param {boolean} isLoading True when events start loading,
         *                            false once they are finished.
         */
        fullCalendarHelper_onLoading(isLoading) {
            if (isLoading === true) {
                document.dispatchEvent(new Event("OVERLAY_LOADER_SHOW"));
            } else {
                document.dispatchEvent(new Event("OVERLAY_LOADER_HIDE"));
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
            if (calEvent.id === "placeholder") {
                calEvent.setProp("display", "block");
                return true;
            }

            // Ignore the phantom event that the API puts there
            if (!calEvent?.extendedProps?.data) {
                calEvent.setProp("display", "none");
                return false;
            }

            // Don't render me if I'm marked as do not render
            if ("is_rendered" in calEvent && !calEvent?.is_rendered) {
                calEvent.setProp("display", "none");
                return false;
            }

            // Apply the visible class?
            if (calEvent?.extendedProps?.data?.styles?.visible) {
                eventRenderInfo.el.classList.add(calEvent?.extendedProps?.data?.styles?.visible);
            }

            calEvent.setProp("display", "block");

            return true;
        },

        /**
         * Is the given event a task or not? This affects how it's rendered.
         *
         * @param {object} event
         * @return {boolean}
         */
        fullCalendarHelper_isTask(event) {
            return event?.extendedProps?.data?.meta?.variant === EVENT_VARIANTS.TASK;
        },

        /**
         * Is the given event an EOTC event or not? This affects how it's rendered.
         *
         * @param {object} event
         * @return {boolean}
         */
        fullCalendarHelper_isEOTC(event) {
            return event?.extendedProps?.data?.custom?.isEOTC === true;
        },

        /**
         * Helper function to get the difference between two dates.
         * @param start
         * @param end
         * @param type
         * @returns {*}
         */
        fullCalendarHelper_dateDiff(start, end, type = "day") {
            const startDate = dayjs(start);
            const endDate = dayjs(end);
            return endDate.diff(startDate, type);
        },

        /**
         * To display a week date range like:
         * September 26th - October 2nd, 2021
         * We also add the extra formatting of removing the second dates month if it's the same as the second:
         * October 3rd - 9th, 2021
         *
         * @param { Object }
         *
         * @returns { String }
         */
        fullCalendarHelper_titleFormatWeek(date) {
            const startDate = date.start.array;
            const endDate = date.end.array;
            const isSameYear = dayjs(startDate).isSame(endDate, "year"); //only display the first year if it's different than the endDate's
            const startDateFormat = isSameYear
                ? dayjs(startDate).format("MMMM Do")
                : dayjs(startDate).format("MMMM Do, YYYY");
            const isSameMonth = dayjs(startDate).isSame(endDate, "month"); //only display the end date's month if it's different than the startDate's
            const endDateFormat = isSameMonth
                ? dayjs(endDate).format("Do, YYYY")
                : dayjs(endDate).format("MMMM Do, YYYY");
            return `${startDateFormat} – ${endDateFormat}`;
        },

        /**
         * To display an event time like 8:30am - 9:45am
         * Zero minutes will also be taken off, so 11:00am will become 11am
         * Will also show only the start date if there's no end
         * And will consider all day events, returning an empty string
         *
         * @param { Object }
         *
         * @returns { String }
         */
        fullCalendarHelper_eventTimeFormat(date) {
            if (date.start && date.end) {
                const startTime = dayjs(date.start.array).format("h:mma").replace(":00", "");
                const endTime = dayjs(date.end.array).format("h:mma").replace(":00", "");
                return `${startTime} - ${endTime}`;
            } else if (date.start) {
                return dayjs(date.start.array).format("h:mma").replace(":00", "");
            } else {
                //typically an all day event
                return "";
            }
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
            const calendarOffset = this.jQuery("#content").offset().top;

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
        },
    },
};
