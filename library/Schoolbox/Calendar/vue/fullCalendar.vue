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
    import toQueryString            from "./helper/toQueryString";

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
            window: () => window,
            config: () => window.fullCalendarConfig,
            isNewEventSubmittable: function () {
                return this.newEvent.title && this.newEvent.title.length !== 0;
            },
        },
        methods: {
            onExportClick: function () {
                this.$refs.export.open();
            },
            onFilterClick: function () {
                this.$refs.filter.open();
            },
            onLoading:  function (isLoading) {
                /* Show loading div right in the middle
                 */
                if (isLoading === true) {
                    // this.$refs.toast.activate('Loading...');
                    this.window.$.magnificPopup.open({
                        showCloseBtn: false,
                        items: {
                            src: "<div class='preview loader'></div>",
                            type: 'inline'
                        }
                    });
                }
                /* Has finished loading
                 */
                else {
                    /* Hide loading div
                     */
                    // this.$refs.toast.timeout('Done', true, 3000, 2000);
                    this.window.$.magnificPopup.close();

                    // // update any tasks that have been completed by strikethrough-ing them
                    // $(this).find('input[type=checkbox]').each(function () {
                    //     var self = $(this);
                    //
                    //     if (self.prop('checked')) {
                    //         self.siblings('.fc-title').addClass('strike')
                    //     }
                    // });
                    //
                    // // // reapply filters
                    // // c.filters.organise(c, ["campus", "groups", "sources"], false);
                    //
                    // // update datepicker
                    // $('#datepicker').datepicker('setDate', FullCalendarMoment.toMoment(this.getDate(), this).format('DD-MM-YYYY'))
                }
            },
            events: function (fetchInfo, callback) {
                var self = this;
                axios.get(
                    '/calendar/ajax/full',
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
            viewSkeletonRender: function (info) {
                // Preserve most recent view for this user
                axios.post(
                    '/calendar/changeState',
                    toQueryString({calendarView: info.view.type})
                );
            },
            eventRender: function (eventRenderInfo) {
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
                // TODO don't do this
                let $element = this.window.$(eventRenderInfo.el),
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
                        $('<input type="checkbox" name="completed" class="plain" />')
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
                        switch (prefix) {
                            case 'source':
                                if (!self.config.selectedFilters.source.flat().includes(id)) {
                                    matchesAnyInFilter = false;
                                }
                                break;

                            case 'type':
                                if (!self.config.selectedFilters.eventType.includes(id)) {
                                    matchesAnyInFilter = false;
                                }
                                break;

                            case 'group':
                                if (!self.config.selectedFilters.group.includes(id)) {
                                    matchesAnyInFilter = false;
                                }
                                break;

                            case 'campus':
                                if (!self.config.selectedFilters.campus.includes(id)) {
                                    matchesAnyInFilter = false;
                                }
                                break;

                            default:
                                break;
                        }
                    });
                    if (!matchesAnyInFilter) {
                        isEventRendered = false;
                    }
                });

                return isEventRendered;
            },
            onDateClick: function (dateClickInfo) {
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
            onEventClick: function (eventInfo) {
                // Ideally the current event would be a part of the modal,
                // not this component, but :shrug:
                this.currentEvent = eventInfo.event;
                this.$refs.event.open();
            },
            onEventResize: function (eventResizeInfo) {
                let event = eventResizeInfo.event,
                    data  = {
                        eventId: event?.extendedProps?.data?.meta?.eventId,
                        resize:  true,
                        start:   this.toMoment(event.start).format(),
                        end:     this.toMoment(event.end).format(),
                    };
                // Apply and then remove the "loading" class
                axios.post('/calendar/ajax/update', toQueryString(data));
            },
            onEventDrop: function (eventDropInfo) {
                let event = eventDropInfo.event,
                    delta = toDuration(eventDropInfo.delta),
                    data  = {
                        eventId:     event?.extendedProps?.data?.meta?.eventId,
                        minuteDelta: delta.asMinutes(),
                        allDay:      event.allDay,
                    };
                // Apply and then remove the "loading" class
                axios.post('/calendar/ajax/update', toQueryString(data));
            },
            toMoment: function (date) {
                return toMoment(date, this.$refs.fullCalendar.getApi());
            },
            addEvent: function (event) {
                this.$refs.fullCalendar.getApi().addEvent(event);
            },
            updateEvent: function (event) {
                this.$refs.fullCalendar.getApi().addEvent(event);
            },
            isTask: function (event) {
                return Number(event?.extendedProps?.data?.meta?.eventTypeId) === this.config.taskTypeId;
            },
            reloadEvents: function () {
                this.$refs.fullCalendar.getApi().refetchEvents();
            },
            updateSelectedFilters: function (selectedFilters) {
                // The selected filters in config are read-only and passed by reference.
                // TODO pass it by value into the filter modal, and update in this method
                this.$refs.fullCalendar.getApi().rerenderEvents();
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
                      :token="config.token" />

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
                            @eventCreated="addEvent" />

        <view-event-modal ref="event"
                          :event="currentEvent"
                          :task-type-id="config.taskTypeId"
                          :to-moment="toMoment"
                          @eventModified="reloadEvents"
                          @eventDeleted="reloadEvents" />

        <full-calendar
            ref="fullCalendar"
            :plugins="calendarPlugins"
            :views="{
                timeGridDay: {
                    // dateDisplayFormats.momentJsDateLong: figure out how to inject this
                    titleFormat: config.dateDisplayFormats.momentJsDateLong
                },
                timeGridWeek: {
                    columnFormat: 'ddd D/M'
                }
            }"
            event-time-format="h(:mm)a"
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
