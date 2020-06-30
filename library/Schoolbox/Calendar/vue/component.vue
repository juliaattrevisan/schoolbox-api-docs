<script>
    import FullCalendar   from '@fullcalendar/vue'
    import ViewEventModal from '@schoolbox/fullcalendar/view-event-modal';
    import interactionPlugin from "@fullcalendar/interaction";
    import listPlugin from "@fullcalendar/list";
    import momentPlugin, {toDuration} from "@fullcalendar/moment";
    import axios from "axios";
    import FullCalendarHelper         from "@schoolbox/fullcalendar/helper";

    // Various URLs that FullCalendar may make use of.
    const URL_STATE_CHANGE = '/calendar/changeState';

    const URL_EVENT_SOURCE = '/calendar/ajax/full';
    const URL_EVENT_UPDATE = '/calendar/ajax/update';
    const URL_EVENT_FILTER = '/calendar/ajax/filter';
    const URL_EVENT_CREATE = '/calendar/ajax/create';

    /* TODO: Events should already know about and come with their own modify link;
     * we shouldn't be inferring and building it out here
     */
    const URL_EVENT_CREATE_FORM = '/calendar/event/create';
    const URL_EVENT_MODIFY_FORM = '/calendar/event/{eventId}/modify';
    const URL_EVENT_MODIFY_FORM_ACTION = '/calendar/modifyForm.php';
    const URL_EVENT_DELETE = '/calendar/event/{eventId}/delete';
    const URL_EVENT_EXPORT = '/calendar/export.php';

    const URL_ATTENDANCE_LIST = '/calendar/attendance/list/';
    const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';

    const FORMAT_FORM_DATE = 'DD-MM-YYYY';
    const FORMAT_FLATPICKR_DATE = 'DD/MM/YYYY';
    const FORMAT_FORM_DATETIME = 'DD-MM-YYYY HH:mm';

    // TODO let display formats be configurable
    const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
    const FORMAT_DISPLAY_TIME = 'h:mma';
    const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';
    const FORMAT_DISPLAY_WEEK_DAY = 'ddd D/M';

    export default {
        components: {
            ViewEventModal,
            FullCalendar,
        },
        beforeMount() {
            this.helper = new FullCalendarHelper(this, this.config, this.jQuery);
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, listPlugin, momentPlugin ],
                currentDisplay: null,
                duration: 31,
                listViewDateRange: {
                    start: null,
                    end: null,
                },
            }
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
            urls() {
                return {
                    createForm: URL_EVENT_CREATE_FORM,
                    modifyForm: URL_EVENT_MODIFY_FORM,
                    modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
                    create: URL_EVENT_CREATE,
                    update: URL_EVENT_UPDATE,
                    delete: URL_EVENT_DELETE,
                    export: URL_EVENT_EXPORT,
                    attendanceList: URL_ATTENDANCE_LIST,
                    attendanceToggle: URL_ATTENDANCE_TOGGLE,
                };
            },
            formats() {
                return {
                    formDate: FORMAT_FORM_DATE,
                    formDatetime: FORMAT_FORM_DATETIME,
                    displayDate: FORMAT_DISPLAY_DATE,
                    displayTime: FORMAT_DISPLAY_TIME,
                    displayDatetime: FORMAT_DISPLAY_DATETIME,
                    displayWeekDay: FORMAT_DISPLAY_WEEK_DAY,
                };
            },
        },
        methods: {
            /**
             * Event loading callback: show a spinner whilst events are loading,
             * remove the spinner once they have finished.
             * @see https://fullcalendar.io/docs/loading
             *
             * @param isLoading True when events start loading,
             *                  false once they are finished.
             */
            onLoading(isLoading) {
                // TODO this method should be unnecessary
                // I should just be able to reference this in the `<full-calendar>`
                // tag as `@loading="helper.onLoading"`, but `this` within that
                // call is null.
                // Even `@loading="helper.onLoading.bind(helper)"` fails, the method
                // doesn't get called at all.
                this.helper.onLoading(isLoading);
            },

            contentHeight() {
                return this.helper.contentHeight();
            },

            /**
             * Event source.
             * @see https://fullcalendar.io/docs/events-function
             *
             * @param fetchInfo The time span of the events to fetch
             * @param callback  A function to call with the list of events.
             */
            events(fetchInfo, callback) {
                var self = this;
                // this will have to have a folder ID attached
                axios.get(
                    URL_EVENT_SOURCE,
                    {
                        headers: {
                            'X-Requested-With' : 'XMLHttpRequest'
                        },
                        params: {
                            start:  Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                            end:    Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                            userId: self.config.userId,
                        }
                    }
                ).then(
                    function (response) {
                        callback(response.data.filter(function (v) {
                            return 'start' in v;
                        }));
                    }
                );
                // Fail callback: consider a message saying something failed
            },

            /**
             * Event render callback: manually tweaks the event's rendering, and
             * can also conditionally show/hide the event.
             * @see https://fullcalendar.io/docs/eventRender
             *
             * @param eventRenderInfo
             * @return {boolean}
             */
            eventRender(eventRenderInfo) {
                if (!this.helper.eventRender(eventRenderInfo)) {
                    return false;
                }
                return true;
            },

            /**
             * Click an event on the calendar callback: opens the view event
             * modal for the event.
             * @see https://fullcalendar.io/docs/eventClick
             *
             * @param eventInfo
             */
            onEventClick(eventInfo) {
                // Ideally the current event would be a part of the modal,
                // not this component, but :shrug:
                this.$refs.event.open(eventInfo.event);
            },

            /**
             * Helper function for converting a Date object to a Moment object.
             * @see https://fullcalendar.io/docs/moment-plugins
             *
             * @param date
             */
            toMoment(date) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading`)
                return this.helper.toMoment(date);
            },

            /**
             * Get the current url; these redirects are used after backend processing
             * so you can return to your current location afterwards
             *
             * Use the base64encoded version of the url's pathname, so it can work in
             * conjunection with Class Redirect in the back end
             */
            returnUrl() {
                return btoa(window.location.pathname);
            },

            /**
             * Callback for adding an event to the calendar.
             * @see https://fullcalendar.io/docs/Calendar-addEvent
             *
             * @param event
             */
            addEvent(event) {
                this.$refs.fullCalendar.getApi().addEvent(event);
            },

            /**
             * Check if current display is list
             */
            currentViewIsList() {
                return this.currentDisplay === 'list';
            },

            /**
             * Callback for reloading the calendar's events. May be used when
             * a series of events is deleted, as the calendar no longer knows
             * whether an event should be rendered even if it wasn't deleted.
             * @see https://fullcalendar.io/docs/Calendar-refetchEvents
             */
            reloadEvents() {
                this.$refs.fullCalendar.getApi().refetchEvents();
            },
        },
    }
</script>

<template>
    <div>
        <view-event-modal
            ref="event"
            :to-moment="toMoment"
            :event-modify-form-url="urls.modifyForm"
            :event-modify-form-action-url="urls.modifyFormAction"
            :event-delete-url="urls.delete"
            :event-update-url="urls.update"
            :attendance-list-url="urls.attendanceList"
            :attendance-toggle-url="urls.attendanceToggle"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
            :return-url="returnUrl()"
            @eventModified="reloadEvents"
            @eventDeleted="reloadEvents"
        />

        <full-calendar
            ref="fullCalendar"
            :plugins="calendarPlugins"
            :views="{
                list: {
                    type: 'list',
                    duration: { days: config.days },
                }
            }"
            :header="false"
            :event-time-format="helper.eventTimeFormat"
            :content-height="this.contentHeight"
            default-view="list"
            :events="events"
            :event-render="eventRender"
            @loading="onLoading"
            @eventClick="onEventClick"
        />
    </div>
</template>

<style scoped>

</style>
