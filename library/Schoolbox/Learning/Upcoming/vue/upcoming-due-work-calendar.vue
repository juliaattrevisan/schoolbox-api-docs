<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import FullCalendar from "@fullcalendar/vue3";
import interactionPlugin from "@fullcalendar/interaction";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import adaptivePlugin from "@fullcalendar/adaptive";
import { getSearchString } from "@js/util/queryUtils";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import ViewEventModal from "@schoolboxVue/components/fullcalendar/view-event-modal.vue";
import FlatPickr from "@schoolboxVue/components/fullcalendar/flat-pickr.vue";
import fullCalendarHelper from "@schoolboxVue/components/fullcalendar/mixinFullCalendarHelper";
import CustomisedCalendarEvent from "@schoolboxVue/components/fullcalendar/customised-calendar-event.vue";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";

// Various URLs that FullCalendar may make use of.
const URL_EVENT_SOURCE = "/learning/due/events";
const URL_EVENT_EXPORT = "/calendar/export.php";

const FORMAT_FORM_DATE = "DD-MM-YYYY";
const FORMAT_FORM_DATETIME = "DD-MM-YYYY HH:mm";

// TODO let display formats be configurable
const FORMAT_DISPLAY_DATE = "ddd Do MMM";
const FORMAT_DISPLAY_TIME = "h:mma";
const FORMAT_DISPLAY_DATETIME = "ddd Do MMM, h:mma";
const FORMAT_DISPLAY_WEEK_DAY = "ddd D/M";

export default {
    name: "upcoming-due-work-calendar",

    components: {
        ViewEventModal,
        FullCalendar,
        RevealModal,
        FlatPickr,
        CustomisedCalendarEvent,
    },

    mixins: [fullCalendarHelper],

    data() {
        return {
            calendarApi: null, //to access the underlying calendar object
            selectedFilters: null,
            calendarOptions: {}, //will init in beforeMount due to vars being needed
        };
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

    beforeMount() {
        this.calendarOptions = {
            schedulerLicenseKey: this.config.schedulerLicenseKey,
            plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, adaptivePlugin],
            views: {
                day: {
                    dayHeaders: false,
                    titleFormat: (date) => {
                        return dayjs(date.date.array).format(this.config.dateDisplayFormats.dayjsDateLong); // will produce something like "Tuesday July 16th 2021"
                    },
                },
                week: {
                    dayHeaderFormat: (date) => {
                        return dayjs(date.date.array).format(this.formats.displayWeekDay);
                    },
                    titleFormat: (date) => {
                        return this.fullCalendarHelper_titleFormatWeek(date);
                    },
                },
            },
            eventTimeFormat: (date) => {
                return this.fullCalendarHelper_eventTimeFormat(date);
            },
            customButtons: {
                quickSwitchButton: {
                    text: " ",
                },
                customPrev: {
                    icon: "chevron-left",
                    click: this.clickPrev,
                },
                customNext: {
                    icon: "chevron-right",
                    click: this.clickNext,
                },
                customToday: {
                    text: "Today",
                    click: this.clickToday,
                },
            },
            headerToolbar: {
                left: "quickSwitchButton,customToday,customPrev,customNext",
                center: "title",
                right: "dayGridMonth,timeGridWeek,timeGridDay",
            },
            buttonText: {
                today: "Today",
                month: "Month",
                week: "Week",
                day: "Day",
            },
            initialView: "dayGridMonth",
            scrollTime: this.config.dateDisplayFormats.calendarStartTime,
            eventDidMount: this.fullCalendarHelper_eventRender,
            defaultTimedEventDuration: "00:01:00",
            events: this.events,
            loading: this.fullCalendarHelper_onLoading,
            eventClick: this.onEventClick,
        };
    },

    mounted() {
        this.$nextTick().then(() => {
            this.calendarApi = this.$refs.fullCalendar.getApi();
            this.calendarApi.setOption("contentHeight", this.fullCalendarHelper_contentHeight());
            this.calendarApi.scrollToTime(this.config.dateDisplayFormats.calendarStartTime);
        });

        // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
        const quickSwitchIcon = document.createElement("a");
        quickSwitchIcon.setAttribute("class", "adtp-btn icon-calendar no-text");

        document.getElementsByClassName("fc-quickSwitchButton-button")[0].appendChild(quickSwitchIcon);

        document.addEventListener("upcoming-due-work-calendar__onFilterChange", this.onFilterChange);
    },

    methods: {
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
            activate("Retrieving calendar events...");
            const params = {
                start: Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                end: Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                filters: this.selectedFilters,
            };
            fetch(`${URL_EVENT_SOURCE}/${window.fullCalendarConfig.userId}?${getSearchString(params)}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            })
                .then((response) => response.json())
                .then((response) => {
                    successCallback(
                        response.filter((v) => {
                            return "start" in v;
                        }),
                    );
                    timeout({ text: "Events retrieved successfully" });
                })
                .catch((error) => {
                    timeout({ text: "Events could not be retrieved", isSuccess: false });
                    console.error(error);
                });
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
         * Fired from outer space, currently from: library/Schoolbox/Learning/Upcoming/templates/upcomingOuter.twig
         *
         * @param {object} selectedFilters The filters that were selected
         */
        onFilterChange(event) {
            this.selectedFilters = event.detail.selectedFilters;
            this.calendarApi.refetchEvents();
        },
    },
};
</script>

<template>
    <div>
        <view-event-modal
            ref="event"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
        />
        <full-calendar ref="fullCalendar" :options="calendarOptions">
            <template v-slot:eventContent="arg">
                <customised-calendar-event :content="arg" />
            </template>
        </full-calendar>
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
@import "@schoolboxVue/components/fullcalendar/styles/calendar";
</style>
