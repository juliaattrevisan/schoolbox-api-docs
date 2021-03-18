<script>

import FullCalendar               from '@fullcalendar/vue'
import interactionPlugin          from '@fullcalendar/interaction'
import dayGridPlugin              from '@fullcalendar/daygrid'
import timeGridPlugin             from '@fullcalendar/timegrid'
import listPlugin                 from '@fullcalendar/list'
import momentPlugin               from '@fullcalendar/moment'
import ViewEventModal             from '@schoolbox/fullcalendar/view-event-modal';
import CreateBookingModal         from '@schoolbox/fullcalendar/resourceBooking/create-booking-modal';
import ExportModal                from '@schoolbox/fullcalendar/resourceBooking/export-booking-modal';
import FilterModal                from '@schoolbox/fullcalendar/resourceBooking/filter-modal';
import FlatPickr                  from '@schoolbox/fullcalendar/flat-pickr';
import axios                      from 'axios'
import { mixinFullCalendarHelper } from '@schoolbox/fullcalendar/mixinFullCalendarHelper';

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

    name: 'resource-booking-item-calendar',

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
            calendarApi: null, //to access the underlying calendar object
            calendarOptions: {} //will init in beforeMount due to linked vars being needed
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

    beforeMount() {
        this.calendarOptions = {
            plugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, momentPlugin ],
            views: {
                day: {
                    titleFormat: this.dayTitleFormat,
                    dayHeaders: false,
                    allDaySlot: false,
                },
                week: {
                    dayHeaderFormat: this.formats.displayWeekDay,
                    allDaySlot: false,
                }
            },
            eventTimeFormat: this.$_fullCalendarHelper_eventTimeFormat,
            customButtons: {
                customExport: {
                    text: 'Export',
                    click: this.onExportClick
                },
                customFilters: {
                    text: 'Filters',
                    click: this.onFilterClick
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
                right: 'customFilters,dayGridMonth,timeGridWeek,timeGridDay,customExport'
            },
            initialDate: this.config.defaultDate,
            initialView: this.config.defaultView || 'timeGridWeek',
            scrollTime: this.config.dateDisplayFormats.calendarStartTime,
            dayMaxEventRows: this.config.eventLimit,
            firstDay: this.config.firstDay,
            events: this.events,
            eventDidMount: this.eventRender,
            loading: this.$_fullCalendarHelper_onLoading,
            dateClick: this.onDateClick,
            eventClick: this.onEventClick
        }
    },

    mounted() {

        this.$nextTick()
        .then(() => {
            this.calendarApi = this.$refs.fullCalendar.getApi();
            this.calendarApi.setOption('height', this.$_fullCalendarHelper_contentHeight());
        });

        // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
        let quickSwitchIcon = document.createElement('a');
        quickSwitchIcon.setAttribute('class', 'adtp-btn icon-calendar no-text');
        quickSwitchIcon.innerHTML += '<span class="splash animate"></span>';

        document.getElementsByClassName('fc-quickSwitchButton-button')[0].appendChild(quickSwitchIcon);

    },

    methods: {

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
            axios.get(
                this.config.resourceBookingEndpoint,
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
            ).then((response) => {
                callback(response.data.filter((v) => {
                    return 'start' in v;
                }));
            } );
            // Fail callback: consider a message saying something failed
        },

        /**
         * Event render callback: manually tweaks the event's rendering, and
         * can also conditionally show/hide the event.
         * @see https://fullcalendar.io/docs/event-render-hooks
         *
         * @param event
         * @return {boolean}
         */
        eventRender(eventRenderInfo) {
            
            if (!this.$_fullCalendarHelper_eventRender(eventRenderInfo)) {
                eventRenderInfo.event.setProp('display', 'none');
                return false;
            }

            let bookingStatus = eventRenderInfo.event.extendedProps.data.booking?.status,
                element = eventRenderInfo.el;

            // Add booking styles
            if (bookingStatus?.isRejected) {
                element.classList.add('rejected');
            } else if (bookingStatus?.isApproved) {
                element.classList.add('approved');
            } else if (bookingStatus?.isPending) {
                element.classList.add('pending');
            }

            eventRenderInfo.event.setProp('display', 'block');
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
         * Callback for adding an event to the calendar.
         * @see https://fullcalendar.io/docs/Calendar-addEvent
         *
         * @param event
         */
        addEvent(event) {
            this.calendarApi.addEvent(event);
        },

        /**
         * Callback for reloading the calendar's events. May be used when
         * a series of events is deleted, as the calendar no longer knows
         * whether an event should be rendered even if it wasn't deleted.
         * @see https://fullcalendar.io/docs/Calendar-refetchEvents
         */
        reloadEvents() {
            this.calendarApi.refetchEvents();
        },

        /**
         * Additional customisation for prev button to sync flatpickr's selected date with fullCalendar's
         */
        clickPrev() {
            this.calendarApi.prev();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
        },

        /**
         * Additional customisation for next button to sync flatpickr's selected date with fullCalendar's
         */
        clickNext() {
            this.calendarApi.next();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
        },

        /**
         * Additional customisation for today's button to sync flatpickr's selected date with fullCalendar's
         */
        clickToday() {
            this.calendarApi.today();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
        },

        /**
         * Additional customisation for quick month switch button.
         * When the user clicks on the flatpickr's date, we'll trigger fullcalendar's gotoDate.
         */
        onQuickSwitch(value) {
            this.calendarApi.gotoDate(value.selectedDate);
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
            :booking-type="'item'"
        />

        <filter-modal 
            ref="filter"
            :selected-filters="config.selectedFilters"
            @filterChanged="reloadEvents" 
        />

        <create-booking-modal 
            ref="createBooking"
            :to-moment="$_fullCalendarHelper_toMoment"
            :booking-path="config.createBookingPath"
            :date-formats="formats"
            :booking-type="'item'"
        />

        <view-event-modal 
            ref="event"
            :to-moment="$_fullCalendarHelper_toMoment"
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
    // @import '~@fullcalendar/core/main.css';
    // @import '~@fullcalendar/daygrid/main.css';
    // @import '~@fullcalendar/timegrid/main.css';
    // @import '~@fullcalendar/list/main.css';
</style>
