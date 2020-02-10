<script>

    import FullCalendar               from '@fullcalendar/vue'
    import interactionPlugin          from '@fullcalendar/interaction'
    import dayGridPlugin              from '@fullcalendar/daygrid'
    import timeGridPlugin             from '@fullcalendar/timegrid'
    import listPlugin                 from '@fullcalendar/list'
    import momentPlugin, {toDuration} from '@fullcalendar/moment'
    import RevealModal                from '@schoolbox/reveal-modal'
    import MetaList                   from '@schoolbox/meta/list'
    import MetaItem                   from '@schoolbox/meta/item'
    import Toast                      from "@schoolbox/toast";
    import ViewEventModal             from '@schoolbox/fullcalendar/view-event-modal';
    import CreateBookingModal         from '@schoolbox/fullcalendar/resourceBooking/createBookingModal';
    import ExportModal                from '@schoolbox/fullcalendar/resourceBooking/exportBookingModal';
    import FilterModal                from '@schoolbox/fullcalendar/resourceBooking/filterModal';
    import axios                      from 'axios'
    import FullCalendarHelper         from "@schoolbox/fullcalendar/helper";

    // Various URLs that FullCalendar may make use of.
    const URL_STATE_CHANGE = '/calendar/changeState';

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
            Toast,
            FilterModal,
            CreateBookingModal,
            ExportModal,
            ViewEventModal,
            MetaItem,
            MetaList,
            FullCalendar,
            RevealModal,
        },
        beforeMount() {
            this.helper = new FullCalendarHelper(this, this.config, this.jQuery);
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, momentPlugin ],
            }
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
            urls() {
                return {
                    export: URL_EVENT_EXPORT,
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
            contentHeight() {
                return this.helper.contentHeight();
            },

            /**
             * Open the export modal.
             */
            onExportClick() {
                this.$refs.export.open();
            },

            /**
             * Open the filter modal.
             */
            onFilterClick() {
                this.$refs.filter.open();
            },

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

            /**
             * Event source.
             * @see https://fullcalendar.io/docs/events-function
             *
             * @param fetchInfo The time span of the events to fetch
             * @param callback  A function to call with the list of events.
             */
            events(fetchInfo, callback) {
                var self = this;
                axios.get(
                    self.config.resourceBookingEndpoint,
                    {
                        headers: {
                            'X-Requested-With' : 'XMLHttpRequest'
                        },
                        params: {
                            start:  Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                            end:    Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                            status: this.config.selectedFilters,
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
             * @param event
             * @return {boolean}
             */
            eventRender(event) {
                if (!this.helper.eventRender(event)) {
                    return false;
                }

                let bookingStatus = event.event.extendedProps.data.booking?.status,
                    element = event.el;

                // Add booking styles
                if (bookingStatus?.isRejected) {
                    element.classList.add('rejected');
                } else if (bookingStatus?.isApproved) {
                    element.classList.add('approved');
                } else if (bookingStatus?.isPending) {
                    element.classList.add('pending');
                }

                return true;
            },

            /**
             * Click a date on the calendar callback: opens the create event
             * modal for the clicked date.
             * @see https://fullcalendar.io/docs/dateClick
             *
             * @param dateClickInfo
             */
            onDateClick(dateClickInfo) {
                // Just a proposal.
                // If the user doesn't have the rights to create a booking, just don't show the modal.
                if (this.config.bookingIsAuthorized) {
                    this.$refs.createBooking.open({
                        date: dateClickInfo.date,
                        allDay: dateClickInfo.allDay,
                    });
                }
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
             * Callback for adding an event to the calendar.
             * @see https://fullcalendar.io/docs/Calendar-addEvent
             *
             * @param event
             */
            addEvent(event) {
                this.$refs.fullCalendar.getApi().addEvent(event);
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
             * Helper for rendering the title of the day view.
             * Without this the calendar renders the date twice for some reason.
             *
             * @param dateObject
             * @return {*}
             */
            dayTitleFormat(dateObject) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading`)
                return this.helper.dayTitleFormat(dateObject);
            },
        }
    }

</script>

<template>
    <div>
        <export-modal ref="export"
                      :token="config.token"
                      :booking-export-url="urls.export"
                      :resource-id="config.resourceId"
                      :booking-type="'item'"
        />

        <filter-modal ref="filter"
                      :selected-filters="config.selectedFilters"
                      @filterChanged="reloadEvents" />

        <create-booking-modal ref="createBooking"
                              :to-moment="toMoment"
                              :booking-path="config.createBookingPath"
                              :date-formats="formats"
                              :booking-type="'item'"
        />

        <view-event-modal ref="event"
                          :task-type-id="config.taskTypeId"
                          :to-moment="toMoment"
                          :event-modify-form-url="urls.modifyForm"
                          :event-modify-form-action-url="urls.modifyFormAction"
                          :event-update-url="urls.update"
                          :format-display-date="formats.displayDate"
                          :format-display-time="formats.displayTime"
                          :format-display-datetime="formats.displayDatetime"
                          @eventModified="reloadEvents"
                          @eventDeleted="reloadEvents" />

        <full-calendar
                ref="fullCalendar"
                :plugins="calendarPlugins"
                :views="{
                    day: {
                        titleFormat: dayTitleFormat,
                        columnHeader: false,
                        allDaySlot: false,
                    },
                    week: {
                        columnHeaderFormat: formats.displayWeekDay,
                        allDaySlot: false,
                    }
                }"
                :event-time-format="helper.eventTimeFormat"
                :content-height="this.contentHeight"
                :custom-buttons="{
                    customExport: {
                        text: 'Export',
                        click: onExportClick
                    },
                    customFilters: {
                        text: 'Filters',
                        click: onFilterClick
                    },
                }"
                :buttonText="{
                    today:    'Today',
                    month:    'Month',
                    week:     'Week',
                    day:      'Day'
                }"
                :header="{
                    left: 'prev,today,next',
                    center: 'title',
                    right: 'customFilters,dayGridMonth,timeGridWeek,timeGridDay,customExport'
                }"
                :default-date="config.defaultDate"
                :default-view="config.defaultView || 'dayGridMonth'"
                :scroll-time="config.dateDisplayFormats.calendarStartTime"
                :event-limit="config.eventLimit"
                :first-day="config.firstDay"
                :events="events"
                :event-render="eventRender"
                @loading="onLoading"
                @dateClick="onDateClick"
                @eventClick="onEventClick"
        />

        <toast ref="toast"></toast>
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
</style>
