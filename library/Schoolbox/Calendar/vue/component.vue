<script>
    import FullCalendar       from '@fullcalendar/vue'
    import ViewEventModal     from '@schoolbox/fullcalendar/view-event-modal';
    import interactionPlugin  from "@fullcalendar/interaction";
    import listPlugin         from "@fullcalendar/list";
    import momentPlugin       from "@fullcalendar/moment";
    import axios              from "axios";
    import FullCalendarHelper from "@schoolbox/fullcalendar/helper";

    // Various URLs that FullCalendar may make use of.
    const URL_EVENT_SOURCE = '/calendar/ajax/full';
    const URL_EVENT_UPDATE = '/calendar/ajax/update';

    /* TODO: Events should already know about and come with their own modify link;
     * we shouldn't be inferring and building it out here
     */
    const URL_EVENT_MODIFY_FORM = '/calendar/event/{eventId}/modify';
    const URL_EVENT_MODIFY_FORM_ACTION = '/calendar/modifyForm.php';
    const URL_EVENT_DELETE = '/calendar/event/{eventId}/delete';
    const URL_EVENT_EXPORT = '/calendar/export.php';

    const URL_ATTENDANCE_LIST = '/calendar/attendance/list/';
    const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';

    // TODO let display formats be configurable
    const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
    const FORMAT_DISPLAY_TIME = 'h:mma';
    const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';

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
                    modifyForm: URL_EVENT_MODIFY_FORM,
                    modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
                    update: URL_EVENT_UPDATE,
                    delete: URL_EVENT_DELETE,
                    attendanceList: URL_ATTENDANCE_LIST,
                    attendanceToggle: URL_ATTENDANCE_TOGGLE,
                };
            },
            formats() {
                return {
                    displayDate: FORMAT_DISPLAY_DATE,
                    displayTime: FORMAT_DISPLAY_TIME,
                    displayDatetime: FORMAT_DISPLAY_DATETIME,
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
                // TODO replace with a more component-appropriate display
                this.helper.onLoading(isLoading);
            },

            /**
             * Callback for determining content height of the component.
             */
            contentHeight() {
                // TODO figure out a more appropriate calculation
                // (This is the same calculation the full calendar uses)
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
                let self = this;
                axios.get(
                    URL_EVENT_SOURCE,
                    {
                        headers: {
                            'X-Requested-With' : 'XMLHttpRequest'
                        },
                        params: {
                            start:    Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                            end:      Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                            userId:   self.config.userId,
                            folderId: self.config.folderId,
                        }
                    }
                ).then(
                    function (response) {
                        callback(response.data.filter(function (v) {
                            return 'start' in v;
                        }));
                    }
                ).finally(
                    function () {
                        // Update the no events message now we're no longer loading events
                        // (This currently does double-duty as the empty state)
                        self.$refs.fullCalendar.getApi().setOption(
                            'noEventsMessage',
                            self.helper.dayTitleFormat(fetchInfo.start) + ': No upcoming events'
                        );
                    }
                )
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
                return this.helper.eventRender(eventRenderInfo);
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
             * Callback for reloading the calendar's events. May be used when
             * a series of events is deleted, as the calendar no longer knows
             * whether an event should be rendered even if it wasn't deleted.
             * @see https://fullcalendar.io/docs/Calendar-refetchEvents
             */
            reloadEvents() {
                this.$refs.fullCalendar.getApi().refetchEvents();
            },

            /**
             * Helper for rendering the list day.
             *
             * @param dateObject
             * @returns {string}
             */
            listDayFormat(dateObject) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading`)
                return this.helper.dayTitleFormat(dateObject);
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
                    listDayFormat: listDayFormat,
                    listDayAltFormat: false,
                }
            }"
            :header="false"
            :event-time-format="helper.eventTimeFormat"
            height="auto"
            default-view="list"
            :events="events"
            :event-render="eventRender"
            no-events-message="Loading events..."
            @loading="onLoading"
            @eventClick="onEventClick"
        />
    </div>
</template>

<style scoped>

</style>
