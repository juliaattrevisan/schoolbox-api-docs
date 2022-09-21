<script>

import FullCalendar               from '@fullcalendar/vue3'
import interactionPlugin          from '@fullcalendar/interaction'
import dayGridPlugin              from '@fullcalendar/daygrid'
import timeGridPlugin             from '@fullcalendar/timegrid'
import listPlugin                 from '@fullcalendar/list'
import adaptivePlugin             from '@fullcalendar/adaptive'
import ViewEventModal             from '@schoolboxVue/components/fullcalendar/view-event-modal';
import CreateEventModal           from './create-event-modal';
import ExportModal                from "./export-modal";
import FilterModal                from "./filter-modal";
import FlatPickr                  from '@schoolboxVue/components/fullcalendar/flat-pickr';
import axios                      from 'axios'
import qs                         from "qs";
import fullCalendarHelper         from "@schoolboxVue/components/fullcalendar/mixinFullCalendarHelper";
import {ENDPOINTS}                from '@schoolboxCalendarEvent/endpoints';
import CustomisedCalendarEvent    from '@schoolboxVue/components/fullcalendar/customised-calendar-event';


// Various URLs that FullCalendar may make use of.
const URL_STATE_CHANGE = '/calendar/changeState';

const URL_EVENT_SOURCE = '/calendar/ajax/full';
const URL_EVENT_UPDATE = '/calendar/ajax/update';
const URL_EVENT_FILTER = '/calendar/ajax/filter';
const URL_EVENT_CREATE = '/calendar/ajax/create';

/* TODO: Events should already know about and come with their own modify link;
 * we shouldn't be inferring and building it out here
 */
const URL_EVENT_CREATE_FORM = '/calendar/event/create';
const URL_EVENT_MODIFY_FORM = '/calendar/event/{eventId}/modify';
const URL_EVENT_MODIFY_FORM_ACTION = '/calendar/modifyForm.php';
const URL_EVENT_DELETE = '/calendar/event/{eventId}/delete';
const URL_EVENT_EXPORT = '/calendar/export.php';

const URL_ATTENDANCE_LIST = '/calendar/attendance/list/';
const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';

const FORMAT_FORM_DATE = 'DD-MM-YYYY';
const FORMAT_FLATPICKR_DATE = 'DD/MM/YYYY';
const FORMAT_FORM_DATETIME = 'DD-MM-YYYY HH:mm';

// TODO let display formats be configurable
const FORMAT_DISPLAY_DATE = 'ddd Do MMM';
const FORMAT_DISPLAY_TIME = 'h:mma';
const FORMAT_DISPLAY_DATETIME = 'ddd Do MMM, h:mma';
const FORMAT_DISPLAY_WEEK_DAY = 'ddd D/M';

// Default event duration is 1 hour, default allday event span is 1 day
const DEFAULT_EVENT_DURATION    = 60 * 60 * 1000;
const DEFAULT_ALLDAY_EVENT_DAYS = 1;

const EVENT_SOURCE_ID = 'events';

export default {

    name: 'timetable-calendar',

    components: {
        FilterModal,
        ExportModal,
        CreateEventModal,
        ViewEventModal,
        FullCalendar,
        FlatPickr,
        CustomisedCalendarEvent
    },

    mixins: [
        fullCalendarHelper
    ],

    data () {
        return {
            calendarApi: null, //to access the underlying calendar object
            currentDisplay: null,
            duration: {
                days: 31
            },
            listViewDateRange: {
                start: null,
                end: null,
            },
            calendarOptions: {}
        }
    },

    computed: {
        jQuery: () => window.jQuery,
        config: () => window.fullCalendarConfig,
        urls() {
            return {
                createForm: URL_EVENT_CREATE_FORM,
                modifyForm: URL_EVENT_MODIFY_FORM,
                modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
                create: URL_EVENT_CREATE,
                update: URL_EVENT_UPDATE,
                delete: URL_EVENT_DELETE,
                export: URL_EVENT_EXPORT,
                attendanceList: URL_ATTENDANCE_LIST,
                attendanceToggle: URL_ATTENDANCE_TOGGLE,
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
        }
    },

    beforeMount () {
        this.calendarOptions = {
            schedulerLicenseKey: this.config.schedulerLicenseKey,
            plugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, adaptivePlugin ],
            /* Some schools start on saturday/sundays, some don't
             * For now make this a configurable option
             */
            weekends: this.config.weekends,
            /* Make all the rows fill the full height of the container element such
             * that the last row isn't hilariously tall
             */
            expandRows: true,
            /* Show a red line to indicate the current day/time - this used to be a
             * blue bordered outline in the classical grid
             */
            nowIndicator: true,
            views: {
                /* Only the Week view is used in Timetable Calendar
                 * Disabled the other two
                 */
                // day: {
                //     dayHeaders: false,
                //     titleFormat: (date) => {
                //         return dayjs(date.date.array).format(this.config.dateDisplayFormats.dayjsDateLong); // will produce something like "Tuesday July 16th 2021"
                //     }
                // },
                week: {
                    dayHeaderFormat: (date) => {
                        return dayjs(date.date.array).format(this.formats.displayWeekDay);
                    },
                    titleFormat: (date) => {
                        return this.fullCalendarHelper_titleFormatWeek(date);
                    }
                },
                // list: {
                //     buttonText: 'List',
                //     duration: this.duration,
                //     listDayFormat: (date) => {
                //         return dayjs(date.date.array).format(this.config.dateDisplayFormats.dayjsDateLong);
                //     }
                // }
            },
            eventTimeFormat: (date) => {
                return this.fullCalendarHelper_eventTimeFormat(date);
            },
            customButtons: {
                /* You can't export or filter events in Timetable Calendar view
                 */
                // customExport: {
                //     text: 'Export',
                //     click: this.onExportClick
                // },
                // customFilters: {
                //     text: 'Filters',
                //     click: this.onFilterClick
                // },
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
                // right: 'customFilters,list,dayGridMonth,timeGridWeek,timeGridDay,customExport'
                right: ''
            },
            buttonText: {
                today:    'Today',
                /* Didn't need any of these extra buttons in Timetable View
                 */
                // month:    'Month',
                // week:     'Week',
                // day:      'Day',
                // list:     'List',
            },
            initialDate: this.config.defaultDate,
            initialView: this.config.defaultView || 'dayGridMonth',
            scrollTime: this.config.dateDisplayFormats.calendarStartTime,
            dayMaxEventRows: this.config.eventLimit,
            firstDay: this.config.firstDay,
            eventSources: [
                {
                    id:     EVENT_SOURCE_ID,
                    events: this.events,
                }
            ],
            eventDidMount: this.eventRender,
            viewDidMount: this.viewDidMount,
            loading: this.fullCalendarHelper_onLoading,
            dateClick: this.onDateClick,
            eventClick: this.onEventClick,
            eventResize: this.onEventResize,
            eventDrop: this.onEventDrop
        }
    },

    mounted() {
        this.$nextTick()
            .then(() => {
                this.calendarApi = this.$refs.fullCalendar.getApi();
                this.calendarApi.setOption('contentHeight', this.fullCalendarHelper_contentHeight());
                this.calendarApi.scrollToTime(this.config.dateDisplayFormats.calendarStartTime);
            });

        this.registerListCustomInputFields();

        // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
        let quickSwitchIcon = document.createElement('a');
        quickSwitchIcon.setAttribute('class', 'adtp-btn icon-calendar no-text');
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
        events (fetchInfo, callback) {
            axios.get(
                URL_EVENT_SOURCE,
                {
                    headers: {
                        'X-Requested-With' : 'XMLHttpRequest'
                    },
                    params: {
                        start:  Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                        end:    Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                        userId: this.config.userId,

                        /* This param lets you load the specific set of subscriptions
                         * relevant to the Timetable Calendar
                         */
                        timetableCalendar: true
                    }
                }
            ).then((response) => {
                callback(response.data.filter((v) => {
                    return 'start' in v;
                }));

                let slots = this.findSlotBoundaries(response.data);

                this.calendarApi.setOption('slotMinTime', slots.slotMinTime);
                this.calendarApi.setOption('slotMaxTime', slots.slotMaxTime);
                this.calendarApi.setOption('allDaySlot', slots.allDaySlot);
            } );
            // Fail callback: consider a message saying something failed
        },


        /**
         * Find the earliest time and the latest time in a set of events
         * Used to configure slotMinTime and slotMaxTime
         *
         * @param data
         */
        findSlotBoundaries (data) {
            let slotMinTime = null;
            let slotMaxTime = null;
            let allDaySlot = false;

            for (let i = 0; i < data.length; i++) {
                /* Don't care about all-day events; they don't alter
                 * the way we set slotMinTime and slotMaxTime
                 *
                 * If there aren't any all-day events, hide the ui thing
                 */
                if (data[i].allDay) {
                    allDaySlot = true;

                    continue;
                }

                /* Remember the earliest time we saw
                 */
                let start = dayjs(new Date(data[i].start)).format('HH:mm:ss');
                if (null === slotMinTime || slotMinTime > start) {
                    slotMinTime = start;
                }

                /* Remember the latest time we saw
                 */
                let finish = dayjs(new Date(data[i].end)).format('HH:mm:ss');

                if (null === slotMaxTime || slotMaxTime < finish) {
                    slotMaxTime = finish;
                }
            }

            return {
                slotMinTime: slotMinTime ?? '00:00:00',
                slotMaxTime: slotMaxTime ?? '00:00:00',
                allDaySlot: allDaySlot
            }
        },


        /**
         * Callback for when the calendar view changes: preserve the
         * newly selected view for this user.
         * @see https://fullcalendar.io/docs/viewDidMount
         *
         * @param info
         */
        viewDidMount(info) {

            const customGroup = document.querySelector('.fc-custom-group');

            this.currentDisplay = info.view.type;

            // document.querySelectorAll('.fc-toolbar .fc-left .fc-button-group, .fc-toolbar .fc-center').forEach((element) => {
            document.querySelectorAll('.fc-button-group, .fc-toolbar-title').forEach((element) => {
                if (this.currentViewIsList()) {
                    if (!element.firstChild?.classList?.contains('fc-customFilters-button')) { //never hide the right side buttons
                        element.classList.add('hide');
                    }
                    if (customGroup?.classList && customGroup?.classList.contains('hide') !== null) {
                        customGroup.classList.remove('hide')
                    }
                } else {
                    element.classList.remove('hide');
                    if (customGroup?.classList) {
                        customGroup.classList.add('hide');
                    }
                }
            });

            if (this.currentViewIsList()) {

                this.registerListCustomInputFields();

                /**
                 * Re-initialise datepicker upon date update on list view.
                 * Without this, date picker fields just turns into a normal text field.
                 */
                let attachAlaressDatePicker = function () {
                    var $dateinput = $(this);
                    if ($dateinput.attr('data-datepicker-id') === undefined) {
                        $dateinput.alaressDateTimePicker();
                    }
                };

                $(function () {
                    $('[data-datepicker]').each(attachAlaressDatePicker);
                });
            }

            axios.post(
                URL_STATE_CHANGE,
                qs.stringify({calendarView: info.view.type})
            );
        },

        /**
         * Event render callback: manually tweaks the event's rendering, and
         * can also conditionally show/hide the event.
         * @see https://fullcalendar.io/docs/event-render-hooks
         *
         * @param eventRenderInfo
         * @return {boolean}
         */
        eventRender (eventRenderInfo) {

            /* Filters Modal doesn't exist in the Timetable Calendar special screen
             * Everything always renders
             */
            return true;

            // const calEvent = eventRenderInfo.event;
            //
            // if (!this.fullCalendarHelper_eventRender(eventRenderInfo)) {
            //     calEvent.setProp('display', 'none');
            //     return false;
            // }
            //
            // // Extra condition for rendering events on this calendar:
            // // All of the event's attributes must match at least one
            // // selected filter: if it doesn't, don't render it.
            // let eventAttributes = {};
            // let isEventRendered = true;
            //
            // // Build a list of filters from the event
            // calEvent.classNames.forEach((className) => {
            //     let match = className.match(/^([a-z]+)(\d+)/i);
            //     if (match) {
            //         let attributeGroup = match[1],
            //             id             = Number(match[2]);
            //         if (!eventAttributes[attributeGroup]) {
            //             eventAttributes[attributeGroup] = [];
            //         }
            //         eventAttributes[attributeGroup].push(id);
            //     }
            // });
            //
            // // All of the event's attributes must match at least one
            // // selected filter.
            // // If it doesn't, don't render it.
            // Object.keys(eventAttributes).forEach((attributeGroup) => {
            //     let ids                = eventAttributes[attributeGroup],
            //         eventMatchesFilter = false;
            //
            //     // If all items in the attribute group are selected:
            //     // always render an event that has one of these attributes,
            //     // even if it doesn't match the filter.
            //     // This allows events a user has been invited to, but are
            //     // outside their list of groups/campuses, to display.
            //     if (this.$refs.filter.allSelected(attributeGroup)) {
            //         eventMatchesFilter = true;
            //         return;
            //     }
            //
            //     // Otherwise: check id against the list of selected filters.
            //     ids.forEach((id) => {
            //         let keyspace = [];
            //         switch (attributeGroup) {
            //             case 'source':
            //                 // Sources have multiple IDs per selection:
            //                 // this event needs to only match one of them.
            //                 keyspace = (this.config.selectedFilters?.source || []).flat();
            //                 break;
            //
            //             case 'type':
            //                 keyspace = this.config.selectedFilters?.eventType || [];
            //                 break;
            //
            //             case 'group':
            //                 keyspace = this.config.selectedFilters?.group || [];
            //                 break;
            //
            //             case 'campus':
            //                 keyspace = this.config.selectedFilters?.campus || [];
            //                 break;
            //
            //             default:
            //                 // Ignore this one
            //                 return;
            //         }
            //         if (keyspace.includes(id)) {
            //             eventMatchesFilter = true;
            //         }
            //     });
            //     if (!eventMatchesFilter) {
            //         isEventRendered = false;
            //     }
            // });
            //
            // if (isEventRendered) {
            //     calEvent.setProp('display', 'block');
            // } else {
            //     calEvent.setProp('display', 'none');
            // }
            //
            // return isEventRendered;
        },

        /**
         * Click a date on the calendar callback: opens the create event
         * modal for the clicked date.
         * @see https://fullcalendar.io/docs/dateClick
         *
         * @param dateClickInfo
         */
        onDateClick(dateClickInfo) {
            let [firstEventType] = this.config.eventTypes;
            this.$refs.createEvent.open({
                date:        dateClickInfo.date,
                allDay:      dateClickInfo.allDay,
                title:       '',
                detail:      '',
                type:        firstEventType ? firstEventType.id : null,
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
            // Ideally the current event would be a part of the modal,
            // not this component, but :shrug:
            this.$refs.event.open(eventInfo.event);
        },

        /**
         * Resize event callback: persist the change to the event's start
         * and end dates.
         * @see https://fullcalendar.io/docs/eventResize
         *
         * @param eventResizeInfo
         */
        onEventResize(eventResizeInfo) {
            this.moveEvent(eventResizeInfo.event);
        },

        /**
         * Drop event callback: persist the change to the event's start
         * and end dates.
         * @see https://fullcalendar.io/docs/eventDrop
         *
         * @param eventDropInfo
         */
        onEventDrop(eventDropInfo) {
            // If an event is moved from all-day to a time:
            // it won't have an end date, assume it lasts 1 hour
            if (eventDropInfo.oldEvent.allDay && !eventDropInfo.event.allDay) {
                eventDropInfo.event.setEnd(new Date(eventDropInfo.event.start.getTime() + DEFAULT_EVENT_DURATION));
            }

            // If event is moved from a time to all-day:
            // it won't have an end date either, assume it lasts 1 day
            if (!eventDropInfo.oldEvent.allDay && eventDropInfo.event.allDay) {
                eventDropInfo.event.setEnd(new Date(eventDropInfo.event.start.getDate() + DEFAULT_ALLDAY_EVENT_DAYS));
            }

            // And move the event
            this.moveEvent(eventDropInfo.event);
        },

        /**
         * Moves an existing event to its new start and end time.
         *
         * @param {Object} event
         */
        moveEvent(event) {

            const data = {
                start:  dayjs(event.start).format(),
                allDay: event.allDay,
            };

            // Account for the difference in all-day end date between FullCalendar and Schoolbox:
            // * Schoolbox events are inclusive of the end date
            // * FullCalendar events are exclusive
            // This means that all-day events need their end date set back
            // one day before being sent to Schoolbox.
            if (event.allDay && dayjs(event.end).isValid()) {
                data.end = dayjs(event.end).add(dayjs.duration({days: -1})).format();
            } else if (dayjs(event.end).isValid()) { //there isn't always an end date, so only add in when it's available
                data.end = dayjs(event.end).format();
            } else { //otherwise it's assumed the start date is the same as the end date
                data.end = dayjs(event.start).format();
            }

            // @TODO this needs to consider events in series
            axios.post(
                ENDPOINTS.generateUrlFromConst(ENDPOINTS.URL_EVENT_MOVE, String(event?.extendedProps?.data?.meta?.eventId)),
                qs.stringify(data)
            );

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
         * Callback for adding an event to the calendar.
         * @see https://fullcalendar.io/docs/Calendar-addEvent
         *
         * @param event
         */
        addEvent(event) {
            this.calendarApi.addEvent(event, EVENT_SOURCE_ID);
        },

        /**
         * Check if current display is list
         */
        currentViewIsList() {
            return this.currentDisplay === 'list';
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
         * Callback for when the filters have been updated: rerender events
         * (thus hiding events which don't match the new filters) and
         * persist the filters selected.
         *
         * @param selectedFilters
         */
        async updateSelectedFilters(selectedFilters) {
            // The selected filters in config are read-only and passed by reference.
            // TODO pass it by value into the filter modal, and update in this method
            try {
                await axios.post(URL_EVENT_FILTER, qs.stringify({filters: this.config.selectedFilters,}));
                this.calendarApi.refetchEvents();
            } catch (error) {
                console.warn(error);
            }
        },

        /**
         * Register customised input fields for list view
         */
        registerListCustomInputFields() {

            // Make sure that there's only one of the custom input field
            if (!document.querySelector('.fc-custom-group'))
            {
                // Prepare input fields
                const container = document.createElement('div');
                const startDate = document.createElement('input');
                const startDateFieldWrapper = document.createElement('div');
                const endDate = document.createElement('input');
                const endDateFieldWrapper = document.createElement('div');
                const updateRangeButton = document.createElement('input');
                const buttonWrapper = document.createElement('div');

                startDateFieldWrapper.setAttribute('class', 'small-4 columns');
                endDateFieldWrapper.setAttribute('class', 'small-4 columns');
                buttonWrapper.setAttribute('class', 'small-4 columns');

                // Start date field
                startDate.setAttribute('id', 'start_date');
                startDate.setAttribute('name', 'start_date');
                startDate.setAttribute('data-datepicker', '');
                startDate.setAttribute('data-datepicker-buttontext', '');
                startDate.setAttribute('value',
                    (this.listViewDateRange.start) ? this.listViewDateRange.start : dayjs(new Date()).format(FORMAT_FLATPICKR_DATE)
                );


                // End date field
                endDate.setAttribute('id', 'end_date');
                endDate.setAttribute('name', 'end_date');
                endDate.setAttribute('data-datepicker', '');
                endDate.setAttribute('data-datepicker-buttontext', '');
                endDate.setAttribute('value',
                    (this.listViewDateRange.end) ? this.listViewDateRange.end : dayjs(new Date()).add(this.duration.days, 'days').format(FORMAT_FLATPICKR_DATE)
                );


                // Update button
                updateRangeButton.setAttribute('id', 'update_date_range_button');
                updateRangeButton.setAttribute('type', 'submit');
                updateRangeButton.setAttribute('value', 'Update');

                if (this.currentViewIsList()) {
                    container.setAttribute('class', 'fc-custom-group row');
                } else {
                    container.setAttribute('class', 'fc-custom-group row hide');
                }

                // Wrap input fields to columns
                startDateFieldWrapper.appendChild(startDate);
                endDateFieldWrapper.appendChild(endDate);
                buttonWrapper.appendChild(updateRangeButton);

                // Append fields into the container
                container.appendChild(startDateFieldWrapper);
                container.appendChild(endDateFieldWrapper);
                container.appendChild(buttonWrapper);

                //add to the first tooblbar chunk, so it'll appear on the left hand side
                document.querySelector('.fc-toolbar').getElementsByClassName('fc-toolbar-chunk')[0].appendChild(container);

                // Register an event listener when update button is clicked.
                document.querySelector('#update_date_range_button').addEventListener('click', () => {
                    if (document.querySelector('#start_date').value && document.querySelector('#end_date').value) {
                        const startDateValue = dayjs(document.querySelector('#start_date').value, FORMAT_FLATPICKR_DATE, true);
                        const endDateValue = dayjs(document.querySelector('#end_date').value, FORMAT_FLATPICKR_DATE, true);

                        if (startDateValue.isValid() && endDateValue.isValid()) {
                            this.listViewDateRange.start = document.querySelector('#start_date').value;
                            this.listViewDateRange.end = document.querySelector('#end_date').value;

                            this.duration.days = this.fullCalendarHelper_dateDiff(startDateValue.format('YYYY-MM-DD'), endDateValue.format('YYYY-MM-DD')) + 1;
                            this.calendarApi.gotoDate(startDateValue.format('YYYY-MM-DD'));

                        }
                    }
                });
            }

        },

        /**
         * Update list view's start and end dates on date navigation
         * Wish we can use v-model rather than doing this but because the input fields are being injected via javascript,
         * I'm not sure if we can use it..
         */
        updateListViewDateRangeField(start, end = null) {
            /* This is the next/previous button on the date selector
             * It needs to go to the new /timetable/calendar/... route
             */
            window.history.pushState('', '', '/timetable/calendar/' + dayjs(start).format(FORMAT_FORM_DATE) + '/' + this.config.userId);

            this.listViewDateRange.start = dayjs(start).format(FORMAT_FLATPICKR_DATE);
            this.listViewDateRange.end = dayjs(end || new Date()).add(this.duration.days, 'days').format(FORMAT_FLATPICKR_DATE);

            let startDate = this.jQuery('#start_date'),
                endDate   = this.jQuery('#end_date');

            // .val() is needed so that we'll have the value visible in the
            // input field.
            // data-default-date is needed so that when flatpickr gets
            // initialised, the selected value will be the same as the one
            // that's provided in the input field.
            // The change event is required so that the date picker popup is
            // updated with the new date.
            startDate
                .val(this.listViewDateRange.start)
                .attr('data-default-date', this.listViewDateRange.start)
                .trigger('change');
            endDate
                .val(this.listViewDateRange.end)
                .attr('data-default-date', this.listViewDateRange.end)
                .trigger('change');
        },

        /**
         * Additional customisation for prev button to sync flatpickr's selected date with fullCalendar's
         */
        clickPrev() {
            this.calendarApi.prev();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeField(this.calendarApi.getDate(), this.calendarApi.getDate());
        },

        /**
         * Additional customisation for next button to sync flatpickr's selected date with fullCalendar's
         */
        clickNext() {
            this.calendarApi.next();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeField(this.calendarApi.getDate(), this.calendarApi.getDate());
        },

        /**
         * Additional customisation for today's button to sync flatpickr's selected date with fullCalendar's
         */
        clickToday() {
            this.calendarApi.today();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeField(this.calendarApi.getDate());
        },

        /**
         * Additional customisation for quick month switch button.
         * When the user clicks on the flatpickr's date, we'll trigger fullcalendar's gotoDate.
         */
        onQuickSwitch(value) {
            this.calendarApi.gotoDate(value.selectedDate);

            this.updateListViewDateRangeField(
                value.selectedDate,
                value.selectedDate,
            );
        },
    }
}
</script>

<template>
    <div>
        <create-event-modal
            ref="createEvent"
            :user-id="config.userId"
            :can-invite="config.canInvite"
            :event-types="config.eventTypes"
            :event-create-url="urls.create"
            :event-create-form-url="urls.createForm"
            :event-modify-form-url="urls.modifyForm"
            :format-form-date="formats.formDate"
            :format-form-datetime="formats.formDatetime"
            :format-display-date="formats.displayDate"
            :format-display-datetime="formats.displayDatetime"
            @eventCreated="addEvent"
        />

        <view-event-modal
            ref="event"
            :event-modify-form-url="urls.modifyForm"
            :event-modify-form-action-url="urls.modifyFormAction"
            :event-delete-url="urls.delete"
            :event-update-url="urls.update"
            :attendance-list-url="urls.attendanceList"
            :attendance-toggle-url="urls.attendanceToggle"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
            :return-url="returnUrl()"
            @eventModified="reloadEvents"
            @eventDeleted="reloadEvents"
        />

        <full-calendar
            ref="fullCalendar"
            :options="calendarOptions"
        >
            <template #eventContent="arg">
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

/* Move times right up to the top
 */
.fc-timegrid-slot.fc-timegrid-slot-label.fc-scrollgrid-shrink {
    vertical-align: top;
}

/* Make each event dig go to the edges of their container columns
 */
.fc-direction-ltr .fc-timegrid-col-events {
    margin: 0
}

/* Make each event div not go all the way to the edge
 */
.fc-timegrid-event.fc-v-event.fc-event.fc-event-start.fc-event-end {
    padding: 0 0.25rem;
}

/* Remove weird left padding which indents the time, but not any wrapping text
 * This might have been added for the list view, but manifests here
 */
.fc-timegrid-event.fc-v-event.fc-event.fc-event-start.fc-event-end .fc-event-title {
    padding-left: 0;
}

/* Indicator line - make it a bit easier to see, and translucent
 */
.fc .fc-timegrid-now-indicator-line {
    border-width: 2px 0 0;
    opacity: 0.75;
}

/* Indicator arrow - make it a bit easier to see
 */
.fc-direction-ltr .fc-timegrid-now-indicator-arrow {
    border-width: 7px 0 7px 8px;
}
</style>
