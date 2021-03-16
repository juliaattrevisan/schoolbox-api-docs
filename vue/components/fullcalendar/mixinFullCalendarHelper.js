import { toMoment }          from '@fullcalendar/moment';
import { ATTENDANCE_STATUS } from '@schoolboxCalendarEvent/attendance-status';

const EVENT_VARIANT_EVENT = 'event';
const EVENT_VARIANT_TASK  = 'task';

/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */
export const mixinFullCalendarHelper = {

    data () {
        return {
            $_fullCalendarHelper_eventTimeFormat: {
                hour: 'numeric',
                hour12: true,
                minute: '2-digit',
                meridiem: 'short',
                omitZeroMinute: true
            }
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

            const element = eventRenderInfo.el;
            const inner = element.querySelector('.fc-list-event');
            const title = element.querySelector('.fc-list-event-title');
            debugger

            if (calEvent?.extendedProps?.data?.attendance?.hasAttendance) {
                // Add a style depending on attendability
                element.classList.add('has-attendance');
                const attendanceStatus = calEvent?.extendedProps?.data?.attendance?.status?.current;
                if (attendanceStatus === ATTENDANCE_STATUS.DECLINED) {
                    element.classList.add('declined');
                } else if (attendanceStatus === ATTENDANCE_STATUS.PENDING) {
                    element.classList.add('pending');
                } else if (attendanceStatus === ATTENDANCE_STATUS.ACCEPTED) {
                    element.classList.add('accepted');
                }
            }
            
            // If this event is a task, alter the state of the checkbox accordingly.
            if (this.isTask(calEvent)) {
                
                const inputCheckbox = document.createElement('input');
                inputCheckbox.type = 'checkbox';
                inputCheckbox.name = 'completed';
                inputCheckbox.classList.add('plain');
                inputCheckbox.setAttribute('id', 't' + calEvent?.extendedProps?.data?.meta?.eventId);

                inner.insertBefore(inputCheckbox, inner.firstChild);

                // inner.prepend(
                //     this.jQuery('<input type="checkbox" name="completed" class="plain" />')
                //         .attr('id', 't' + calEvent?.extendedProps?.data?.meta?.eventId)
                // );
                if (Boolean(calEvent?.extendedProps?.data?.meta?.completed)) {
                    title.classList.add('strike');
                    // inner.find(':checkbox').prop('checked', true)
                    inputCheckbox.checked = true;
                }
            }

            // Apply the visible class?
            element.classList.add(calEvent?.extendedProps?.data?.styles?.visible);
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
        isTask (event) {
            return event?.extendedProps?.data?.meta?.variant === EVENT_VARIANT_TASK;
        },

        /**
         * Helper for rendering the title of the day view.
         * Without this the calendar renders the date twice for some reason.
         *
         * @param dateObject
         * @return {string}
         */
        $_fullCalendarHelper_dayTitleFormat (dateObject) {
            return this.$_fullCalendarHelper_toMoment(dateObject.date).format(this.config.dateDisplayFormats.momentJsDateLong);
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
         * callback.
         *
         * @return {number}
         */
        $_fullCalendarHelper_contentHeight() {

            const windowHeight = window.innerHeight;
            // The amount of space between the top of the window and the start of the calendar
            const calendarOffset = this.jQuery(this.$el).find('.fc-view-harness').offset().top;
            // <section> margin-bottom and #container padding-bottom
            const footerHeight = this.jQuery('#footer').outerHeight();
            // const footerHeight = document.getElementById('footer').offsetHeight;
            const otherPadding = 32;
            const contentHeight = windowHeight - calendarOffset - footerHeight - otherPadding;
            
            // debugger

            // Force a min height of 500px
            if (contentHeight < 500) {
                return 500;
            }
            return contentHeight;
        }

    }

}