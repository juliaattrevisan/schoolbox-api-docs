<template>
    <div>
        <view-event-modal ref="event"
                          :to-moment="toMoment"
                          :format-display-date="formats.displayDate"
                          :format-display-time="formats.displayTime"
                          :format-display-datetime="formats.displayDatetime"
        />

        <full-calendar
                ref="fullCalendar"
                :plugins="calendarPlugins"
                :views="{
                    day: {
                        titleFormat: dayTitleFormat,
                        columnHeader: false
                    },
                    week: {
                        columnHeaderFormat: formats.displayWeekDay
                    }
                }"
                :event-time-format="helper.eventTimeFormat"
                content-height="auto"
                :header="{
                    left: 'today,prev,next',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                }"
                :buttonText="{
                    today:    'Today',
                    month:    'Month',
                    week:     'Week',
                    day:      'Day'
                }"
                default-view="dayGridMonth"
                :event-render="eventRender"
                :events="events"
                @loading="onLoading"
                @eventClick="onEventClick"
        />
    </div>
</template>

<script>
    import FullCalendar       from '@fullcalendar/vue'
    import interactionPlugin  from '@fullcalendar/interaction'
    import dayGridPlugin      from '@fullcalendar/daygrid'
    import timeGridPlugin     from '@fullcalendar/timegrid'
    import listPlugin         from '@fullcalendar/list'
    import momentPlugin       from '@fullcalendar/moment'
    import RevealModal        from '@schoolbox/reveal-modal'
    import ViewEventModal     from '@schoolbox/fullcalendar/view-event-modal';
    import axios              from 'axios'
    import FullCalendarHelper from "@schoolbox/fullcalendar/helper";
    import {stringify}        from 'qs';

    // Various URLs that FullCalendar may make use of.
    const URL_EVENT_SOURCE = '/learning/due/events';
    const URL_EVENT_EXPORT = '/calendar/export.php';

    const FORMAT_FORM_DATE = 'DD-MM-YYYY';
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
            RevealModal,
        },
        beforeMount() {
            this.helper = new FullCalendarHelper(this, this.config, this.jQuery);
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, momentPlugin ],
                selectedFilters: null,
            }
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
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
                // (for the same reason as `onLoading` in the main fullCalendar)
                this.helper.onLoading(isLoading);
            },
            /**
             * Helper for rendering the title of the day view.
             * Without this the calendar renders the date twice for some reason.
             *
             * @param dateObject
             * @return {*}
             */
            dayTitleFormat(dateObject) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading` in the main fullCalendar)
                return this.helper.dayTitleFormat(dateObject);
            },

            /**
             * Helper function for converting a Date object to a Moment object.
             * @see https://fullcalendar.io/docs/moment-plugins
             *
             * @param date
             */
            toMoment(date) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading` in the main fullCalendar)
                return this.helper.toMoment(date);
            },

            /**
             * Event source.
             * @see https://fullcalendar.io/docs/events-function
             *
             * @param fetchInfo       The time span of the events to fetch
             * @param successCallback A function to call with the list of events.
             */
            events(fetchInfo, successCallback) {
                if (this.selectedFilters === null) {
                    successCallback([]);
                    return;
                }
                axios.get(
                    URL_EVENT_SOURCE + '/' + window.fullCalendarConfig.userId,
                    {
                        headers: {
                            'X-Requested-With' : 'XMLHttpRequest'
                        },
                        params: {
                            start: Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                            end:   Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                            filters: this.selectedFilters,
                        },
                        paramsSerializer: params => stringify(params, {arrayFormat: 'brackets'}),
                    }
                ).then(
                    function (response) {
                        successCallback(response.data.filter(function (v) {
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
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading` in the main fullCalendar)
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
                this.$refs.event.open(eventInfo.event);
            },

            /**
             * Callback for when the filters for this calendar are updated.
             *
             * Fired from outer space (i.e. from the outside window).
             *
             * @param {object} selectedFilters The filters that were selected
             */
            onFilterChange(selectedFilters) {
                this.selectedFilters = selectedFilters;
                this.$refs.fullCalendar.getApi().refetchEvents();
            },
        }
    }
</script>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
</style>
