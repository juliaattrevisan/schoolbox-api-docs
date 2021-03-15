<script>

import FullCalendar       from '@fullcalendar/vue'
import interactionPlugin  from '@fullcalendar/interaction'
import dayGridPlugin      from '@fullcalendar/daygrid'
import timeGridPlugin     from '@fullcalendar/timegrid'
import listPlugin         from '@fullcalendar/list'
import momentPlugin       from '@fullcalendar/moment'
import RevealModal        from '@schoolbox/reveal-modal'
import ViewEventModal     from '@schoolbox/fullcalendar/view-event-modal';
import FlatPickr          from '@schoolbox/fullcalendar/flatPickr';
import axios              from 'axios'
import { mixinFullCalendarHelper } from '@schoolbox/fullcalendar/mixinFullCalendarHelper';
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

    name: 'upcoming-due-work-calendar',

    components: {
        ViewEventModal,
        FullCalendar,
        RevealModal,
        FlatPickr,
    },

    mixins: [
        mixinFullCalendarHelper
    ],

    data () {
        return {
            selectedFilters: null,
            calendarOptions: {} //will init in beforeMount due to vars being needed
        }
    },

    computed: {
        jQuery: () => window.jQuery,
        config: () => window.fullCalendarConfig,
        formats () {
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
            plugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, momentPlugin ],
            views: {
                day: {
                    titleFormat: this.dayTitleFormat,
                    columnHeader: false
                },
                week: {
                    columnHeaderFormat: this.formats.displayWeekDay
                }
            },
            eventTimeFormat: this.$_fullCalendarHelper_eventTimeFormat,
            // contentHeight: 'this.contentHeight',
            customButtons: {
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
            headerToolbar: {
                left: 'quickSwitchButton,customToday,customPrev,customNext',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            },
            initialView: 'dayGridMonth',
            eventDidMount: this.$_fullCalendarHelper_eventRender,
            events: this.events,
            loading: this.$_fullCalendarHelper_onLoading,
            eventClick: this.onEventClick
        }
    },

    mounted () {

        // this.$nextTick()
        // .then(() => {
        //     // this.$refs.fullCalendar.setOption('contentHeight', this.contentHeight);
        //     debugger
        // });

        // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
        const quickSwitchIcon = document.createElement('a');
        quickSwitchIcon.setAttribute('class', 'adtp-btn icon-calendar no-text');
        quickSwitchIcon.innerHTML += '<span class="splash animate"></span>';

        document.getElementsByClassName('fc-quickSwitchButton-button')[0].appendChild(quickSwitchIcon);
    },

    methods: {

        // contentHeight() {
        //     return this.helper.contentHeight();
        // },

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

<template>
    <div>
        <view-event-modal 
            ref="event"
            :to-moment="$_fullCalendarHelper_toMoment"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
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
