<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import FullCalendar from "@fullcalendar/vue3";
import interactionPlugin from "@fullcalendar/interaction";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import adaptivePlugin from "@fullcalendar/adaptive";
import resourceTimelinePlugin from "@fullcalendar/resource-timeline";
import { getSearchString } from "@js/util/queryUtils";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import ViewEventModal from "@schoolboxVue/components/fullcalendar/view-event-modal.vue";
import fullCalendarHelper from "@schoolboxVue/components/fullcalendar/mixinFullCalendarHelper";
import CustomisedCalendarEvent from "@schoolboxVue/components/fullcalendar/customised-calendar-event.vue";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import ViewGroupedEventsModal from "./view-grouped-events-modal.vue";

// Various URLs this calendar can make use of
const URL_ASSESSMENT_EVENT_SOURCE = "/learning/assessment/calendar/events";
const URL_ASSESSMENT_GROUPED_EVENT_SOURCE = "/learning/assessment/calendar/events/grouped";
const URL_EVENT_UPDATE = "/calendar/ajax/update";

/* TODO: Events should already know about and come with their own modify link;
 * we shouldn't be inferring and building it out here
 */
const URL_EVENT_CREATE_FORM = "/calendar/event/create";
const URL_EVENT_MODIFY_FORM = "/calendar/event/{eventId}/modify";
const URL_EVENT_MODIFY_FORM_ACTION = "/calendar/modifyForm.php";
const URL_EVENT_DELETE = "/calendar/event/{eventId}/delete";

const URL_ATTENDANCE_LIST = "/calendar/attendance/list/";
const URL_ATTENDANCE_TOGGLE = "/service/attendance/setAttendance";

const FORMAT_FORM_DATE = "DD-MM-YYYY";
const FORMAT_FORM_DATETIME = "DD-MM-YYYY HH:mm";

// TODO let display formats be configurable
const FORMAT_DISPLAY_DATE = "ddd Do MMM";
const FORMAT_DISPLAY_TIME = "h:mma";
const FORMAT_DISPLAY_DATETIME = "ddd Do MMM, h:mma";
const FORMAT_DISPLAY_WEEK_DAY = "ddd D/M";

const VIEW_RESOURCE_TIMELINE_SEMESTER = "resourceTimelineSemester";
const VIEW_RESOURCE_TIMELINE_WEEK = "resourceTimelineWeek";
const VIEW_DAY_GRID_MONTH = "dayGridMonth";

const RESOURCE_FOLDER_PREFIX = "folder-";
const RESOURCE_EVENT_TYPE_PREFIX = "eventType-";

const MILLISECONDS_PER_DAY = 1000 * 60 * 60 * 24;

export default {
    name: "assessment-calendar",

    components: {
        ViewGroupedEventsModal,
        ViewEventModal,
        FullCalendar,
        RevealModal,
        CustomisedCalendarEvent,
    },

    mixins: [fullCalendarHelper],

    data() {
        return {
            calendarApi: null, //to access the underlying calendar object
            calendarOptions: {},
            resourceTimelineSemester: {},
            resourceTimelineWeek: {},
            selectedFilters: null,
            eventTypeResources: [],
            folderResources: [],
            termStartDate: null,
            termEndDate: null,
            cursor: 0,
            currentBatchCursor: null,
            eventGroups: [],
            defaultBgColour: "#0066b0",
            maxDate: null,
            minDate: null,
            inContext: null,
            eventSources: {
                events: {
                    id: "events",
                    events: this.events,
                },
                groupedEvents: {
                    id: "groupedEvents",
                    events: this.groupedEvents,
                },
            },
        };
    },

    computed: {
        jQuery: () => window.jQuery,
        config: () => window.fullCalendarConfig,
        urls() {
            return {
                createForm: URL_EVENT_CREATE_FORM,
                modifyForm: URL_EVENT_MODIFY_FORM,
                modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
                delete: URL_EVENT_DELETE,
                update: URL_EVENT_UPDATE,
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
        },
    },

    created() {
        this.termStartDate = this.config.termDates.current.start;
        this.termEndDate = this.config.termDates.current.end;
        this.allTerms = this.buildAllTermsList();
        this.eventTypeResources = this.buildEventTypeResourcesList();
        this.inContext = this.config.inContext;
    },

    beforeMount() {
        this.resourceTimelineSemester = {
            type: "resourceTimeline",
            visibleRange: this.determineVisibleRange,
            slotDuration: { weeks: 1 },
            weekNumbers: true,
            slotLabelFormat: [
                (date) => {
                    return dayjs(date.date.array).format("MMM");
                },
                (date) => {
                    return dayjs(date.date.array).format("w");
                },
            ],
            resourceAreaWidth: "15%",
            filterResourcesWithEvents: true,
            validRange: {
                start: this.minDate,
                end: this.maxDate,
            },
            resourceAreaHeaderContent: "Week",
            slotMinWidth: "50",
            titleFormat: (date) => {
                return this.fullCalendarHelper_titleFormatWeek(date);
            },
        };
        this.resourceTimelineWeek = {
            dayHeaderFormat: (date) => {
                return dayjs(date.date.array).format(this.formats.displayWeekDay);
            },
            duration: { weeks: 1 },
            slotDuration: { days: 1 },
            slotLabelFormat: (date) => {
                return dayjs(date.date.array).format("ddd DD");
            },
            resourceAreaWidth: "15%",
            filterResourcesWithEvents: true,
            titleFormat: (date) => {
                return this.fullCalendarHelper_titleFormatWeek(date);
            },
        };
        this.calendarOptions = {
            schedulerLicenseKey: this.config.schedulerLicenseKey,
            plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, resourceTimelinePlugin, adaptivePlugin],
            views: {
                resourceTimelineSemester: this.resourceTimelineSemester,
                resourceTimelineWeek: this.resourceTimelineWeek,
            },
            initialView: "resourceTimelineSemester",
            weekText: "''",
            eventTimeFormat: (date) => {
                return this.fullCalendarHelper_eventTimeFormat(date);
            },
            headerToolbar: {
                left: "customToday,customPrev,customNext",
                center: "title",
                right: "resourceTimelineSemester,customDayGridMonth,customResourceTimelineWeek",
            },
            buttonText: {
                today: "Today",
            },
            customButtons: {
                customNext: {
                    icon: "chevron-right",
                    click: this.clickNext,
                },
                customPrev: {
                    icon: "chevron-left",
                    click: this.clickPrev,
                },
                customToday: {
                    text: "Today",
                    click: this.clickToday,
                },
                customDayGridMonth: {
                    dayMaxEventRows: this.config.eventLimit,
                    text: "Month",
                    click: this.clickMonthView,
                },
                customResourceTimelineWeek: {
                    text: "Week",
                    click: this.clickWeekView,
                },
                resourceTimelineSemester: {
                    text: "Semester",
                    click: this.clickSemesterView,
                },
            },
            scrollTime: this.config.dateDisplayFormats.calendarStartTime,
            eventDidMount: this.eventRender,
            defaultTimedEventDuration: "00:01:00",
            resources: this.resources,
            firstDay: "1",
            initialDate: this.termStartDate,
            viewClassNames: this.viewClassNames,
            weekNumberCalculation: "ISO",
            loading: this.fullCalendarHelper_onLoading,
            eventClick: this.onEventClick,
            dateClick: this.onDateClick,
            datesSet: this.datesSet,
            resourceOrder: "title", // sort resources(events) by the event title
        };
    },

    mounted() {
        this.$nextTick().then(() => {
            this.calendarApi = this.$refs.fullCalendar.getApi();
            this.calendarApi.setOption("contentHeight", this.fullCalendarHelper_contentHeight());
        });
        document.addEventListener("assessment-calendar__onFilterChange", this.onFilterChange);
    },

    methods: {
        /**
         * Event source.
         * @see https://fullcalendar.io/docs/events-function
         *
         * @param fetchInfo       The time span of the events to fetch
         * @param successCallback A function to call with the list of events.
         */
        events(fetchInfo, successCallback) {
            // No filters loaded yet? Render nothing.
            // The selected filters get populated by `onFilterChange`:
            // this is called by the filter's `filterContentLoaded` trigger.
            if (this.selectedFilters === null) {
                successCallback([]);
                return;
            }
            activate("Retrieving calendar events...");
            // AJAX params: the same for all requests
            const ajaxParams = {
                start: Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                end: Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                filters: this.selectedFilters,
            };
            fetch(`${URL_ASSESSMENT_EVENT_SOURCE}?${getSearchString(ajaxParams)}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            })
                .then((response) => response.json())
                .then((response) => {
                    timeout({ text: "Events retrieved successfully" });
                    // Assign event grouping and resourceIds into every event
                    response = response.map((element) => {
                        element.resourceId = this.buildResourceIdFromEvent(element);
                        return element;
                    });

                    // Add new resources
                    response.forEach((element) => {
                        if (!this.folderResources.some((el) => el.id === element.data?.meta?.folderId)) {
                            this.folderResources.push({
                                id: element.resourceId,
                                title: element.data.meta.folderName,
                                eventBackgroundColor: element.data.meta.colour || this.defaultBgColour,
                            });
                        }
                    });

                    // Reload resources here too
                    this.calendarApi.refetchResources();

                    // For FullCalendar to correctly render the event colour for time-based events,
                    // the event must have a `display` property set to `block`, rather then the default of 'auto'
                    // This appears to relate to https://github.com/fullcalendar/fullcalendar/issues/5533
                    response.forEach((e) => {
                        e.display = "block";
                    });

                    // Filter out events which don't start
                    successCallback(response.filter((v) => "start" in v));
                })
                .catch((error) => {
                    timeout({ text: "Events could not be retrieved", isSuccess: false });
                    console.error(error);
                });
        },

        groupedEvents(fetchInfo, successCallback) {
            // No filters loaded yet? Render nothing.
            // The selected filters get populated by `onFilterChange`:
            // this is called by the filter's `filterContentLoaded` trigger.
            if (this.selectedFilters === null) {
                successCallback([]);
                return;
            }
            // AJAX params: the same for all requests
            const ajaxParams = {
                start: Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                end: Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                filters: this.selectedFilters,
                inContext: this.inContext,
            };
            fetch(`${URL_ASSESSMENT_GROUPED_EVENT_SOURCE}?${getSearchString(ajaxParams)}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            })
                .then((response) => response.json())
                .then((response) => {
                    // Assign event grouping and resourceIds into every event
                    response = response.map((element) => {
                        element.resourceId = this.buildResourceIdFromEvent(element);
                        return element;
                    });
                    // Add new resources
                    response.forEach((element) => {
                        if (!this.folderResources.some((el) => el.id === element.data?.meta?.folderId)) {
                            this.folderResources.push({
                                id: element.resourceId,
                                title: element.data.meta.folderName,
                                eventBackgroundColor: element.data.meta.colour || this.defaultBgColour,
                            });
                        }
                    });
                    this.folderResources.sort((a, b) => {
                        if (typeof a?.title === "string" && typeof b?.title === "string") {
                            return a.title.localeCompare(b.title, "en", { numeric: true, sensitivity: "accent" });
                        }
                        return 0;
                    });

                    // Reload resources here too
                    this.calendarApi.refetchResources();

                    // Filter out events which don't start
                    successCallback(response.filter((v) => "start" in v));
                })
                .catch((error) => {
                    console.error(error);
                });
        },

        /**
         * Resource source.
         * @see https://fullcalendar.io/docs/resources-function
         *
         * @param fetchInfo       Unused, but contains start/end of period
         *                        to fetch resources for.
         * @param successCallback A callback that is called with the list of
         *                        resources.
         */
        resources(fetchInfo, successCallback) {
            successCallback([
                {
                    title: "Events",
                    children: this.eventTypeResources,
                    eventAllow: false,
                },
                {
                    title: "Activities",
                    children: this.folderResources,
                    eventAllow: false,
                },
            ]);
        },

        /**
         * Event render callback: manually tweaks the event's rendering, and
         * can also conditionally show/hide the event.
         * @see https://fullcalendar.io/docs/event-render-hooks
         *
         * @param eventRenderInfo
         * @return {boolean}
         */
        eventRender(eventRenderInfo) {
            const eventGrouping = eventRenderInfo.event.extendedProps.data.meta.eventGrouping;
            const element = eventRenderInfo.el;

            if (!this.eventGroups.includes(eventGrouping)) {
                this.eventGroups.push(eventGrouping);
            }

            if (eventRenderInfo.event.extendedProps.data.placeholder) {
                element.setAttribute("event-group-id-placeholder", eventGrouping);
                element.classList.add("placeholder");
                element.classList.add("fc-more");
            } else {
                if (this.currentViewIsSemester()) {
                    element.style.width = "auto";
                }
                element.setAttribute("event-group-id", eventGrouping);
                element.setAttribute("event-id", eventRenderInfo.event.extendedProps.data.meta.eventId);
                element.setAttribute("event-group-date", dayjs(eventRenderInfo.event.start).format("MMMM DD, YYYY"));
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
            this.currentEvent = eventInfo.event;
            if (eventInfo.event.source.id === "groupedEvents") {
                this.$refs.groupedEvents.open(eventInfo.event);
            } else {
                this.$refs.event.open(eventInfo.event);
            }
        },

        /**
         * Callback for when the filters for this calendar are updated.
         *
         * Fired from outer space, currently from: library/Schoolbox/Learning/Assessment/Calendar/templates/index.twig
         *
         * @param {object} selectedFilters The filters that were selected
         */
        onFilterChange(event) {
            this.selectedFilters = event.detail.selectedFilters;
            this.calendarApi.refetchEvents();
            this.inContext = false;
        },

        /**
         * Get terms duration based on start and finish date
         * @returns {*}
         */
        getTermDateDurations() {
            return this.fullCalendarHelper_dateDiff(this.termStartDate, this.termEndDate);
        },

        /**
         * Callback for when the calendar view's class list changes (as it does
         * when a new view is selected): preserve the newly selected view for
         * this user.
         *
         * Changed from `viewDidMount`, as that only fires when a new view is
         * added to the DOM: the day and week views share the same view, thus
         * this event didn't fire when switching between day and week view.
         *
         * @see https://fullcalendar.io/docs/view-render-hooks
         * @see https://github.com/fullcalendar/fullcalendar/issues/5543 for
         *      discussion of `viewDidMount` vs. `viewClassNames`
         */
        viewClassNames() {
            const calendarApi = this.$refs.fullCalendar.getApi(); //runs before the nextTick, so need api here
            const eventSource = calendarApi.getEventSourceById("events");
            const groupedEventSource = calendarApi.getEventSourceById("groupedEvents");

            this.destroyPopOver();

            if (this.currentViewIsSemester()) {
                this.redrawWeekNumbers();

                // Add grouped events source if it's not there:
                // remove standard source if it is
                if (eventSource) {
                    eventSource.remove();
                }
                if (!groupedEventSource) {
                    calendarApi.addEventSource(this.eventSources.groupedEvents);
                }
            } else {
                // Add standard events source if it's not there:
                // remove grouped source if it is
                if (groupedEventSource) {
                    groupedEventSource.remove();
                }
                if (!eventSource) {
                    calendarApi.addEventSource(this.eventSources.events);
                }
            }

            this.maxDate = this.getMaxTermDate();
            this.minDate = this.getMinTermDate();
        },

        /**
         * Customise next button behaviour for semester view. Clicking next button while on semester view will
         * navigate into the next available semester
         */
        clickNext() {
            if (this.currentViewIsSemester()) {
                // Figure out which term I'm in: go to the next one
                let termIndex = this.findTermIndex(this.convertDateToDateString(this.calendarApi.getDate()));
                if (this.allTerms[termIndex + 1]) {
                    // Go to the midpoint of the term, as we can reasonably
                    // assume that will not overlap the view for another
                    // term.
                    this.calendarApi.gotoDate(this.allTerms[termIndex + 1].mid);
                    this.redrawWeekNumbers();
                }
            } else {
                this.calendarApi.next();
            }
        },

        /**
         * Customise previous button behaviour for semester view. Clicking previous button while on semester view will
         * navigate into the previous available semester
         */
        clickPrev() {
            if (this.currentViewIsSemester()) {
                // Figure out which term I'm in: go to the previous one
                let termIndex = this.findTermIndex(this.convertDateToDateString(this.calendarApi.getDate()));
                if (this.allTerms[termIndex - 1]) {
                    // As with clickNext():
                    // Go to the midpoint of the term, as we can reasonably
                    // assume that will not overlap the view for another
                    // term.
                    this.calendarApi.gotoDate(this.allTerms[termIndex - 1].mid);
                    this.redrawWeekNumbers();
                }
            } else {
                this.calendarApi.prev();
            }
        },

        /**
         * Customise the behaviour for month view button. With this in place, once the user clicked on month button,
         * the initial month view will go to the current date
         */
        clickMonthView() {
            this.switchView(VIEW_DAY_GRID_MONTH, true);
        },

        /**
         * Customise the behaviour for week view button. With this in place, once the user clicked on month button,
         * the initial month view will go to the current date
         */
        clickWeekView() {
            this.switchView(VIEW_RESOURCE_TIMELINE_WEEK, true);
        },

        /**
         * Customise the behaviour for week view button. With this in place, once the user clicked on month button,
         * the initial month view will go to the current date
         */
        clickSemesterView() {
            this.switchView(VIEW_RESOURCE_TIMELINE_SEMESTER, true);
        },

        /**
         * Custom method for switching calendar view
         * @param {String}  viewType
         * @param {Boolean} updateDate
         */
        switchView(viewType, updateDate) {
            this.updateSelectedCustomButton(viewType);
            /**
             * Since clicking of month and week view needs to be customised, so that initial loading of month or week
             * view goes to the current date.
             *
             * We need to do this trick so that we'll be able to show the loading pop-up.
             * And without this, you'll notice that there's a delay before the switch happens without any indicator
             * that the calendar is still being rendered in the background.
             *
             * You'll be able to replicate it by just removing all the lines below except for line 519 and 522.
             */
            new Promise((resolve) => {
                this.fullCalendarHelper_onLoading(true);
                setTimeout(() => resolve(1), 1000);
            })
                .then((result) => {
                    // Semester view, will just use the term start date that's set on page load
                    if (updateDate) {
                        if (viewType === VIEW_RESOURCE_TIMELINE_SEMESTER) {
                            this.calendarApi.gotoDate(this.termStartDate);
                        } else {
                            //otherwise use current date
                            this.calendarApi.gotoDate(this.convertDateToDateString(new Date()));
                        }
                    }

                    this.calendarApi.changeView(viewType);

                    return result;
                })
                .then((result) => {
                    this.viewClassNames();
                    this.fullCalendarHelper_onLoading(false);
                    return result;
                });
        },

        /**
         * As this calendar uses FullCalendars Custom Buttons for Month and Week views, the active class styling doesn't appear to be
         * automatically applied like it is for standard buttons. So we currently need to apply manually in this instance.
         * Without this ths Month and Week view buttons don't appear with active styling like they do in the other calendars.
         * @param {String}  viewType
         */
        updateSelectedCustomButton(viewType) {
            this.$el.querySelectorAll(".fc-button-primary").forEach((element) => {
                element.classList.remove("fc-button-active");
            });
            const activeButtonSelectors = {
                //use the existing viewType to target the buttons, FC applies a unique custom class to each button
                [VIEW_RESOURCE_TIMELINE_SEMESTER]: ".fc-resourceTimelineSemester-button",
                [VIEW_DAY_GRID_MONTH]: ".fc-customDayGridMonth-button",
                [VIEW_RESOURCE_TIMELINE_WEEK]: ".fc-customResourceTimelineWeek-button",
            };
            this.$el.querySelector(activeButtonSelectors[viewType]).classList.add("fc-button-active");
        },

        /**
         * Customise today button behaviour for semester view.
         *
         * Clicking today button while on semester view will navigate to
         * today, only if today is in a semester.
         */
        clickToday() {
            if (this.currentViewIsSemester()) {
                // Figure out if today is in a term before going to it
                let termIndex = this.findTermIndex(this.convertDateToDateString(new Date()));
                if (this.allTerms[termIndex]) {
                    this.calendarApi.today();
                    this.redrawWeekNumbers();
                }
            } else {
                this.calendarApi.today();
            }
        },

        /**
         * Navigate through the dates via the cursor
         */
        getStartDateByCursor() {
            if (this.cursor === 0) {
                return this.config.termDates.current?.start;
            } else if (this.cursor < 0) {
                return this.config.termDates.past[Math.abs(this.cursor) - 1]?.start;
            } else {
                return this.config.termDates.future[Math.abs(this.cursor) - 1]?.start;
            }
        },

        /**
         * Get the latest available term end date for fullCalendar's validRange config.
         * @see: https://fullcalendar.io/docs/validRange
         */
        getMaxTermDate() {
            const termStartDates = Object.values(this.config.termDates)
                .flat()
                .map((row) => {
                    return new Date(row.end);
                });
            return dayjs(new Date(Math.max.apply(null, termStartDates))).format("YYYY-MM-DD");
        },

        /**
         * Get the earliest available term start date for fullCalendar's validRange config.
         * @see: https://fullcalendar.io/docs/validRange
         */
        getMinTermDate() {
            const termStartDates = Object.values(this.config.termDates)
                .flat()
                .map((row) => {
                    return new Date(row.start);
                });

            return dayjs(new Date(Math.min.apply(null, termStartDates))).format("YYYY-MM-DD");
        },

        /**
         * As much as possible, destroy the popover holder whenever it's created on every calendar transitions.
         */
        destroyPopOver() {
            var popOver = document.getElementById("popOver");

            // Always destroy the current popover, as there should only be one.
            if (popOver) {
                popOver.parentNode.removeChild(popOver);
            }
        },

        onDateClick(dateClickInfo) {
            if (dateClickInfo.view.type === VIEW_RESOURCE_TIMELINE_SEMESTER) {
                this.destroyPopOver();
            }
        },

        redrawWeekNumbers() {
            //@TODO - is this still needed?
            // document.querySelectorAll('.fc-time-area .fc-content tr')[1].querySelectorAll('.fc-cell-text').forEach((element, index) => {
            //     element.innerHTML = index + 1;
            // });
        },

        /**
         * Callback when a date is rendered (i.e. the view or viewed date
         * changes).
         *
         * This callback enables/disabled prev/next/today buttons based on
         * whether they're available.
         */
        datesSet(datesRenderInfo) {
            let todayButton = this.$el.querySelector("button.fc-customToday-button"),
                nextButton = this.$el.querySelector("button.fc-customNext-button"),
                prevButton = this.$el.querySelector("button.fc-customPrev-button"),
                start = datesRenderInfo.view.currentStart,
                end = datesRenderInfo.view.currentEnd,
                now = new Date();

            // Should the Today button be enabled?
            todayButton.disabled = now >= start && now < end;

            // What about next/prev?
            if (this.currentViewIsSemester()) {
                // Figure out the midpoint between the start and end of the
                // view, and use that to determine which term the calendar
                // is displaying.
                // That's because the start of the view may overlap another
                // term (see `determineVisibleRange`)
                let startMoment = dayjs(start),
                    endMoment = dayjs(end),
                    diff = endMoment.diff(startMoment, "day"),
                    midMoment = dayjs(start).add(Math.ceil(diff / 2), "day"),
                    currentIndex = this.findTermIndex(midMoment.format("YYYY-MM-DD"));

                // Next:  disable if there is no next term
                // Prev:  disable if there is no prev term
                // Today: disable if today isn't within a term
                nextButton.disabled = currentIndex === this.allTerms.length - 1;
                prevButton.disabled = currentIndex === 0;
                if (!todayButton.disabled) {
                    let todayIndex = this.findTermIndex(dayjs(now).format("YYYY-MM-DD"));
                    todayButton.disabled = todayIndex === -1;
                }
            } else {
                nextButton.disabled = false;
                prevButton.disabled = false;
            }
        },

        /**
         * Check if the current calendar view is in semester
         * @returns {boolean}
         */
        currentViewIsSemester() {
            return this.$refs.fullCalendar.getApi().view.type === VIEW_RESOURCE_TIMELINE_SEMESTER;
        },

        /**
         * Check if the current calendar view is in week
         * @returns {boolean}
         */
        currentViewIsWeek() {
            return this.$refs.fullCalendar.getApi().view.type === VIEW_RESOURCE_TIMELINE_WEEK;
        },

        /**
         * Determine which visible range is shown on the semester view.
         *
         * The semester view shows an entire semester, expanded out to
         * include the start/end of the week that it starts/ends on:
         * for example, if the semester starts on a Tuesday and finishes on
         * a Friday, the start will be set to the Monday before the term's
         * start and the end to the Monday after the term's end.
         *
         * This ensures that no events are left off the calendar because
         * they fall within a week that the calendar view wouldn't render.
         *
         * @param currentDate A Date object matching the date to show
         * @return {*} An object which can be passed to FullCalendar's
         *             `visibleRange`, or null if if found nothing.
         */
        determineVisibleRange(currentDate) {
            // Find the range in the list of terms.
            let dateString = this.convertDateToDateString(currentDate),
                termIndex = this.findTermIndex(dateString);

            // Ensure that visible range starts on Monday (round down),
            // and ends on Monday (round up), all without using Moment
            // (Not initialized on initial calendar load)
            if (this.allTerms[termIndex]) {
                let startDate = new Date(this.allTerms[termIndex].start),
                    endDate = new Date(this.allTerms[termIndex].end),
                    startDay = startDate.getDay() || 7,
                    endDay = endDate.getDay() || 7;

                // Round start down to previous Monday
                if (startDay !== 1) {
                    startDate.setHours(-24 * (startDay - 1));
                }

                // Round end up to next Monday
                if (endDay !== 1) {
                    endDate.setHours(24 * (8 - endDay));
                }

                return {
                    start: startDate,
                    end: endDate,
                };
            }

            return null;
        },

        /**
         * Builds the list of all terms passed into `this.config.termDates`
         * into a single array, ordered by start then end date.
         *
         * @return {Array}
         */
        buildAllTermsList() {
            return this.config.termDates.past
                .concat([this.config.termDates.current], this.config.termDates.future)
                .sort((a, b) => {
                    if (a.start < b.start) {
                        return -1;
                    }
                    if (a.start > b.start) {
                        return 1;
                    }
                    if (a.end < b.end) {
                        return -1;
                    }
                    if (a.end > b.end) {
                        return 1;
                    }
                    return 0;
                })
                .map((term) => {
                    let start = new Date(term.start),
                        mid = new Date(term.start),
                        end = new Date(term.end);

                    // Extend the end date by one day, as FullCalendar dates
                    // are exclusive.
                    end.setDate(end.getDate() + 1);

                    // Add a midpoint to the terms list as well.
                    // This is the date the calendar will actually go to, as
                    // when we query the calendar's date in `datesRender` we
                    // can reasonably assume it doesn't overlap the view
                    // window for another term.
                    mid.setDate(mid.getDate() + Math.ceil((end - start) / MILLISECONDS_PER_DAY / 2));
                    term.mid = this.convertDateToDateString(mid);
                    term.end = this.convertDateToDateString(end);
                    return term;
                });
        },

        /**
         * Convert a Date object to a `YYYY-MM-DD` format string without
         * MomentJS.
         *
         * Required because in this component's  `create` method, the
         * calendar and thus MomentJS won't yet be initialized.
         *
         * @param {Date} date
         *
         * @return {string}
         */
        convertDateToDateString(date) {
            // Need to feed the date in as UTC because toISOString outputs
            // date in UTC.
            let actualDate = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            return actualDate.toISOString().slice(0, 10);
        },

        /**
         * Builds the list of all event type resources, based on
         * `this.config.eventTypes`.
         *
         * @return {Array}
         */
        buildEventTypeResourcesList() {
            return this.config.eventTypes.map((a) => {
                return {
                    id: RESOURCE_EVENT_TYPE_PREFIX + String(a.id),
                    title: a.type,
                    eventBackgroundColor: a.color,
                };
            });
        },

        /**
         * Build a resource ID out of the event.
         *
         * @param {object} event The event to be rendered
         *
         * @return {string|null} The resource ID, or null if it has none
         */
        buildResourceIdFromEvent(event) {
            if (event?.data?.meta?.folderId) {
                return RESOURCE_FOLDER_PREFIX + String(event.data.meta.folderId);
            }
            if (event?.data?.meta?.eventTypeId) {
                return RESOURCE_EVENT_TYPE_PREFIX + String(event.data.meta.eventTypeId);
            }
            return null;
        },

        /**
         * Find the index of the term within `this.termDates` which contains
         * `dateString`.
         *
         * @param dateString A date to find the index for, in "YYYY-MM-DD"
         *                   format
         * @return {Number} The index of the term within `this.termDates`,
         *                  or -1 if not found.
         */
        findTermIndex(dateString) {
            for (let i in this.allTerms) {
                if (!this.allTerms.hasOwnProperty(i)) {
                    continue;
                }
                let term = this.allTerms[i];
                if (dateString >= term.start && dateString < term.end) {
                    return Number(i);
                }
            }
            return -1;
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
         * Go to the week view for a particular date.
         *
         * Listens to the "eventViewWeek" event from the grouped events
         * modal.
         *
         * @param date
         */
        viewWeek(date) {
            this.calendarApi.gotoDate(date);
            this.switchView(VIEW_RESOURCE_TIMELINE_WEEK, false);
        },
    },
};
</script>

<template>
    <div>
        <view-event-modal
            ref="event"
            return-to="assessmentCalendar"
            :event-create-form-url="urls.createForm"
            :event-modify-form-url="urls.modifyForm"
            :event-modify-form-action-url="urls.modifyFormAction"
            :event-delete-url="urls.delete"
            :event-update-url="urls.update"
            :attendance-list-url="urls.attendanceList"
            :attendance-toggle-url="urls.attendanceToggle"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
            @eventModified="reloadEvents"
            @eventDeleted="reloadEvents"
        />
        <view-grouped-events-modal
            ref="groupedEvents"
            :format-display-date="formats.displayDate"
            :format-display-time="formats.displayTime"
            :format-display-datetime="formats.displayDatetime"
            @eventViewWeek="viewWeek"
        />
        <full-calendar ref="fullCalendar" :options="calendarOptions">
            <template v-slot:eventContent="arg">
                <customised-calendar-event :content="arg" />
            </template>
        </full-calendar>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/fullcalendar/styles/calendar";

// Please forgive me...

.fc-resourceTimelineSemester-view .fc-timeline-event .fc-title-wrap {
    text-align: center !important;
}

.fc-resourceTimelineSemester-view .fc-timeline-event.placeholder .fc-title-wrap {
    padding: 5px;
    font-size: 0.8rem;
    line-height: 1.7;
}

.placeholder .fc-title {
    padding-left: 0 !important;
    margin-left: 0 !important;
}

.fc-direction-ltr .fc-timeline-event .fc-title {
    padding-left: 3px !important;
    margin-left: 0 !important;
}

.fc-timeline-event,
.fc-day-grid-event {
    border: 0 !important;
}

.fc-event {
    margin-bottom: 1px;
}

.fc-event .fc-title,
.fc-event .fc-title:hover {
    color: #fff !important;
}

.fc-resourceTimelineSemester-view .fc-direction-ltr .fc-timeline-event {
    margin-right: 0 !important;
}

.fc-resourceTimelineSemester-view a.fc-more {
    margin: 0 !important;
}

.fc-resourceTimelineSemester-view span.fc-title.fc-sticky {
    left: 0 !important;
    width: 100% !important;
}

.fc-timeline-event-div {
    padding-bottom: 2px !important;
}

.fc-resourceTimelineSemester-view .fc-event-container > .fc-timeline-event {
    position: absolute !important;
    top: 1px !important;
    height: 31px !important;
}

.fc-resourceTimelineSemester-view .fc-event-container .fc-timeline-event:not(.placeholder),
.fc-dayGridMonth-view .placeholder,
.fc-resourceTimelineWeek-view .placeholder {
    display: none;
    max-height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

.fc-resourceTimelineSemester-view .fc-resource-area .fc-widget-content div {
    height: 35px !important;
}

.fc-resourceTimelineSemester-view .fc-time-area .fc-rows .fc-widget-content div {
    height: 35px !important;
}

.fc-resourceTimelineSemester-view .fc-head tr td:first-child .fc-widget-header div {
    text-align: right !important;
    padding-top: 0.3rem;
}

.fc-time {
    color: #fff;
}

.fc-resourceTimelineSemester-view span.fc-cell-text.fc-sticky {
    left: 0 !important;
}

.fc-resourceTimelineSemester-view .fc-widget-header .fc-cell-content {
    text-align: center !important;
}

.fc-dayGridMonth-view a.fc-timeline-event,
.fc-resourceTimelineWeek-view a.fc-timeline-event {
    width: auto !important;
}

.fc-theme-standard .fc-popover .fc-header .fc-close {
    margin: 0 !important;
}

.fc-icon-x::before {
    display: none !important;
    content: none !important;
}

.fc-icon-x::after {
    color: #000 !important;
    font-size: 1.5rem !important;
    top: 0 !important;
    right: 1px !important;
    line-height: 0 !important;
}

a.fc-not-start .fc-title-wrap .fc-title {
    padding-left: 8px !important;
}

.fc-timeline-event.fc-not-start::before,
.fc-timeline-event.fc-not-end::after {
    content: none;
}
</style>
