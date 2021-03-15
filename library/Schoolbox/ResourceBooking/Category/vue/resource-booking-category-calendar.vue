<script>

import FullCalendar               from '@fullcalendar/vue'
import interactionPlugin          from '@fullcalendar/interaction'
import momentPlugin, {toDuration} from '@fullcalendar/moment'
import ViewEventModal             from '@schoolbox/fullcalendar/view-event-modal';
import CreateBookingModal         from '@schoolbox/fullcalendar/resourceBooking/createBookingModal';
import ExportModal                from '@schoolbox/fullcalendar/resourceBooking/exportBookingModal';
import FilterModal                from '@schoolbox/fullcalendar/resourceBooking/filterModal';
import FlatPickr                  from '@schoolbox/fullcalendar/flatPickr';
import axios                      from 'axios'
// import FullCalendarHelper         from "@schoolbox/fullcalendar/helper";
import { mixinFullCalendarHelper } from '@schoolbox/fullcalendar/mixinFullCalendarHelper';
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
        FilterModal,
        CreateBookingModal,
        ExportModal,
        ViewEventModal,
        FullCalendar,
        FlatPickr,
    },

    mixins: [
        mixinFullCalendarHelper
    ],
    
    data () {
        return {
            calendarOptions: {} //will init in beforeMount due to vars being needed,
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

    beforeMount () {
        this.calendarOptions = {
            plugins: [ interactionPlugin, momentPlugin, resourceTimelinePlugin ],
            views: {
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
            },
            eventTimeFormat: this.helper.eventTimeFormat,
            // contentHeight: this.contentHeight,
            customButtons: {
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
                    click: this.clickPrev,
                },
                customNext: {
                    icon: 'chevron-right',
                    click: this.clickNext,
                },
                customToday: {
                    text: 'Today',
                    click: this.clickToday,
                },
            },
            buttonText: {
                today:    'Today',
                month:    'Month',
                week:     'Week',
                day:      'Day'
            },
            headerToolbar: {
                left: 'quickSwitchButton,customToday,customPrev,customNext',
                center: 'title',
                right: 'customFilters,resourceTimelineMonth,resourceTimelineWeek,resourceTimelineDay,customExport'
            },
            resources: this.config.resources,
            resourceOrder: this.resourceOrder,
            resourceColumns: this.config.resourceColumns,
            schedulerLicenseKey: this.config.schedulerLicenseKey,
            initialDate: this.config.defaultDate,
            initialView: this.config.defaultView,
            scrollTime: this.config.dateDisplayFormats.calendarStartTime,
            eventLimit: this.config.eventLimit,
            firstDay: this.config.firstDay,
            events: this.events,
            eventDidMount: this.$_fullCalendarHelper_eventRender,
            allDaySlot: false,
            defaultTimedEventDuration: '00:05:00',
            loading: this.$_fullCalendarHelper_onLoading,
            dateClick: this.onDateClick,
            eventClick: this.onEventClick
        }
    },

    mounted() {
        // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
        let quickSwitchIcon = document.createElement('a');
        quickSwitchIcon.setAttribute('class', 'adtp-btn icon-calendar no-text');
        quickSwitchIcon.innerHTML += '<span class="splash animate"></span>';

        document.getElementsByClassName('fc-quickSwitchButton-button')[0].appendChild(quickSwitchIcon);
    },

    methods: {

        // contentHeight() {
        //     return this.helper.contentHeight();
        // },

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
            if (!this.$_fullCalendarHelper_eventRender(eventRenderInfo)) {
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
            return this.$_fullCalendarHelper_toMoment(date);
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

        /**
         * Apply natural sort ordering to resources before display
         */
        resourceOrder(a, b) {
            if (typeof a?.name === 'string' && typeof b?.name === 'string') {
                return a.name.localeCompare(b.name, 'en', { numeric: true, sensitivity: 'accent' });
            }
            return 0;
        },
    }
    
}

</script>

<template>
    <div>
        <export-modal 
            ref="export"
            :token="config.token"
            :booking-export-url="urls.export"
            :resource-id="config.resourceId"
            :booking-type="'category'"
        />

        <filter-modal 
            ref="filter"
            :selected-filters="config.selectedFilters"
            @filterChanged="reloadEvents" 
        />

        <create-booking-modal 
            ref="createBooking"
            :to-moment="toMoment"
            :booking-path="config.createBookingPath"
            :date-formats="formats"
            :booking-type="'category'"
        />

        <view-event-modal 
            ref="event"
            :to-moment="toMoment"
            :event-modify-form-url="urls.modifyForm"
            :event-modify-form-action-url="urls.modifyFormAction"
            :event-update-url="urls.update"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
            @eventModified="reloadEvents"
            @eventDeleted="reloadEvents" 
        />

        <full-calendar
            ref="fullCalendar"
            :options="calendarOptions"
        />

        <flat-pickr
            ref="flatPickr"
            :config="{
                defaultDate: config.defaultDate,
                selector: '.fc-quickSwitchButton-button',
                disableMobile: true,
            }"
            @dateSelected="onQuickSwitch"
        />
    </div>
</template>

<style lang="scss">
    
</style>
