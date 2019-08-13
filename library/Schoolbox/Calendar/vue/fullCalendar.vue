<script>

    import FullCalendar             from '@fullcalendar/vue'
    import interactionPlugin        from '@fullcalendar/interaction'
    import dayGridPlugin            from '@fullcalendar/daygrid'
    import timeGridPlugin           from '@fullcalendar/timegrid'
    import listPlugin               from '@fullcalendar/list'
    import momentPlugin,
           {toMoment, toDuration}   from '@fullcalendar/moment'
    import RevealModal              from '@schoolbox/reveal-modal'
    import MetaList                 from '@schoolbox/meta/list'
    import MetaItem                 from '@schoolbox/meta/item'
    import Toast                    from "@schoolbox/toast";
    import ViewEventModal           from './viewEventModal';
    import CreateEventModal         from './createEventModal';
    import ExportModal              from "./exportModal";
    import FilterModal              from "./filterModal";
    import axios                    from 'axios'
    import qs                       from "qs";

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
        data() {
            return {
                calendarPlugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, momentPlugin ],
                currentEvent: {},
                newEvent: {},
            }
        },
        computed: {
            jQuery: () => window.jQuery,
            config: () => window.fullCalendarConfig,
            isNewEventSubmittable() {
                return this.newEvent.title && this.newEvent.title.length !== 0;
            },
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
                // TODO find a non-jQuery dependent way to do this
                if (isLoading === true) {
                    // this.$refs.toast.activate('Loading...');
                    this.jQuery.magnificPopup.open({
                        showCloseBtn: false,
                        items: {
                            src: "<div class='preview loader'></div>",
                            type: 'inline'
                        }
                    });
                } else {
                    // this.$refs.toast.timeout('Done', true, 3000, 2000);
                    this.jQuery.magnificPopup.close();
                }
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
                let calEvent = eventRenderInfo.event,
                    self     = this;

                // Always render placeholders in the most basic manner
                if (calEvent.id === 'placeholder') {
                    return true;
                }

                // Ignore the phantom event that the API puts there
                if (!calEvent?.extendedProps?.data) {
                    return false;
                }

                // Don't render me if I'm marked as do not render
                if ('is_rendered' in calEvent && !calEvent?.is_rendered) {
                    return false;
                }

                // Mangle the event using old-fashioned jQuery
                // TODO don't depend on jQuery for this
                let $element = this.jQuery(eventRenderInfo.el),
                    inner    = $element.find('.fc-content'),
                    title    = $element.find('.fc-title'),
                    content  = $element.find('.fc-event-content');

                // If appropriate, indicate this event can be attended
                if (Boolean(calEvent?.extendedProps?.data?.attendance?.icon)) {
                    let span = '<span class="att">(A)</span>';
                    if (content.length === 1) {
                        content.append(span)
                    } else {
                        inner.append(span)
                    }
                }

                // If this event is a task, alter the state of the checkbox accordingly.
                if (this.isTask(calEvent)) {
                    inner.prepend(
                        this.jQuery('<input type="checkbox" name="completed" class="plain" />')
                            .attr('id', 't' + calEvent?.extendedProps?.data?.meta?.eventId)
                    );
                    if (Boolean(calEvent?.extendedProps?.data?.meta?.completed)) {
                        title.addClass('strike');
                        inner.find(':checkbox').prop('checked', true)
                    }
                }

                // Apply the visible class?
                $element.addClass(calEvent?.extendedProps?.data?.styles?.visible);

                // Ensure that this event matches the selected filters.
                // For all of them, match against the event's class names.
                let eventFilters    = {},
                    isEventRendered = true;
                calEvent.classNames.forEach(function (className) {
                    let match = className.match(/^([a-z]+)(\d+)/i);
                    if (match) {
                        let prefix = match[1],
                            id     = Number(match[2]);
                        if (!eventFilters[prefix]) {
                            eventFilters[prefix] = [];
                        }
                        eventFilters[prefix].push(id);
                    }
                });


                // All of the event's attributes must match at least one selected.
                // If it doesn't, don't render it.
                Object.keys(eventFilters).forEach(function (prefix) {
                    let ids                = eventFilters[prefix],
                        matchesAnyInFilter = true;
                    ids.forEach(function (id) {
                        // Check id against the list of selected filters.
                        // (Sources have multiple IDs per source: only check one)
                        let keyspace = [];
                        switch (prefix) {
                            case 'source':
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
                        if (!keyspace.includes(id)) {
                            matchesAnyInFilter = false;
                        }
                    });
                    if (!matchesAnyInFilter) {
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
                return toMoment(date, this.$refs.fullCalendar.getApi());
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
                return Number(event?.extendedProps?.data?.meta?.eventTypeId) === this.config.taskTypeId;
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
                return this.toMoment(dateObject.date).format(this.config.dateDisplayFormats.momentJsDateLong);
            },
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
                }
            }"
            :event-time-format="{
                hour: 'numeric',
                hour12: true,
                minute: '2-digit',
                meridiem: 'short',
                omitZeroMinute: true,
            }"
            content-height="auto"
            :custom-buttons="{
                customExport: {
                    text: 'export',
                    click: onExportClick
                },
                customFilters: {
                    text: 'filters',
                    click: onFilterClick
                },
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
