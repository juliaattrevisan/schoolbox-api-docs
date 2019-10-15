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
    import CreateEventModal           from './createEventModal';
    import ExportModal                from "./exportModal";
    import FilterModal                from "./filterModal";
    import axios                      from 'axios'
    import qs                         from "qs";
    import FullCalendarHelper         from "@schoolbox/fullcalendar/helper";

    // Various URLs that FullCalendar may make use of.
    const URL_STATE_CHANGE = '/calendar/changeState';

    const URL_EVENT_SOURCE = '/calendar/ajax/full';
    const URL_EVENT_UPDATE = '/calendar/ajax/update';
    const URL_EVENT_FILTER = '/calendar/ajax/filter';
    const URL_EVENT_CREATE = '/calendar/ajax/create';
    const URL_EVENT_MODIFY_FORM = '/calendar/modify.php';
    const URL_EVENT_MODIFY_FORM_ACTION = '/calendar/modifyForm.php';
    const URL_EVENT_EXPORT = '/calendar/export.php';

    const URL_ATTENDANCE_LIST = '/calendar/attendance/list/';
    const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';

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
            ExportModal,
            CreateEventModal,
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
                currentEvent: {},
                newEvent: {},
                currentDisplay: null,
                duration: 10,
                listViewDateRange: {
                    start: null,
                    end: null,
                }
            }
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
            urls() {
                return {
                    create: URL_EVENT_CREATE,
                    modifyForm: URL_EVENT_MODIFY_FORM,
                    modifyFormAction: URL_EVENT_MODIFY_FORM_ACTION,
                    update: URL_EVENT_UPDATE,
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
                    URL_EVENT_SOURCE,
                    {
                        headers: {
                            'X-Requested-With' : 'XMLHttpRequest'
                        },
                        params: {
                            start:  Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                            end:    Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                            userId: self.config.userId,
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
             * Callback for when the calendar view changes: preserve the
             * newly selected view for this user.
             * @see https://fullcalendar.io/docs/viewSkeletonRender
             *
             * @param info
             */
            viewSkeletonRender(info) {

                let self = this,
                    customGroup = document.querySelector('.fc-custom-group');

                self.currentDisplay = info.view.type;

                document.querySelectorAll('.fc-toolbar .fc-left .fc-button-group, .fc-toolbar .fc-center').forEach(function (element) {
                    if (self.currentViewIsList()) {
                        element.classList.add('hide');
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

                if (self.currentViewIsList()) {
                    self.registerListCustomInputFields();

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
             * @see https://fullcalendar.io/docs/eventRender
             *
             * @param eventRenderInfo
             * @return {boolean}
             */
            eventRender(eventRenderInfo) {
                if (!this.helper.eventRender(eventRenderInfo)) {
                    return false;
                }


                // Extra condition for rendering events on this calendar:
                // All of the event's attributes must match at least one
                // selected filter: if it doesn't, don't render it.
                let self            = this,
                    calEvent        = eventRenderInfo.event,
                    eventAttributes = {},
                    isEventRendered = true;

                // Build a list of filters from the event
                calEvent.classNames.forEach(function (className) {
                    let match = className.match(/^([a-z]+)(\d+)/i);
                    if (match) {
                        let attributeGroup = match[1],
                            id             = Number(match[2]);
                        if (!eventAttributes[attributeGroup]) {
                            eventAttributes[attributeGroup] = [];
                        }
                        eventAttributes[attributeGroup].push(id);
                    }
                });


                // All of the event's attributes must match at least one
                // selected filter.
                // If it doesn't, don't render it.
                Object.keys(eventAttributes).forEach(function (attributeGroup) {
                    let ids                = eventAttributes[attributeGroup],
                        eventMatchesFilter = false;
                    ids.forEach(function (id) {
                        // Check id against the list of selected filters.
                        let keyspace = [];
                        switch (attributeGroup) {
                            case 'source':
                                // Sources have multiple IDs per selection:
                                // this event needs to only match one of them.
                                keyspace = (self.config.selectedFilters?.source || []).flat();
                                break;

                            case 'type':
                                keyspace = self.config.selectedFilters?.eventType || [];
                                break;

                            case 'group':
                                keyspace = self.config.selectedFilters?.group || [];
                                break;

                            case 'campus':
                                keyspace = self.config.selectedFilters?.campus || [];
                                break;

                            default:
                                // Ignore this one
                                return;
                        }
                        if (keyspace.includes(id)) {
                            eventMatchesFilter = true;
                        }
                    });
                    if (!eventMatchesFilter) {
                        isEventRendered = false;
                    }
                });

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
                let [firstEventType] = this.config.eventTypes;
                this.newEvent = {
                    date:        dateClickInfo.date,
                    allDay:      dateClickInfo.allDay,
                    title:       '',
                    detail:      '',
                    type:        firstEventType ? firstEventType.id : null,
                };
                this.$refs.createEvent.open();
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
                this.currentEvent = eventInfo.event;
                this.$refs.event.open();
            },

            /**
             * Resize event callback: persist the change to the event's start
             * and end dates.
             * @see https://fullcalendar.io/docs/eventResize
             *
             * @param eventResizeInfo
             */
            onEventResize(eventResizeInfo) {
                // TODO this needs to consider events in series
                let event = eventResizeInfo.event,
                    data  = {
                        eventId: event?.extendedProps?.data?.meta?.eventId,
                        resize:  true,
                        start:   this.toMoment(event.start).format(),
                        end:     this.toMoment(event.end).format(),
                    };
                // Apply and then remove the "loading" class
                axios.post(URL_EVENT_UPDATE, qs.stringify(data));
            },

            /**
             * Drop event callback: persist the change to the event's start
             * and end dates.
             * @see https://fullcalendar.io/docs/eventDrop
             *
             * @param eventDropInfo
             */
            onEventDrop(eventDropInfo) {
                let event = eventDropInfo.event,
                    delta = toDuration(eventDropInfo.delta),
                    data  = {
                        eventId:     event?.extendedProps?.data?.meta?.eventId,
                        minuteDelta: delta.asMinutes(),
                        allDay:      event.allDay,
                    };
                // Apply and then remove the "loading" class
                axios.post(URL_EVENT_UPDATE, qs.stringify(data));
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
             * Is the given event a task or not? This affects how it's rendered.
             *
             * @param event
             * @return {boolean}
             */
            isTask(event) {
                // TODO this method should be unnecessary
                // (for the same reason as `onLoading`)
                return this.helper.isTask(event);
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
             * Callback for when the filters have been updated: rerender events
             * (thus hiding events which don't match the new filters) and
             * persist the filters selected.
             *
             * @param selectedFilters
             */
            updateSelectedFilters(selectedFilters) {
                // The selected filters in config are read-only and passed by reference.
                // TODO pass it by value into the filter modal, and update in this method
                this.$refs.fullCalendar.getApi().rerenderEvents();
                axios.post(URL_EVENT_FILTER, qs.stringify({ filters: this.config.selectedFilters, }))
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
             * Register customised input fields for list view
             */
            registerListCustomInputFields() {

                // Make sure that there's only one of the custom input field
                if (!document.querySelector('.fc-custom-group')) {
                    // Prepare input fields
                    let self = this,
                        container = document.createElement('div'),
                        startDate = document.createElement('input'),
                        startDateFieldWrapper = document.createElement('div'),
                        endDate = document.createElement('input'),
                        endDateFieldWrapper = document.createElement('div'),
                        updateRangeButton = document.createElement('input'),
                        buttonWrapper = document.createElement('div');

                    startDateFieldWrapper.setAttribute('class', 'small-4 columns');
                    endDateFieldWrapper.setAttribute('class', 'small-4 columns');
                    buttonWrapper.setAttribute('class', 'small-4 columns');

                    // Start date field
                    startDate.setAttribute('id', 'start_date');
                    startDate.setAttribute('name', 'start_date');
                    startDate.setAttribute('data-datepicker', '');
                    startDate.setAttribute('data-datepicker-buttontext', '');
                    if (self.listViewDateRange.start) startDate.setAttribute('value', self.listViewDateRange.start);

                    // End date field
                    endDate.setAttribute('id', 'end_date');
                    endDate.setAttribute('name', 'end_date');
                    endDate.setAttribute('data-datepicker', '');
                    endDate.setAttribute('data-datepicker-buttontext', '');
                    if (self.listViewDateRange.end) endDate.setAttribute('value', self.listViewDateRange.end);

                    // Update button
                    updateRangeButton.setAttribute('id', 'update_date_range_button');
                    updateRangeButton.setAttribute('type', 'submit');
                    updateRangeButton.setAttribute('value', 'Update');

                    if (self.currentViewIsList()) {
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

                    document.querySelector('.fc-toolbar > .fc-left').appendChild(container);

                    // Register an event listener when update button is clicked.
                    document.querySelector('#update_date_range_button').addEventListener('click', function () {
                        if (document.querySelector('#start_date').value && document.querySelector('#end_date').value) {
                            let startDateValue = moment(document.querySelector('#start_date').value, 'DD/MM/YYYY'),
                                endDateValue = moment(document.querySelector('#end_date').value, 'DD/MM/YYYY');

                            if (startDateValue.isValid() && endDateValue.isValid()) {
                                self.listViewDateRange.start = document.querySelector('#start_date').value;
                                self.listViewDateRange.end = document.querySelector('#end_date').value;

                                self.duration = self.helper.dateDiff(startDateValue.format('YYYY-MM-DD'), endDateValue.format('YYYY-MM-DD')) + 1;
                                self.$refs.fullCalendar.getApi().gotoDate(startDateValue.format('YYYY-MM-DD'));
                            }
                        }
                    });
                }
            },

            currentViewIsList() {
                return this.currentDisplay === 'list';
            }
        },
        mounted() {
            this.registerListCustomInputFields();
        }
    }

</script>

<template>
    <div>
        <export-modal ref="export"
                      :event-types="config.eventTypes"
                      :groups="config.groups"
                      :campuses="config.campuses"
                      :translations="config.translations"
                      :token="config.token"
                      :event-export-url="urls.export" />

        <filter-modal ref="filter"
                      :selected-filters="config.selectedFilters"
                      :sources="config.sources"
                      :event-types="config.eventTypes"
                      :groups="config.groups"
                      :campuses="config.campuses"
                      @filterChanged="updateSelectedFilters" />

        <create-event-modal ref="createEvent"
                            :event="newEvent"
                            :user-id="config.userId"
                            :task-type-id="config.taskTypeId"
                            :event-types="config.eventTypes"
                            :to-moment="toMoment"
                            :event-create-url="urls.create"
                            :event-modify-form-url="urls.modifyForm"
                            :format-form-date="formats.formDate"
                            :format-form-datetime="formats.formDatetime"
                            :format-display-date="formats.displayDate"
                            :format-display-datetime="formats.displayDatetime"
                            @eventCreated="addEvent" />

        <view-event-modal ref="event"
                          :event="currentEvent"
                          :task-type-id="config.taskTypeId"
                          :to-moment="toMoment"
                          :event-modify-form-url="urls.modifyForm"
                          :event-modify-form-action-url="urls.modifyFormAction"
                          :event-update-url="urls.update"
                          :attendance-list-url="urls.attendanceList"
                          :attendance-toggle-url="urls.attendanceToggle"
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
                    columnHeader: false
                },
                week: {
                    columnHeaderFormat: formats.displayWeekDay
                },
                list: {
                    buttonText: 'List',
                    duration: { days: this.duration },

                }
            }"
            :event-time-format="helper.eventTimeFormat"
            content-height="auto"
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
            :header="{
                left: 'today,prev,next',
                center: 'title',
                right: 'customFilters,list,dayGridMonth,timeGridWeek,timeGridDay,customExport'
            }"
            :buttonText="{
                today:    'Today',
                month:    'Month',
                week:     'Week',
                day:      'Day',
                list: 'List',
            }"
            :default-date="config.defaultDate"
            :default-view="config.defaultView || 'dayGridMonth'"
            :scroll-time="config.dateDisplayFormats.calendarStartTime"
            :event-limit="config.eventLimit"
            :first-day="config.firstDay"
            :events="events"
            :event-render="eventRender"
            :view-skeleton-render="viewSkeletonRender"
            @loading="onLoading"
            @dateClick="onDateClick"
            @eventClick="onEventClick"
            @eventResize="onEventResize"
            @eventDrop="onEventDrop"
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
