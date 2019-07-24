<script>

    import FullCalendar             from '@fullcalendar/vue'
    import interactionPlugin        from '@fullcalendar/interaction'
    import dayGridPlugin            from '@fullcalendar/daygrid'
    import timeGridPlugin           from '@fullcalendar/timegrid'
    import listPlugin               from '@fullcalendar/list'
    import momentPlugin, {toMoment} from '@fullcalendar/moment'
    import RevealModal              from '@schoolbox/reveal-modal'

    export default {
        components: {
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
            window: function () { return window; },
            config: function () { return window.fullCalendarConfig; },
            isNewEventSubmittable: function() {
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
                // Use axios instead of jQuery to fetch events?
                window.$.ajax({
                    dataType: 'json',
                    url: '/calendar/ajax/full',
                    data: {
                        start:  Number((fetchInfo.start.getTime() / 1000).toFixed(0)),
                        end:    Number((fetchInfo.end.getTime() / 1000).toFixed(0)),
                        userId: self.config.userId
                    },
                    success: function (data) {
                        callback(data.filter(function (v) {
                            return 'start' in v;
                        }));

                        // There should be no need to preserve state change on loading events
                        // /* Save state to the db every time you change tabs
                        //  */
                        // // TODO restore this
                        // $.post('/calendar/changeState', {calendarView: fc.view.type});
                    },
                    cache: false
                });
            },
            onDateClick: function (dateClickInfo) {
                // this.currentEvent = eventInfo.event;
                // this.$refs.createEvent.open();
                let momentDate = this.toMoment(dateClickInfo.date);
                this.newEvent = {
                    allDay:      dateClickInfo.allDay,
                    date:        momentDate.format(dateClickInfo.allDay ? 'DD-MM-YYYY' : 'DD-MM-YYYY HH:mm'),
                    displayDate: momentDate.format(dateClickInfo.allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma'),
                    title:       '',
                    detail:      '',
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

        <reveal-modal ref="createEvent">
            <!-- create a tab Vue component? Currently they just work magically -->
            <dl class="tabs" data-tab>
                <dd class="active"><a href="#createEventPanel">Create Event</a></dd>
                <dd><a href="#createTaskPanel">Create Task</a></dd>
            </dl>
            <div class="tabs-content">
                <div class="content active" id="createEventPanel" aria-hidden="false">
                    <form id="createEvent" method="post" action="/calendar/ajax/create" class="top">
                        <input name="userId" type="hidden" :value="config.userId" />
                        <input name="allDay" type="hidden" v-model="newEvent.allDay" />
                        <input name="date"   type="hidden" v-model="newEvent.date" />

                        <section>
                            <h3>On <span>{{ newEvent.displayDate }}</span></h3>

                            <label>
                                Event Title
                                <input name="title" type="text" v-model="newEvent.title" />
                            </label>

                            <label>
                                Event Type
                                <!-- Pass in a list of event types as well, so that a dropdown can be rendered -->
                                <select name="type">
                                    <!-- BEGIN event_type -->
                                    <option value="{EVENT_TYPE_ID}">{EVENT_TYPE_NAME}</option>
                                    <!-- END event_type -->
                                </select>
                            </label>
                        </section>
                        <ul class="flex-list buttons">
                            <li><input type="submit" :disabled="!isNewEventSubmittable" value="Create Event" /></li>
                            <li><a href="#" id="edit-full" class="button icon-modify">Edit Event</a></li>
                        </ul>
                    </form>
                </div>
                <div class="content" id="createTaskPanel" aria-hidden="true" tabindex="-1">
                    <form id="createTask" method="post" action="/calendar/ajax/create">
                        <input name="userId" type="hidden" :value="config.userId" />
                        <input name="allDay" type="hidden" v-model="newEvent.allDay" />
                        <input name="date"   type="hidden" v-model="newEvent.date" />
                        <input name="type"   type="hidden" :value="config.taskTypeId" />

                        <section>
                            <h3>Due <span>{{ newEvent.displayDate }}</span></h3>

                            <label>
                                Task
                                <input name="title" type="text" v-model="newEvent.title" />
                            </label>

                            <label class="detail">
                                Note
                                <textarea name="detail" v-model="newEvent.detail"></textarea>
                            </label>
                        </section>
                        <div class="row">
                            <div class="small-12 columns no-pad">
                                <input type="submit" :disabled="!isNewEventSubmittable" value="Create Task" />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </reveal-modal>

        <reveal-modal ref="event">
            <div class="row">
                <div class="small-12">
                    <h3 class="note">{{ currentEvent.title }}</h3>
                    <article class="detail">
                        <span v-html="currentEvent.detail"></span>
                    </article>
                </div>
            </div>
        </reveal-modal>

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
