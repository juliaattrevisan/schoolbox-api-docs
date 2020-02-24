<script>

    import FullCalendar               from '@fullcalendar/vue'
    import interactionPlugin          from '@fullcalendar/interaction'
    import momentPlugin, {toDuration} from '@fullcalendar/moment'
    import RevealModal                from '@schoolbox/reveal-modal'
    import MetaList                   from '@schoolbox/meta/list'
    import MetaItem                   from '@schoolbox/meta/item'
    import Toast                      from "@schoolbox/toast";
    import ViewEventModal             from '@schoolbox/fullcalendar/view-event-modal';
    import CreateBookingModal         from '@schoolbox/fullcalendar/resourceBooking/createBookingModal';
    import ExportModal                from '@schoolbox/fullcalendar/resourceBooking/exportBookingModal';
    import FilterModal                from '@schoolbox/fullcalendar/resourceBooking/filterModal';
    import FlatPickr                  from '@schoolbox/fullcalendar/flatPickr';
    import axios                      from 'axios'
    import FullCalendarHelper         from "@schoolbox/fullcalendar/helper";
    import resourceTimelinePlugin     from '@fullcalendar/resource-timeline';

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
            FlatPickr,
        },
        beforeMount() {
            this.helper = new FullCalendarHelper(this, this.config, this.jQuery);
        },
        data() {
            return {
                calendarPlugins: [ interactionPlugin, momentPlugin, resourceTimelinePlugin ],
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
             * @param eventRenderInfo
             * @return {boolean}
             */
            eventRender(eventRenderInfo) {
                if (!this.helper.eventRender(eventRenderInfo)) {
                    return false;
                }

                let bookingStatus = eventRenderInfo.event.extendedProps.data.booking?.status,
                    isEventRendered = true;

                if (bookingStatus?.isRejected) {
                    eventRenderInfo.el.classList.add('rejected');
                } else if (bookingStatus?.isApproved) {
                    eventRenderInfo.el.classList.add('approved');
                } else if (bookingStatus?.isPending) {
                    eventRenderInfo.el.classList.add('pending');
                }

                return isEventRendered;
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
                if (dateClickInfo.resource.extendedProps.bookingIsAuthorized) {
                    this.$refs.createBooking.open({
                        date: dateClickInfo.date,
                        allDay: dateClickInfo.allDay,
                        name: dateClickInfo.resource.extendedProps.name,
                        id: dateClickInfo.resource.id,
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

            /**
             * Additional customisation for prev button to sync flatpickr's selected date with fullCalendar's
             */
            clickPrev() {
                let api = this.$refs.fullCalendar.getApi();
                api.prev();
                this.$refs.flatPickr.updateActiveDate(api.getDate());
            },

            /**
             * Additional customisation for next button to sync flatpickr's selected date with fullCalendar's
             */
            clickNext() {
                let api = this.$refs.fullCalendar.getApi();
                api.next();
                this.$refs.flatPickr.updateActiveDate(api.getDate());
            },

            /**
             * Additional customisation for today's button to sync flatpickr's selected date with fullCalendar's
             */
            clickToday() {
                let api = this.$refs.fullCalendar.getApi();
                api.today();
                this.$refs.flatPickr.updateActiveDate(api.getDate());
            },

            /**
             * Additional customisation for quick month switch button.
             * When the user clicks on the flatpickr's date, we'll trigger fullcalendar's gotoDate.
             */
            onQuickSwitch(value) {
                this.$refs.fullCalendar.getApi().gotoDate(value.selectedDate);
            },
        },
        mounted() {
            // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
            let quickSwitchIcon = document.createElement('a');
            quickSwitchIcon.setAttribute('class', 'adtp-btn icon-calendar no-text');
            quickSwitchIcon.innerHTML += '<span class="splash animate"></span>';

            document.getElementsByClassName('fc-quickSwitchButton-button')[0].appendChild(quickSwitchIcon);
        }
    }

</script>

<template>
    <div>
        <export-modal ref="export"
                      :token="config.token"
                      :booking-export-url="urls.export"
                      :resource-id="config.resourceId"
                      :booking-type="'category'"
        />

        <filter-modal ref="filter"
                      :selected-filters="config.selectedFilters"
                      @filterChanged="reloadEvents" />

        <create-booking-modal ref="createBooking"
                              :to-moment="toMoment"
                              :booking-path="config.createBookingPath"
                              :date-formats="formats"
                              :booking-type="'category'"
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
                    resourceTimelineMonth: {
                        slotLabelFormat: ['ddd D']
                    },
                    resourceTimelineWeek: {
                        slotLabelFormat: ['ddd D/M', 'h'],
                        slotWidth: '50'
                    },
                    resourceTimelineDay: {
                        slotWidth: '50'
                    }
                }"
                :event-time-format="helper.eventTimeFormat"
                :content-height="this.contentHeight"
                :height="'auto'"
                :custom-buttons="{
                    customExport: {
                        text: 'Export',
                        click: onExportClick
                    },
                    customFilters: {
                        text: 'Filters',
                        click: onFilterClick
                    },
                    quickSwitchButton: {
                        text: ' ',
                    },
                    customPrev: {
                        icon: 'chevron-left',
                        click: this.clickPrev.bind(this),
                    },
                    customNext: {
                        icon: 'chevron-right',
                        click: this.clickNext.bind(this),
                    },
                    customToday: {
                        text: 'Today',
                        click: this.clickToday.bind(this),
                    },
                }"
                :buttonText="{
                    today:    'Today',
                    month:    'Month',
                    week:     'Week',
                    day:      'Day'
                }"
                :header="{
                    left: 'quickSwitchButton,customToday,customPrev,customNext',
                    center: 'title',
                    right: 'customFilters,resourceTimelineMonth,resourceTimelineWeek,resourceTimelineDay,customExport'
                }"
                :resources="config.resources"
                :resourceColumns="config.resourceColumns"
                :schedulerLicenseKey="config.schedulerLicenseKey"
                :default-date="config.defaultDate"
                :default-view="config.defaultView"
                :scroll-time="config.dateDisplayFormats.calendarStartTime"
                :event-limit="config.eventLimit"
                :first-day="config.firstDay"
                :events="events"
                :event-render="eventRender"
                :all-day-slot="'false'"
                :default-timed-event-duration="'00:05:00'"
                @loading="onLoading"
                @dateClick="onDateClick"
                @eventClick="onEventClick"
        />

        <flat-pickr
                ref="flatPickr"
                :config="{
                    defaultDate: config.defaultDate,
                    selector: '.fc-quickSwitchButton-button'
                }"
                @dateSelected="onQuickSwitch"
        />

        <toast ref="toast"></toast>
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/timeline/main.css';
    @import '~@fullcalendar/resource-timeline/main.css';
</style>
