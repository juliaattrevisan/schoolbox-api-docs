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
    import axios                    from 'axios'

    export default {
        components: {
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
            currentEventExtendedProps: function () {
                // Also need to render the event's buttons
                let event      = this?.currentEvent,
                    data       = event?.extendedProps?.data,
                    metadata   = data?.meta,
                    modifyLink = null,
                    deleteLink = null;

                // Modify/delete links
                if (event?.editable) {
                    if (data?.custom?.modifyLink) {
                        modifyLink = data?.custom?.modifyLink;
                    } else if (metadata?.eventId) {
                        modifyLink = '/calendar/modify.php?eventID=' + metadata?.eventId + '&returnTo=calendar';
                        deleteLink = '/calendar/modifyForm.php?action=Delete&id=' + metadata?.eventId + '&returnTo=calendar';
                    }
                }

                return {
                    eventId: metadata?.eventId,
                    detail: metadata?.detail,
                    location: metadata?.location,
                    author: {
                        id:   Math.max(0, metadata?.authorId || 0),
                        name: metadata?.author,
                    },
                    reviewer: {
                        id:   Math.max(0, metadata?.reviewerId || 0),
                        name: metadata?.reviewer,
                    },
                    folder: {
                        id: Math.max(0, data?.links?.category.id || 0),
                    },
                    dateSpan: this.determineDateSpan(event?.start, event?.end, event?.allDay),
                    booking: {
                        status: data?.booking?.status,
                        related: data?.booking?.related,
                        actions: data?.booking?.buttons,
                    },
                    attendance: data?.attendance,
                    links: {
                        view: data?.custom?.moreDetailsLink,
                        modify: modifyLink,
                        delete: deleteLink,
                    },
                };
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
                // this.currentEvent = eventInfo.event;
                // this.$refs.createEvent.open();
                let momentDate       = this.toMoment(dateClickInfo.date),
                    [firstEventType] = this.config.eventTypes;
                this.newEvent = {
                    allDay:      dateClickInfo.allDay,
                    date:        momentDate.format(dateClickInfo.allDay ? 'DD-MM-YYYY' : 'DD-MM-YYYY HH:mm'),
                    displayDate: momentDate.format(dateClickInfo.allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma'),
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
            determineDateSpan: function (startDate, endDate, allDay) {
                if (!startDate) {
                    return '';
                }
                let format      = allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma',
                    momentStart = this.toMoment(startDate),
                    start       = momentStart.format(format),
                    momentEnd   = null,
                    end         = null;

                if (endDate != null) {
                    momentEnd = this.toMoment(endDate);
                    if (allDay) {
                        end = momentEnd.subtract(1, 'd').format(format);
                    } else {
                        end = momentEnd.format(format);
                    }
                }

                if (momentEnd && !momentStart.isSame(momentEnd)) {
                    // Start and end aren't the same - stitch them together.
                    if (!allDay && momentStart.isSame(momentEnd, 'day')) {
                        // Between two times on the same day - show the timespan but only one date
                        return start + " – " + momentEnd.format('h:mma');
                    } else {
                        // Between two different days - show the whole timespan
                        return start + " – " + end;
                    }
                } else {
                    // Start and end are the same - just show the start
                    return start;
                }
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

        <reveal-modal ref="createEvent">
            <!-- create a tab Vue component? Currently they just work magically -->
            <dl class="tabs" data-tab>
                <dd class="active"><a href="#createEventPanel">Create Event</a></dd>
                <dd><a href="#createTaskPanel">Create Task</a></dd>
            </dl>
            <div class="tabs-content">
                <div class="content active" id="createEventPanel" aria-hidden="false">
                    <form ref="createEventForm"
                          method="post"
                          action="/calendar/ajax/create"
                          @submit.prevent="createNewEvent($refs.createEventForm)"
                          class="top"
                    >
                        <input name="userId" type="hidden" :value="config.userId" />
                        <input name="allDay" type="hidden" :value="newEvent.allDay ? 1 : 0" />
                        <input name="date"   type="hidden" :value="newEvent.date" />

                        <section>
                            <h3>On <span>{{ newEvent.displayDate }}</span></h3>

                            <label>
                                Event Title
                                <input name="title" type="text" v-model="newEvent.title" />
                            </label>

                            <label>
                                Event Type
                                <select name="type" v-model="newEvent.type">
                                    <option v-for="eventType in config.eventTypes" :value="eventType.id">
                                        {{ eventType.name }}
                                    </option>
                                </select>
                            </label>
                        </section>
                        <ul class="flex-list buttons">
                            <li><button @click.prevent="redirectToEventForm($event)">Edit Event</button></li>
                            <li><input type="submit"
                                       :disabled="!isNewEventSubmittable"
                                       value="Create Event" /></li>
                        </ul>
                    </form>
                </div>
                <div class="content" id="createTaskPanel" aria-hidden="true" tabindex="-1">
                    <form ref="createTaskForm"
                          method="post"
                          action="/calendar/ajax/create"
                          @submit.prevent="createNewEvent($refs.createTaskForm)"
                    >
                        <input name="userId" type="hidden" :value="config.userId" />
                        <input name="allDay" type="hidden" :value="newEvent.allDay ? 1 : 0" />
                        <input name="date"   type="hidden" :value="newEvent.date" />
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
                                <input type="submit"
                                       :disabled="!isNewEventSubmittable"
                                       value="Create Task" />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </reveal-modal>

        <!-- TODO consider moving this to its own component? -->
        <reveal-modal ref="event">
            <div class="row">
                <div class="small-12">
                    <h2 class="note">{{ currentEvent.title }}</h2>
                    <meta-list>
                        <meta-item class="icon-resource-booking" v-if="currentEventExtendedProps.booking.status">
                            {{ currentEventExtendedProps.booking.status.name }}
                        </meta-item>
                        <meta-item class="icon-calendar" v-if="currentEventExtendedProps.dateSpan">
                            {{ currentEventExtendedProps.dateSpan }}
                        </meta-item>
                        <meta-item class="icon-location" v-if="currentEventExtendedProps.location">
                            {{ currentEventExtendedProps.location }}
                        </meta-item>
                        <meta-item class="icon-staff-students" v-if="currentEventExtendedProps.author.id">
                            Posted by
                            <a :href="'/search/user/' + String(currentEventExtendedProps.author.id)">
                                {{ currentEventExtendedProps.author.name }}
                            </a>
                        </meta-item>
                        <meta-item class="icon-staff-students" v-if="currentEventExtendedProps.reviewer.id">
                            Reviewed by
                            <a :href="'/search/user/' + String(currentEventExtendedProps.reviewer.id)">
                                {{ currentEventExtendedProps.reviewer.name }}
                            </a>
                        </meta-item>
                    </meta-list>
                    <article style="white-space: pre-line">
                        <span v-html="currentEventExtendedProps.detail"></span>
                    </article>

                    <div v-if="currentEventExtendedProps.booking.related">
                        <h3>Related Bookings</h3>
                        <ul>
                            <li v-for="relatedBooking in currentEventExtendedProps.booking.related">
                                ({{ relatedBooking.status.name }})
                                <a :href="relatedBooking.status.href">{{ relatedBooking.title }}</a>
                            </li>
                        </ul>
                    </div>

                    <div v-if="currentEventExtendedProps.attendance">
                        <dl>
                            <dd><p :class="currentEventExtendedProps.attendance.icon"></p></dd>
                            <dd class="rm"><p>{{ currentEventExtendedProps.attendance.status }}</p></dd>
                        </dl>
                    </div>

                    <span v-if="currentEventExtendedProps.folder.id">
                        <a class="button icon-home"
                           :href="'/homepage/' + String(currentEventExtendedProps.folder.id)">Go to homepage</a>
                    </span>
                    <span v-if="currentEventExtendedProps.links.view">
                        <a class="button icon-due-work"
                           :href="currentEventExtendedProps.links.view">View More Details</a>
                    </span>
                    <span v-if="currentEventExtendedProps.attendance && currentEventExtendedProps.attendance.listButton">
                        <a class="button icon-group"
                           :href="'/calendar/attendance/list/' + String(currentEventExtendedProps.eventId)">Attendees</a>
                    </span>
                    <span v-if="currentEventExtendedProps.attendance && currentEventExtendedProps.attendance.requestButton">
                        <a class="button icon-reply"
                           @click.prevent="toggleAttendance"
                           href="#">{{ currentEventExtendedProps.attendance.requestButton }}</a>
                    </span>
                    <span v-if="currentEventExtendedProps.modifyLink">
                        <a class="button icon-modify"
                           :href="currentEventExtendedProps.modifyLink">Modify</a>
                    </span>
                    <span v-if="currentEventExtendedProps.deleteLink">
                        <a class="button icon-delete"
                           :href="currentEventExtendedProps.deleteLink">Delete</a>
                    </span>

                    <div v-for="action in currentEventExtendedProps.booking.actions">
                        <a :href="action.href" class="button">{{ action.label }}</a>
                    </div>

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
