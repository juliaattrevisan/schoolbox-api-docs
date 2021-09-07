<script>

import FullCalendar       from '@fullcalendar/vue'
import ViewEventModal     from '@vue/components/fullcalendar/view-event-modal';
import ExportUrlModal     from './export-url-modal';
import interactionPlugin  from "@fullcalendar/interaction";
import listPlugin         from "@fullcalendar/list";
import momentPlugin       from "@fullcalendar/moment";
import adaptivePlugin     from "@fullcalendar/adaptive";
import axios              from "axios";
import { mixinFullCalendarHelper } from '@vue/components/fullcalendar/mixinFullCalendarHelper';
import {ENDPOINTS}        from '@schoolboxCalendarEvent/endpoints';
import CustomisedCalendarEvent	  from '@vue/components/fullcalendar/customised-calendar-event';
import moment              from 'moment';

// TODO let display formats be configurable
const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
const FORMAT_DISPLAY_TIME = 'h:mma';
const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';

export default {

    name: 'calendar-component',

    components: {
        ViewEventModal,
        FullCalendar,
        ExportUrlModal,
        CustomisedCalendarEvent
    },

    mixins: [
        mixinFullCalendarHelper
    ],

    data () {
        return {
            calendarApi: null, //to access the underlying calendar object
            calendarOptions: {}, //will init in beforeMount due to vars being needed
            currentDisplay: null,
            duration: 31,
            listViewDateRange: {
                start: null,
                end: null,
            },
            eventsLoaded: false,
            hasEvents: null,
            hasHeadingDisplayUpdated: false, //to ensure it doesn't fire multiple times
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

    created () {
        this.ENDPOINTS = ENDPOINTS;
    },

    beforeMount () {
        this.calendarOptions = {
            schedulerLicenseKey: this.config.schedulerLicenseKey,
            plugins: [ interactionPlugin, listPlugin, momentPlugin, adaptivePlugin ],
            views: {
                list: {
                    type: 'list',
                    duration: { days: this.config.days },
                    listDayFormat: this.config.momentJsDateLong,
                    listDaySideFormat: false
                }
            },
            headerToolbar: false,
            eventTimeFormat: this.fullCalendarHelper_eventTimeFormat,
            height: 'auto',
            initialView: 'list',
            events: this.events,
            eventDidMount: this.$_fullCalendarHelper_eventRender,
            noEventsDidMount: null, //hide the no events message
            eventClick: this.onEventClick,
            loading: this.onLoading,
        }
    },

    mounted () {
        this.$nextTick()
        .then(() => {
            this.calendarApi = this.$refs.fullCalendar.getApi();
        });
    },

    methods: {

        /**
         * To check whether the heading needs to be updated on the homepage dashboard
         */
        onLoading (isLoading) {
            if (!isLoading && this.config.isDashboard && !this.hasHeadingDisplayUpdated) {
                this.updateHeadingDisplay();
            }
        },

        /**
         * To add the <h2 class="subheader"> and remove the lists own date display
         */
        updateHeadingDisplay () {

            this.hasHeadingDisplayUpdated = true; //so it's not added multiple times...

            const headingElement = document.createElement('h2');
            headingElement.classList.add('subheader');
            headingElement.innerText = moment(new Date()).format(this.config.momentJsDateLong);

            /**
             * For example:
             * <div class="small-12 island">
             *      <h2 class="subheader">
             *      <section>
             *          <div id="calendar">
             *              <calendar-component />
             *          </div>
             *      </section>
             *  </div>
             */
            const containerElement = this.$el.parentElement.parentElement.parentElement;
            const sectionElement = this.$el.parentElement.parentElement;
            containerElement.insertBefore(headingElement, sectionElement);

            if (this.hasEvents) { //won't be available if there's no events to display
                this.$nextTick().then(() => { //pull out the built in date display
                    if (this.$el.querySelector('[data-date]').getAttribute('data-date') === moment(new Date()).format('YYYY-MM-DD')) {
                        this.$el.querySelector('[data-date]').style.display = 'none';
                    }
                });
            }

        },

        /**
         * Event source.
         * @see https://fullcalendar.io/docs/events-function
         *
         * @param fetchInfo The time span of the events to fetch
         * @param callback  A function to call with the list of events.
         */
        events (fetchInfo, callback) {
            axios.get(
                ENDPOINTS.URL_EVENT_SOURCE,
                {
                    headers: {
                        'X-Requested-With' : 'XMLHttpRequest'
                    },
                    params: {
                        start:    Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                        end:      Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                        userId:   this.config.userId,
                        folderId: this.config.folderId,
                    }
                }
            ).then((response) => {
                // Add ability to limit events:
                // need to filter, then sort, then slice
                this.eventsLoaded = true;
                let events = response.data
                    .filter(v => 'start' in v)
                    .sort((a, b) => String(a.start).localeCompare(String(b.start)));
                if (this.config.limit) {
                    events = events.slice(0, this.config.limit);
                }

                // Empty? Don't show the calendar at all
                if (events.length === 0) {
                    // self.$refs.emptyState.style.removeProperty('display');
                    // self.$refs.fullCalendar.getApi().destroy();
                    this.hasEvents = false;
                } else {
                    this.hasEvents = true;
                }
                callback(events);
            } );
            // Fail callback: consider a message saying something failed
        },

        /**
         * Click an event on the calendar callback: opens the view event
         * modal for the event.
         * @see https://fullcalendar.io/docs/eventClick
         *
         * @param eventInfo
         */
        onEventClick (eventInfo) {
            // Ideally the current event would be a part of the modal,
            // not this component, but :shrug:
            this.$refs.event.open(eventInfo.event);
        },

        /**
         * Get the current url; these redirects are used after backend processing
         * so you can return to your current location afterwards
         *
         * Use the base64encoded version of the url's pathname, so it can work in
         * conjunection with Class Redirect in the back end
         */
        returnUrl () {
            return btoa(window.location.pathname);
        },

        /**
         * Callback for reloading the calendar's events. May be used when
         * a series of events is deleted, as the calendar no longer knows
         * whether an event should be rendered even if it wasn't deleted.
         * @see https://fullcalendar.io/docs/Calendar-refetchEvents
         */
        reloadEvents () {
            this.calendarApi.refetchEvents();
        },

    },

}

</script>

<template>
    <div>
        <view-event-modal
            ref="event"
            :to-moment="$_fullCalendarHelper_toMoment"
            :event-modify-form-url="ENDPOINTS.URL_EVENT_MODIFY_FORM"
            :event-modify-form-action-url="ENDPOINTS.URL_EVENT_MODIFY_FORM_ACTION"
            :event-delete-url="ENDPOINTS.URL_EVENT_DELETE"
            :event-update-url="ENDPOINTS.URL_EVENT_UPDATE"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
            :return-url="returnUrl"
            @eventModified="reloadEvents"
            @eventDeleted="reloadEvents"
        />

        <full-calendar
            ref="fullCalendar"
            :options="calendarOptions"
        >
            <template v-slot:eventContent="arg">
                <customised-calendar-event :content="arg" />
            </template>
        </full-calendar>

        <!-- Loading state -->
        <div ref="loadingState" class="loader" v-if="!eventsLoaded">
        </div>

        <!-- Empty state: show only if events have loaded, but none are shown -->
        <div ref="emptyState" class="empty-state" v-if="eventsLoaded && !hasEvents">
            <!-- Different variants of empty state for dashboard (no folder ID) and homepage -->
            <template v-if="config.folderId">
                <i class="icon-calendar"></i>
                <p v-if="config.canCreateEvent">
                    <a :href="config.createEventUrl">Add an event</a>
                    or <a :href="config.importEventsUrl">import events in bulk</a>
                    to show in this component and in group members' calendars.
                </p>
                <p v-else>
                    There are no upcoming events to display at this time.
                </p>
            </template>
            <span v-else>
                <i class="icon-calendar"></i>
                <p>There are no upcoming calendar events</p>
            </span>
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

<style lang="scss">
@import "@vue/components/fullcalendar/styles/calendar";
</style>
