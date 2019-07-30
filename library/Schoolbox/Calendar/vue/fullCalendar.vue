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
    import ViewEventModal           from './viewEventModal';
    import CreateEventModal         from './createEventModal';
    import axios                    from 'axios'

    export default {
        components: {
            CreateEventModal,
            ViewEventModal,
            MetaItem,
            MetaList,
            FullCalendar,
            RevealModal
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
                    window.$.magnificPopup.open({
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
                    window.$.magnificPopup.close();

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

                        // There should be no need to preserve state change on loading events
                        // /* Save state to the db every time you change tabs
                        //  */
                        // // TODO restore this
                        // $.post('/calendar/changeState', {calendarView: fc.view.type});
                    }
                );
                // Fail callback: consider a message saying something failed
            },
            eventRender: function (eventRenderInfo) {
                let calEvent = eventRenderInfo.event;

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
                let $element = window.$(eventRenderInfo.el),
                    inner    = $element.find('.fc-content'),
                    title    = $element.find('.fc-title'),
                    content  = $element.find('.fc-event-content');

                // title.text(calEvent?.title || '');

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
                return true;
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
                axios.post('/calendar/ajax/update', this.toQueryString(data));
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
                axios.post('/calendar/ajax/update', this.toQueryString(data));
            },
            toMoment: function (date) {
                return toMoment(date, this.$refs.fullCalendar.getApi());
            },
            toggleAttendance: function () {
                // TODO get this to work
                // var button = $(this);
                //
                // var data = config.getFormData(button);
                //
                // if (button.text() === config.text.rsvp) {
                //     data['status'] = 'add';
                // } else {
                //     data['status'] = 'remove';
                // }
                //
                // $.post(config.url, data, function (data) {
                //     if (data.error == undefined) {
                //         if (button.text() === config.text.rsvp) {
                //             button.html(config.text.unattend);
                //         }
                //         else {
                //             button.html(config.text.rsvp);
                //         }
                //     }
                // });
            },
            addEvent: function (event) {
                this.$refs.fullCalendar.getApi().addEvent(event);
            },
            toQueryString: function (object) {
                // Helper function for converting an object into a query string.
                // I'm not going to npm install just to do that!
                let qsParts = [];
                Object.keys(object).forEach(function (key) {
                    let val = object[key];
                    qsParts.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
                });
                return qsParts.join('&');
            },
            isTask: function (event) {
                return Number(event?.extendedProps?.data?.meta?.eventTypeId) === this.config.taskTypeId;
            }
        }
    }

</script>

<template>
    <div>
        <reveal-modal ref="export">
            bbb
        </reveal-modal>

        <reveal-modal ref="filter">
            ccc
        </reveal-modal>

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
                          :to-moment="toMoment" />

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
            @loading="onLoading"
            @dateClick="onDateClick"
            @eventClick="onEventClick"
            @eventResize="onEventResize"
            @eventDrop="onEventDrop"
        />
    </div>
</template>

<style lang="scss">
    @import '~@fullcalendar/core/main.css';
    @import '~@fullcalendar/daygrid/main.css';
    @import '~@fullcalendar/timegrid/main.css';
    @import '~@fullcalendar/list/main.css';
</style>
