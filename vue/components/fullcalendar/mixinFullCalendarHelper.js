/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { EVENT_VARIANTS } from "@schoolboxCalendarEvent/event-variants";

const RELATED_EVENT_ACTIVE_CLASS = "sbx-related-events-active";
const RELATED_EVENT_MUTED_CLASS = "sbx-related-event-muted";
const RELATED_EVENT_RELATION_ATTRIBUTE = "data-calendar-event-relation";
const EVENT_FETCH_DAYS_BEFORE_VISIBLE_RANGE = 30;
const EVENT_FETCH_DAYS_AFTER_VISIBLE_RANGE = 100;

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

            this.fullCalendarHelper_attachRelatedEventHighlighting(eventRenderInfo);

            calEvent.setProp("display", "block");

            return true;
        },

        /**
         * Add relation metadata and hover/focus handlers for events that belong
         * to an existing FullCalendar group. Keeping this opt-in avoids grouping
         * unrelated calendar events that only share broad fallback metadata.
         *
         * @param {object} eventRenderInfo
         */
        fullCalendarHelper_attachRelatedEventHighlighting(eventRenderInfo) {
            const relationKey = this.fullCalendarHelper_getRelatedEventKey(eventRenderInfo.event);

            if (!relationKey) {
                return;
            }

            eventRenderInfo.el.setAttribute(RELATED_EVENT_RELATION_ATTRIBUTE, relationKey);
            eventRenderInfo.el.addEventListener("mouseenter", this.fullCalendarHelper_highlightRelatedEvents);
            eventRenderInfo.el.addEventListener("focusin", this.fullCalendarHelper_highlightRelatedEvents);
            eventRenderInfo.el.addEventListener("mouseleave", this.fullCalendarHelper_clearRelatedEventHighlights);
            eventRenderInfo.el.addEventListener("focusout", this.fullCalendarHelper_clearRelatedEventHighlights);
        },

        /**
         * Return a relation key for group-linked events only. Other event types
         * need explicit relation metadata before they can be safely grouped.
         *
         * @param {object} event
         * @return {?string}
         */
        fullCalendarHelper_getRelatedEventKey(event) {
            const groupClass = event?.classNames?.find((className) => /^group\d+$/u.test(className));
            if (groupClass) {
                return groupClass.replace(/^group/u, "group:");
            }

            return null;
        },

        /**
         * Dim events outside the active group while preserving the original
         * rendering of grouped events to avoid FullCalendar repaint issues.
         *
         * @param {Event} event
         */
        fullCalendarHelper_highlightRelatedEvents(event) {
            const relationKey = event.currentTarget.getAttribute(RELATED_EVENT_RELATION_ATTRIBUTE);
            const calendar = event.currentTarget.closest(".fc");

            if (!relationKey || !calendar || event.currentTarget.closest(".fc-list")) {
                return;
            }

            calendar.classList.add(RELATED_EVENT_ACTIVE_CLASS);
            calendar.querySelectorAll(`[${RELATED_EVENT_RELATION_ATTRIBUTE}]`).forEach((calendarEvent) => {
                if (calendarEvent.getAttribute(RELATED_EVENT_RELATION_ATTRIBUTE) === relationKey) {
                    calendarEvent.classList.remove(RELATED_EVENT_MUTED_CLASS);
                } else {
                    calendarEvent.classList.add(RELATED_EVENT_MUTED_CLASS);
                }
            });
        },

        /**
         * Remove hover/focus styling from the current FullCalendar instance.
         * This keeps the effect scoped when multiple calendars are rendered.
         *
         * @param {Event} event
         */
        fullCalendarHelper_clearRelatedEventHighlights(event) {
            const calendar = event.currentTarget.closest(".fc");

            if (!calendar) {
                return;
            }

            calendar.classList.remove(RELATED_EVENT_ACTIVE_CLASS);
            calendar.querySelectorAll(`[${RELATED_EVENT_RELATION_ATTRIBUTE}]`).forEach((calendarEvent) => {
                calendarEvent.classList.remove(RELATED_EVENT_MUTED_CLASS);
            });
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
         * Expand a visible FullCalendar range before requesting events.
         *
         * @param {{ start: Date, end: Date }} range
         * @return {{ start: Date, end: Date }}
         */
        fullCalendarHelper_expandEventFetchRange(range) {
            const start = new Date(range.start);
            start.setDate(start.getDate() - EVENT_FETCH_DAYS_BEFORE_VISIBLE_RANGE);

            const end = new Date(range.end);
            end.setDate(end.getDate() + EVENT_FETCH_DAYS_AFTER_VISIBLE_RANGE);

            return {
                ...range,
                start,
                end,
            };
        },

        /**
         * Determine whether an event overlaps a FullCalendar fetch range.
         *
         * @param {{ start: string|Date, end?: string|Date }} event
         * @param {{ start: Date, end: Date }} range
         * @return {boolean}
         */
        fullCalendarHelper_eventOverlapsFetchRange(event, range) {
            const eventStart = new Date(event.start).getTime();
            const rangeStart = range.start.getTime();
            const rangeEnd = range.end.getTime();

            if (!event.end) {
                return eventStart >= rangeStart && eventStart < rangeEnd;
            }

            return new Date(event.end).getTime() > rangeStart && eventStart < rangeEnd;
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

        /**
         * Update the icons in the calendar's header toolbar to match
         * @param {*} buttonClassName
         * @param {*} iconName
         * @returns void
         */
        fullCalendarHelper_replaceButtonIcon(buttonClassName, iconName) {
            const selector = buttonClassName.startsWith(".") ? buttonClassName : `.${buttonClassName}`;
            const rootEl = this && this.$el ? this.$el : document;
            const button = rootEl.querySelector ? rootEl.querySelector(selector) : document.querySelector(selector);
            if (!button) {
                return;
            }

            button.replaceChildren();

            const icon = document.createElement("sbx-icon");
            icon.setAttribute("name", iconName);
            icon.setAttribute("aria-hidden", "true");
            button.appendChild(icon);
        },
    },
};
