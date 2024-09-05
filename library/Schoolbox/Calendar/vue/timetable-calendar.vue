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
import TimetableCalendarOptions from "@schoolboxLibrary/Calendar/vue/timetable-calendar-options.vue";
import ViewEventModal from "@schoolboxVue/components/fullcalendar/view-event-modal.vue";
import FlatPickr from "@schoolboxVue/components/fullcalendar/flat-pickr.vue";
import fullCalendarHelper from "@schoolboxVue/components/fullcalendar/mixinFullCalendarHelper";
import CustomisedCalendarEvent from "@schoolboxVue/components/fullcalendar/customised-calendar-event.vue";
import { activate, timeout } from "@schoolboxVue/components/toast/sbxToastEvent";
import ExportModal from "./export-modal.vue";
import FilterModal from "./filter-modal.vue";

// Various URLs that FullCalendar may make use of.
const URL_STATE_CHANGE = "/calendar/changeState";

const URL_EVENT_SOURCE = "/calendar/ajax/full";
const URL_EVENT_UPDATE = "/calendar/ajax/update";
const URL_EVENT_FILTER = "/calendar/ajax/filter";
const URL_EVENT_CREATE = "/calendar/ajax/create";

/* TODO: Events should already know about and come with their own modify link;
 * we shouldn't be inferring and building it out here
 */
const URL_EVENT_CREATE_FORM = "/calendar/event/create";
const URL_EVENT_MODIFY_FORM = "/calendar/event/{eventId}/modify";
const URL_EVENT_MODIFY_FORM_ACTION = "/calendar/modifyForm.php";
const URL_EVENT_DELETE = "/calendar/event/{eventId}/delete";
const URL_EVENT_EXPORT = "/calendar/export.php";

const URL_ATTENDANCE_LIST = "/calendar/attendance/list/";
const URL_ATTENDANCE_TOGGLE = "/service/attendance/setAttendance";

const FORMAT_FORM_DATE = "DD-MM-YYYY";
const FORMAT_FLATPICKR_DATE = "DD/MM/YYYY";
const FORMAT_FORM_DATETIME = "DD-MM-YYYY HH:mm";

// TODO let display formats be configurable
const FORMAT_DISPLAY_DATE = "ddd Do MMM";
const FORMAT_DISPLAY_TIME = "h:mma";
const FORMAT_DISPLAY_DATETIME = "ddd Do MMM, h:mma";
const FORMAT_DISPLAY_WEEK_DAY = "ddd D/M";

// Default event duration is 1 hour, default allday event span is 1 day
const DEFAULT_EVENT_DURATION = 60 * 60 * 1000;
const DEFAULT_ALLDAY_EVENT_DAYS = 1;

/* Viewports smaller than this width will be presumed to be a small viewport
 * such as a phone, vertical tablet, or similar device
 */
const SMALL_VIEWPORT_THRESHOLD = 768;

const EVENT_SOURCE_ID = "events";

export default {
    name: "timetable-calendar",

    components: {
        TimetableCalendarOptions,
        FilterModal,
        ExportModal,
        ViewEventModal,
        FullCalendar,
        FlatPickr,
        CustomisedCalendarEvent,
    },

    mixins: [fullCalendarHelper],

    props: {
        options: {
            type: Object,
            default() {
                return {};
            },
        },
    },

    data() {
        return {
            calendarApi: null, //to access the underlying calendar object
            currentDisplay: null,
            duration: {
                days: 31,
            },
            listViewDateRange: {
                start: null,
                end: null,
            },
            calendarOptions: {},

            /* For deciding which display format to use
             */
            windowWidth: window.innerWidth,

            selectedOption: null,
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
        },
    },

    beforeMount() {
        this.calendarOptions = {
            schedulerLicenseKey: this.config.schedulerLicenseKey,
            plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, adaptivePlugin],
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
                list: {
                    buttonText: "List",
                    duration: this.duration,
                    listDayFormat: (date) => {
                        return dayjs(date.date.array).format(this.config.dateDisplayFormats.dayjsDateLong);
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
                right: "",
            },
            buttonText: {
                today: "Today",
            },
            initialDate: this.config.defaultDate,
            initialView: this.config.defaultView || "dayGridMonth",
            scrollTime: this.config.dateDisplayFormats.calendarStartTime,
            dayMaxEventRows: this.config.eventLimit,
            firstDay: this.config.firstDay,
            eventSources: [
                {
                    id: EVENT_SOURCE_ID,
                    events: this.events,
                },
            ],
            eventDidMount: this.eventRender,
            loading: this.fullCalendarHelper_onLoading,
            eventClick: this.onEventClick,
        };
    },

    mounted() {
        this.$nextTick().then(() => {
            this.calendarApi = this.$refs.fullCalendar.getApi();
            this.calendarApi.setOption("contentHeight", this.fullCalendarHelper_contentHeight());
            this.calendarApi.scrollToTime(this.config.dateDisplayFormats.calendarStartTime);

            /* shanked from https://stackoverflow.com/questions/47219272/how-can-i-use-window-size-in-vue-how-do-i-detect-the-soft-keyboard
             */
            window.addEventListener("resize", this.onResize);
        });

        this.registerListCustomInputFields();

        // Inject calendar icon. Unfortunately, fullcalendar doesn't support injecting our own icons into the custom buttons yet...
        let quickSwitchIcon = document.createElement("a");
        quickSwitchIcon.setAttribute("class", "adtp-btn icon-calendar no-text");
        document.getElementsByClassName("fc-quickSwitchButton-button")[0].appendChild(quickSwitchIcon);

        this.selectedOption = this.options["selectedUserOption"];

        window.onpopstate = (event) => {
            if (event.state === null || event.state.urlDate === null) {
                console.error("Event.state and event.state.urlDate should always be set in window.onPopState()."); // Sanity check.
                // See code pattern for dealing with URL history in https://github.com/alaress/schoolbox/pull/28761.
            }
            const date = dayjs(event.state.urlDate, FORMAT_FORM_DATE, true).toDate();
            this.calendarApi.gotoDate(date);
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeFields(date, null, false);
            this.changeUserAndReloadEvents(event.state.selectedOption, event.state.urlDate, false);
        };

        // Initialise history by setting the current state.
        window.history.replaceState(this.getPageStateForUrlHistory(), "", window.location.href);
    },

    beforeDestroy() {
        window.removeEventListener("resize", this.onResize);
    },

    methods: {
        /**
         * @returns {object} containing the page's state, suitable for restoration in the window.onpopstate() function.
         */
        getPageStateForUrlHistory() {
            return {
                selectedOption: {
                    // Must copy each field explicitly to avoid 'Proxy object could not be cloned' error.
                    userId: this.selectedOption.userId,
                    firstCampusName: this.selectedOption.firstCampusName,
                    formattedName: this.selectedOption.formattedName,
                    yearLevel: this.selectedOption.yearLevel,
                },
                urlDate: this.getCurrentValueOfUrlDate(), // Format DD-MM-YYY (FORMAT_FORM_DATE).
            };
        },

        /**
         * @param {object} newSelectedOption - Object like {userId: number, firstCampusName: string, formattedName: string, yearLevel: string}.
         * @param {?string} newUrlDate - Date in format DD-MM-YYYY (FORMAT_FORM_DATE).
         * @param {boolean} simulateRedirect
         *  - Set this to true if you want a click of the browser 'back' button to return the user to the state before this function was called.
         */
        changeUserAndReloadEvents(newSelectedOption, newUrlDate = null, simulateRedirect = true) {
            if (simulateRedirect) {
                // Push current state to history.
                window.history.pushState(this.getPageStateForUrlHistory(), "", window.location.href);
            }

            // Update state.
            this.selectedOption = newSelectedOption;
            this.config.userId = this.selectedOption.userId;
            this.reloadEvents();

            // Replace current state in history object with the new state (and set the current URL to match that state).
            window.history.replaceState(
                this.getPageStateForUrlHistory(),
                "",
                "/timetable/calendar/" +
                    (newUrlDate === null ? this.getCurrentValueOfUrlDate() : newUrlDate) + // Format DD-MM-YYYY (FORMAT_FORM_DATE).
                    "/" +
                    this.selectedOption.userId,
            );
        },

        /**
         * Change the display format depending on viewport size
         */
        onResize() {
            this.windowWidth = window.innerWidth;

            /* Dynamically change display format
             * https://fullcalendar.io/docs/Calendar-changeView
             */
            if (this.windowWidth < SMALL_VIEWPORT_THRESHOLD) {
                this.calendarApi.changeView("timeGridDay");
            } else {
                this.calendarApi.changeView("timeGridWeek");
            }
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
         * Event source.
         * @see https://fullcalendar.io/docs/events-function
         *
         * @param fetchInfo The time span of the events to fetch
         * @param callback  A function to call with the list of events.
         */
        events(fetchInfo, callback) {
            activate("Retrieving timetable events...");
            const params = {
                start: Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                end: Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                userId: this.config.userId,

                /* This param lets you load the specific set of subscriptions
                 * relevant to the Timetable Calendar
                 */
                timetableCalendar: true,
            };
            fetch(`${URL_EVENT_SOURCE}?${getSearchString(params)}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            })
                .then((response) => response.json())
                .then((response) => {
                    callback(
                        response.filter((v) => {
                            return "start" in v;
                        }),
                    );

                    let slots = this.findSlotBoundaries(response);

                    this.calendarApi.setOption("slotMinTime", slots.slotMinTime);
                    this.calendarApi.setOption("slotMaxTime", slots.slotMaxTime);
                    this.calendarApi.setOption("allDaySlot", slots.allDaySlot);
                    timeout({ text: "Timetable retrieved successfully" });
                })
                .catch((error) => {
                    timeout({ text: "Timetable could not be retrieved", isSuccess: false });
                    console.error(error);
                });
        },

        /**
         * Find the earliest time and the latest time in a set of events
         * Used to configure slotMinTime and slotMaxTime
         *
         * @param data
         */
        findSlotBoundaries(data) {
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
                let start = dayjs(new Date(data[i].start)).format("HH:mm:ss");
                if (null === slotMinTime || slotMinTime > start) {
                    slotMinTime = start;
                }

                /* Remember the latest time we saw
                 */
                let finish = dayjs(new Date(data[i].end)).format("HH:mm:ss");

                if (null === slotMaxTime || slotMaxTime < finish) {
                    slotMaxTime = finish;
                }
            }

            return {
                slotMinTime: slotMinTime ?? "00:00:00",
                slotMaxTime: slotMaxTime ?? "00:00:00",
                allDaySlot: allDaySlot,
            };
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
            /* Filters Modal doesn't exist in the Timetable Calendar special screen
             * Everything always renders
             */
            return true;
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
         * Check if current display is list
         */
        currentViewIsList() {
            return this.currentDisplay === "list";
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
         * Register customised input fields for list view
         */
        registerListCustomInputFields() {
            // Make sure that there's only one of the custom input field
            if (!document.querySelector(".fc-custom-group")) {
                // Prepare input fields
                const container = document.createElement("div");
                const startDate = document.createElement("input");
                const startDateFieldWrapper = document.createElement("div");
                const endDate = document.createElement("input");
                const endDateFieldWrapper = document.createElement("div");
                const updateRangeButton = document.createElement("input");
                const buttonWrapper = document.createElement("div");

                startDateFieldWrapper.setAttribute("class", "small-4 columns");
                endDateFieldWrapper.setAttribute("class", "small-4 columns");
                buttonWrapper.setAttribute("class", "small-4 columns");

                // Start date field
                startDate.setAttribute("id", "start_date");
                startDate.setAttribute("name", "start_date");
                startDate.setAttribute("data-datepicker", "");
                startDate.setAttribute("data-datepicker-buttontext", "");
                startDate.setAttribute(
                    "value",
                    this.listViewDateRange.start
                        ? this.listViewDateRange.start
                        : dayjs(new Date()).format(FORMAT_FLATPICKR_DATE),
                );

                // End date field
                endDate.setAttribute("id", "end_date");
                endDate.setAttribute("name", "end_date");
                endDate.setAttribute("data-datepicker", "");
                endDate.setAttribute("data-datepicker-buttontext", "");
                endDate.setAttribute(
                    "value",
                    this.listViewDateRange.end
                        ? this.listViewDateRange.end
                        : dayjs(new Date()).add(this.duration.days, "days").format(FORMAT_FLATPICKR_DATE),
                );

                // Update button
                updateRangeButton.setAttribute("id", "update_date_range_button");
                updateRangeButton.setAttribute("type", "submit");
                updateRangeButton.setAttribute("value", "Update");

                if (this.currentViewIsList()) {
                    container.setAttribute("class", "fc-custom-group row");
                } else {
                    container.setAttribute("class", "fc-custom-group row hide");
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
                document
                    .querySelector(".fc-toolbar")
                    .getElementsByClassName("fc-toolbar-chunk")[0]
                    .appendChild(container);

                // Register an event listener when update button is clicked.
                document.querySelector("#update_date_range_button").addEventListener("click", () => {
                    if (document.querySelector("#start_date").value && document.querySelector("#end_date").value) {
                        const startDateValue = dayjs(
                            document.querySelector("#start_date").value,
                            FORMAT_FLATPICKR_DATE,
                            true,
                        );
                        const endDateValue = dayjs(
                            document.querySelector("#end_date").value,
                            FORMAT_FLATPICKR_DATE,
                            true,
                        );

                        if (startDateValue.isValid() && endDateValue.isValid()) {
                            this.listViewDateRange.start = document.querySelector("#start_date").value;
                            this.listViewDateRange.end = document.querySelector("#end_date").value;

                            this.duration.days =
                                this.fullCalendarHelper_dateDiff(
                                    startDateValue.format("YYYY-MM-DD"),
                                    endDateValue.format("YYYY-MM-DD"),
                                ) + 1;
                            this.calendarApi.gotoDate(startDateValue.format("YYYY-MM-DD"));
                        }
                    }
                });
            }
        },

        /**
         * Update list view's start and end dates on date navigation
         * Wish we can use v-model rather than doing this but because the input fields are being injected via javascript,
         * I'm not sure if we can use it..
         * @param {Date} start -
         * @param {?Date} end -
         * @param {boolean} simulateRedirect
         *  - Set this to true if you want a click of the browser 'back' button to return the user to the state before this function was called.
         */
        updateListViewDateRangeFields(start, end = null, simulateRedirect = true) {
            if (simulateRedirect) {
                window.history.pushState(this.getPageStateForUrlHistory(), "", window.location.href);
            }

            this.listViewDateRange.start = dayjs(start).format(FORMAT_FLATPICKR_DATE);
            this.listViewDateRange.end = dayjs(end || new Date())
                .add(this.duration.days, "days")
                .format(FORMAT_FLATPICKR_DATE);

            let startDate = this.jQuery("#start_date"),
                endDate = this.jQuery("#end_date");

            // .val() is needed so that we'll have the value visible in the
            // input field.
            // data-default-date is needed so that when flatpickr gets
            // initialised, the selected value will be the same as the one
            // that's provided in the input field.
            // The change event is required so that the date picker popup is
            // updated with the new date.
            startDate
                .val(this.listViewDateRange.start)
                .attr("data-default-date", this.listViewDateRange.start)
                .trigger("change");
            endDate
                .val(this.listViewDateRange.end)
                .attr("data-default-date", this.listViewDateRange.end)
                .trigger("change");

            window.history.replaceState(
                this.getPageStateForUrlHistory(),
                "",
                "/timetable/calendar/" + dayjs(start).format(FORMAT_FORM_DATE) + "/" + this.config.userId,
            );
        },

        /**
         * Additional customisation for prev button to sync flatpickr's selected date with fullCalendar's
         */
        clickPrev() {
            this.calendarApi.prev();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeFields(this.calendarApi.getDate(), this.calendarApi.getDate(), true);
        },

        /**
         * Additional customisation for next button to sync flatpickr's selected date with fullCalendar's
         */
        clickNext() {
            this.calendarApi.next();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeFields(this.calendarApi.getDate(), this.calendarApi.getDate(), true);
        },

        /**
         * Additional customisation for today's button to sync flatpickr's selected date with fullCalendar's
         */
        clickToday() {
            this.calendarApi.today();
            this.$refs.flatPickr.updateActiveDate(this.calendarApi.getDate());
            this.updateListViewDateRangeFields(this.calendarApi.getDate(), true);
        },

        /**
         * Additional customisation for quick month switch button.
         * When the user clicks on the flatpickr's date, we'll trigger fullcalendar's gotoDate.
         */
        onQuickSwitch(value) {
            this.calendarApi.gotoDate(value.selectedDate);

            this.updateListViewDateRangeFields(value.selectedDate, value.selectedDate, true);
        },

        /**
         * Return the date that would be displayed in the URL bar, if the /timetable/calendar/{date}/{userId} route was
         * in use.  There will not always be a date in the URL, because the other route (/timetable/calendar/{userId}
         * may be in use.  The value to return is the Sunday that starts the currently displayed week.
         * @returns {string} in FORMAT_FORM_DATE
         */
        getCurrentValueOfUrlDate() {
            const startDateStr = document.querySelector("#start_date").value;
            let dateObj = null;
            if (startDateStr === null) {
                dateObj = Date(); // Today is the default value for #start_date.
            } else {
                dateObj = dayjs(startDateStr, FORMAT_FLATPICKR_DATE, true).toDate();
            }
            dateObj = this.setDateObjToMostRecentSunday(dateObj);

            return dayjs(dateObj).format(FORMAT_FORM_DATE);
        },

        /**
         * @param {Date} date - Any Date.
         * @returns {Date} - Date for the most recent Sunday on or before date.
         */
        setDateObjToMostRecentSunday(date) {
            date.setDate(date.getDate() - date.getDay());
            return date;
        },
    },
};
</script>

<template>
    <timetable-calendar-options
        :options="options"
        :selected-option="selectedOption"
        @option-selected="changeUserAndReloadEvents"
    />
    <div class="row">
        <div class="small-12 columns">
            <h1>
                Timetable for
                <span data-heading-user-fullname>{{ selectedOption === null ? "" : selectedOption.formattedName }}</span
                ><template v-if="selectedOption?.yearLevel?.length">,</template>
                <span data-heading-user-year>{{ selectedOption === null ? "" : selectedOption.yearLevel }}</span>
            </h1>
        </div>
    </div>
    <div class="row">
        <div class="small-12 island">
            <section>
                <div>
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

                    <full-calendar ref="fullCalendar" :options="calendarOptions">
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
            </section>
        </div>
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
    margin: 0;
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
