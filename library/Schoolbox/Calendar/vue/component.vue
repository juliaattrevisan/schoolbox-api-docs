<script>
    import FullCalendar       from '@fullcalendar/vue'
    import ViewEventModal     from '@schoolbox/fullcalendar/view-event-modal';
    import ExportUrlModal     from './exportUrlModal';
    import interactionPlugin  from "@fullcalendar/interaction";
    import listPlugin         from "@fullcalendar/list";
    import momentPlugin       from "@fullcalendar/moment";
    import axios              from "axios";
    import FullCalendarHelper from "@schoolbox/fullcalendar/helper";
    import {ENDPOINTS}        from '@schoolboxCalendarEvent/endpoints';

    // TODO let display formats be configurable
    const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
    const FORMAT_DISPLAY_TIME = 'h:mma';
    const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';

    export default {
        components: {
            ViewEventModal,
            FullCalendar,
            ExportUrlModal,
        },
        created() {
            this.ENDPOINTS = ENDPOINTS;
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
                eventsLoaded: false,
                hasEvents: null,
            }
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
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
                    ENDPOINTS.URL_EVENT_SOURCE + '?returnUrl=' + this.returnUrl(),
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
                        // Add ability to limit events:
                        // need to filter, then sort, then slice
                        self.eventsLoaded = true;
                        let events = response.data
                            .filter(v => 'start' in v)
                            .sort((a, b) => String(a.start).localeCompare(String(b.start)));
                        if (self.config.limit) {
                            events = events.slice(0, self.config.limit);
                        }

                        // Empty? Don't show the calendar at all
                        if (events.length === 0) {
                            // self.$refs.emptyState.style.removeProperty('display');
                            // self.$refs.fullCalendar.getApi().destroy();
                            self.hasEvents = false;
                            if (!self.config.canCreateEvent && document.querySelector('#component'+self.config.componentId)) {
                                document.querySelector('#component'+self.config.componentId).setAttribute('class', 'hide');
                            }
                        } else {
                            self.hasEvents = true;
                        }
                        callback(events);
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
            :event-modify-form-url="ENDPOINTS.URL_EVENT_MODIFY_FORM"
            :event-modify-form-action-url="ENDPOINTS.URL_EVENT_MODIFY_FORM_ACTION"
            :event-delete-url="ENDPOINTS.URL_EVENT_DELETE"
            :event-update-url="ENDPOINTS.URL_EVENT_UPDATE"
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
            :no-events-message="null"
            @eventClick="onEventClick"
        />

        <!-- Loading state -->
        <div ref="loadingState" class="loader" v-if="!eventsLoaded">
        </div>

        <!-- Empty state: show only if events have loaded, but none are shown -->
        <div ref="emptyState" class="empty-state" v-if="eventsLoaded && !hasEvents && config.canCreateEvent">
            <!-- Different variants of empty state for dashboard (no folder ID) and homepage -->
            <template v-if="config.folderId">
                <p>
                    <i class="icon-calendar"></i>
                    <a :href="config.createEventUrl">Add an event</a>
                    or <a :href="config.importEventsUrl">import events in bulk</a>
                    to show in this component and in group members' calendars.
                </p>
            </template>
        </div>

        <!-- Component panel: show only for homepage variant, events are loaded and some are shown -->
        <div class="component-action" v-if="config.folderId && eventsLoaded && hasEvents">
            <section>
                <a v-if="config.canCreateEvent" :href="config.createEventUrl" class="button">Add Event</a>
                <a href="#" @click="$refs.exportModal.open" class="button">Export Events</a>
                <a v-if="config.canCreateEvent" :href="config.importEventsUrl" class="button">Import Events</a>
            </section>
        </div>


        <export-url-modal ref="exportModal" :url="config.exportUrl" />
    </div>
</template>

<style scoped>

</style>
