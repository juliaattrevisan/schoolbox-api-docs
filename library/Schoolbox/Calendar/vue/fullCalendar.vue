<script>

    import FullCalendar             from '@fullcalendar/vue'
    import interactionPlugin        from '@fullcalendar/interaction'
    import dayGridPlugin            from '@fullcalendar/daygrid'
    import timeGridPlugin           from '@fullcalendar/timegrid'
    import listPlugin               from '@fullcalendar/list'
    import momentPlugin, {toMoment} from '@fullcalendar/moment'
    import RevealModal              from '@schoolbox/reveal-modal'
    import MetaList                 from '@schoolbox/meta/list'
    import MetaItem                 from '@schoolbox/meta/item'
    import EventModal               from './eventModal';
    import CreateEventModal         from './createEventModal';
    import axios                    from 'axios'

    export default {
        components: {
            CreateEventModal,
            EventModal,
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
            onDateClick: function (dateClickInfo) {
                // TODO migrate date rendering into create event modal
                let momentDate       = this.toMoment(dateClickInfo.date),
                    [firstEventType] = this.config.eventTypes;
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
                console.log(eventResizeInfo);
            },
            onEventDrop: function (eventDropInfo) {
                console.log(eventDropInfo);
            },
            toMoment: function (date) {
                return toMoment(date, this.$refs.fullCalendar.getApi());
            },
            createNewEvent: function (form) {
                let self = this;
                axios.request({
                    url:          form.action,
                    data:         $(form).serialize(),
                    method:       form.method,
                    responseType: 'json',
                }).then(function (response) {
                    // Add this event to the calendar
                    self.$refs.fullCalendar.getApi().addEvent(response.data);
                }).finally(function () {
                    self.$refs.createEvent.close();
                });
            },
            redirectToEventForm: function () {
                // Use jQuery's form serialize for now: replace later
                window.location.href = '/calendar/modify.php?from=inline&returnTo=calendar&'
                    + window.$(this.$refs.createEventForm).serialize();
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
        />

        <event-modal ref="event"
                     :event="currentEvent"
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
