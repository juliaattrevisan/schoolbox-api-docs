<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import MetaList    from '@schoolbox/meta/list'
    import MetaItem    from '@schoolbox/meta/item'
    import axios       from 'axios';
    import qs          from 'qs';

    const EFFICACY_SELF = 'self';
    const EFFICACY_SERIES = 'series';

    const EVENT_MODIFIED = 'eventModified';
    const EVENT_DELETED = 'eventDeleted';

    const ATTENDANCE_ACTION_ADD = 'add';
    const ATTENDANCE_ACTION_REMOVE = 'remove';

    export default {
        name: "view-event-modal",
        props: [
            'event',
            'toMoment',
            'taskTypeId',
            'eventModifyFormUrl',
            'eventModifyFormActionUrl',
            'eventUpdateUrl',
            'attendanceListUrl',
            'attendanceToggleUrl',
            'formatDisplayDate',
            'formatDisplayTime',
            'formatDisplayDatetime',
        ],
        components: {
            MetaItem,
            MetaList,
            RevealModal
        },
        computed: {
            window: () => window,

            /**
             * Generate extended properties for the component's event.
             *
             * TODO simplify this format
             * (This format this is a mess necessitated by the mess that is the
             *  events API.)
             *
             * @return {Object}
             */
            eventExtendedProps() {
                // Also need to render the event's buttons
                let event      = this.event,
                    data       = event?.extendedProps?.data,
                    metadata   = data?.meta,
                    modifyLink = null,
                    deleteLink = null;

                // Modify/delete links
                if (metadata?.editable) {
                    if (data?.custom?.modifyLink) {
                        modifyLink = data?.custom?.modifyLink;
                    } else if (metadata?.eventId) {
                        modifyLink = this.eventModifyFormUrl + '?eventID=' + metadata?.eventId + '&returnTo=calendar';
                        deleteLink = this.eventModifyFormActionUrl + '?action=Delete&id=' + metadata?.eventId + '&returnTo=calendar';
                    }
                }

                return {
                    eventId: metadata?.eventId,
                    seriesId: metadata?.seriesId,
                    editable: metadata?.editable,
                    detail: metadata?.detail,
                    location: metadata?.location,
                    author: {
                        id: Math.max(0, metadata?.authorId || 0),
                        name: metadata?.author,
                    },
                    reviewer: {
                        id: Math.max(0, metadata?.reviewerId || 0),
                        name: metadata?.reviewer,
                    },
                    folder: {
                        id: Math.max(0, data?.links?.category?.id || 0),
                    },
                    dateSpan: this.determineDateSpan(event?.start, event?.end, event?.allDay),
                    booking: {
                        isBooking: (data?.booking?.status || '').length,
                        status: data?.booking?.status,
                        related: data?.booking?.related || [],
                        actions: (data?.booking?.buttons || []).filter(button => button?.href),
                    },
                    attendance: {
                        // Massage attendance data into something resembling usability
                        isAttendable: data?.attendance?.icon,
                        status: data?.attendance?.status,
                        actions: {
                            toggle: {
                                isAvailable: Boolean(data?.attendance?.requestButton),
                                href: this.attendanceToggleUrl,
                                text: data?.attendance?.nextAction === ATTENDANCE_ACTION_ADD ? 'RSVP' : 'Unattend',
                            },
                            list: {
                                isAvailable: Boolean(data?.attendance?.listButton),
                                href: this.attendanceListUrl + String(metadata?.eventId),
                            },
                        },
                    },
                    classAttendance: {
                        url: data?.classAttendance?.url,
                    },
                    task: {
                        isTask: Number(metadata?.eventTypeId) === Number(this.taskTypeId),
                        completed: metadata?.completed,
                    },
                    links: {
                        view: data?.custom?.moreDetailsLink,
                        modify: modifyLink,
                        delete: deleteLink,
                        path: data?.links?.path
                    },
                };
            }
        },
        methods: {
            /**
             * Update whether the form can be submitted.
             *
             * It can be submitted if the title contains at least one non-space
             * character.
             */
            updateFormSubmittable() {
                this.$refs.eventSubmit.disabled = this.$refs.eventTitleInput.value.trim().length === 0;
            },

            /**
             * Persist changes to the event.
             *
             * TODO consider whether this should be in the base FullCalendar component
             */
            updateEvent() {
                let self = this,
                    form = self.$refs.updateForm,
                    data = {
                        eventId:   this.eventExtendedProps.eventId,
                        completed: this.$refs.eventCompletedInput.checked ? 1 : 0,
                        title:     this.$refs.eventTitleInput.value.trim(),
                        detail:    this.$refs.eventDetailInput.value,
                    },
                    request = {
                        url:          form.action,
                        data:         qs.stringify(data),
                        method:       form.method,
                        responseType: 'json',
                    };
                axios
                    .request(request)
                    .then(function (response) {
                        // Assign updated values into existing event
                        let eventData = Object.assign({}, self.event?.extendedProps?.data || {});
                        if (typeof eventData?.meta === 'object') {
                            eventData.meta.completed = Boolean(response?.data?.data?.meta?.completed);
                            eventData.meta.detail    = response?.data?.data?.meta?.detail;
                        }
                        self.event.setExtendedProp('data', eventData);
                        self.event.setProp('title', response?.data?.title);
                    })
                    .finally(function () {
                        self.$refs.modal.close();
                    });
            },

            /**
             * Open this modal.
             */
            open() {
                this.$refs.modal.open();
            },

            /**
             * Determine how to render the start and end date as date range.
             *
             * @param startDate
             * @param endDate
             * @param allDay
             * @return {string}
             */
            determineDateSpan(startDate, endDate, allDay) {
                if (!startDate) {
                    return '';
                }
                let format      = allDay ? this.formatDisplayDate : this.formatDisplayDatetime,
                    momentStart = this.toMoment(startDate),
                    start       = momentStart.format(format),
                    momentEnd   = null,
                    end         = null;

                if (endDate != null) {
                    momentEnd = this.toMoment(endDate);
                    if (allDay) {
                        // Event end dates in FullCalendar are exclusive.
                        // This means that all-day events from the event source
                        // have had their end date incremented by one day:
                        // roll it back for rendering the user-visible end.
                        end = momentEnd.subtract(1, 'd').format(format);
                    } else {
                        end = momentEnd.format(format);
                    }
                }

                if (momentEnd && !momentStart.isSame(momentEnd)) {
                    // Start and end aren't the same - stitch them together.
                    if (!allDay && momentStart.isSame(momentEnd, 'day')) {
                        // Between two times on the same day - show the timespan but only one date
                        return start + " – " + momentEnd.format(this.formatDisplayTime);
                    } else {
                        // Between two different days - show the whole timespan
                        return start + " – " + end;
                    }
                }

                // Otherwise start and end are the same - just show the start
                return start;
            },

            /**
             * Build the map of classes to render for a resource booking button.
             *
             * @param action
             * @return {Object}
             */
            buildBookingButtonClasses(action) {
                let base = {
                    button: true
                };
                if (action.class) {
                    base[action.class] = true;
                }
                return base;
            },

            /**
             * Handle the clicking of a booking button.
             *
             * @param $event
             */
            clickBookingButton($event) {
                switch ($event.target.dataset.bookingAction) {
                    case 'approve':
                        $event.preventDefault();
                        if (this.eventExtendedProps.seriesId) {
                            this.$refs.bookingApproveSeriesModal.open();
                        } else {
                            this.bookingApprove(false);
                        }
                        break;

                    case 'reject':
                        $event.preventDefault();
                        if (this.eventExtendedProps.seriesId) {
                            this.$refs.bookingRejectSeriesModal.open();
                        } else {
                            this.bookingReject(false);
                        }
                        break;

                    case 'cancel':
                        $event.preventDefault();
                        this.$refs.bookingDeleteModal.open();
                        break;

                    case 'modify':
                    default:
                        // Just follow the link
                        break;
                }
            },

            /**
             * Confirm deletion of a single event.
             */
            confirmEventDeleteSelf() {
                this.eventDelete(EFFICACY_SELF);
            },

            /**
             * Confirm deletion of a series of events.
             */
            confirmEventDeleteSeries() {
                this.eventDelete(EFFICACY_SERIES);
            },

            /**
             * Confirm deletion of a single booking.
             */
            confirmBookingDeleteSelf() {
                this.bookingDelete(EFFICACY_SELF);
            },

            /**
             * Confirm deletion of a series of bookings.
             */
            confirmBookingDeleteSeries() {
                this.bookingDelete(EFFICACY_SERIES);
            },

            /**
             * Confirm approval of a single booking.
             */
            confirmBookingApproveSelf() {
                this.bookingApprove(EFFICACY_SELF);
            },

            /**
             * Confirm approval of a series of bookings.
             */
            confirmBookingApproveSeries() {
                this.bookingApprove(EFFICACY_SERIES);
            },

            /**
             * Confirm rejection of a single booking.
             */
            confirmBookingRejectSelf() {
                this.bookingReject(EFFICACY_SELF);
            },

            /**
             * Confirm rejection of a series of bookings.
             */
            confirmBookingRejectSeries() {
                this.bookingReject(EFFICACY_SERIES);
            },

            /**
             * Delete an event from the database.
             *
             * TODO consider whether this should be in the base FullCalendar component
             *
             * @param efficacy EFFICACY_SELF or EFFICACY_SERIES
             * @return {Promise}
             */
            eventDelete(efficacy) {
                this.$refs.eventDeleteModal.close();
                let data = {
                    id: this.eventExtendedProps.eventId,
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.links.delete, qs.stringify(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_DELETED, this.event));
            },

            /**
             * Delete a booking from the database.
             *
             * TODO consider whether this should be in the base FullCalendar component
             *
             * @param efficacy EFFICACY_SELF or EFFICACY_SERIES
             * @return {Promise}
             */
            bookingDelete(efficacy) {
                this.$refs.bookingDeleteModal.close();
                let data = {
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.booking.actions.find(action => action.action === 'cancel').href, qs.stringify(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_DELETED, this.event));
            },

            /**
             * Approve a booking.
             *
             * TODO consider whether this should be in the base FullCalendar component
             *
             * @param efficacy EFFICACY_SELF or EFFICACY_SERIES
             * @return {Promise}
             */
            bookingApprove(efficacy) {
                this.$refs.bookingApproveSeriesModal.close();
                let data = {
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.booking.actions.find(action => action.action === 'approve').href, qs.stringify(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_MODIFIED, this.event));
            },

            /**
             * Reject a booking.
             *
             * TODO consider whether this should be in the base FullCalendar component
             *
             * @param efficacy EFFICACY_SELF or EFFICACY_SERIES
             * @return {Promise}
             */
            bookingReject(efficacy) {
                this.$refs.bookingRejectSeriesModal.close();
                let data = {
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.booking.actions.find(action => action.action === 'reject').href, qs.stringify(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_MODIFIED, this.event));
            },

            /**
             * Toggle your attendance on/off for an event.
             *
             * TODO consider whether this should be in the base FullCalendar component
             */
            toggleAttendance() {
                let self      = this,
                    eventData = Object.assign({}, self.event?.extendedProps?.data || {});
                self.$refs.modal.close();
                if (typeof eventData?.attendance === 'object') {
                    let nextAction = eventData.attendance.nextAction,
                        data = {
                            status:      nextAction,
                            'event[id]': self.eventExtendedProps.eventId,
                        };

                    axios
                        .post(self.eventExtendedProps.attendance.actions.toggle.href, qs.stringify(data))
                        .then(function () {
                            eventData.attendance.nextAction = nextAction === ATTENDANCE_ACTION_ADD
                                ? ATTENDANCE_ACTION_REMOVE
                                : ATTENDANCE_ACTION_ADD
                            ;
                            eventData.attendance.status = nextAction === ATTENDANCE_ACTION_ADD
                                ? 'Pending'
                                : ''
                            ;
                            self.event.setExtendedProp('data', eventData);
                        });
                }
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <template v-if="eventExtendedProps.editable && eventExtendedProps.task.isTask">
            <form ref="updateForm" method="post" :action="eventUpdateUrl" @submit.prevent="updateEvent">
                <input ref="eventIdInput"
                       name="eventId"
                       type="hidden"
                       :value="eventExtendedProps.eventId"
                />

                <!-- Currently only tasks have this form -->
                <h3>Task</h3>

                <label>
                    Due {{ eventExtendedProps.dateSpan }}
                </label>

                <input ref="eventCompletedInput"
                       id="modifyTaskBubbleCompleted"
                       name="completed"
                       type="checkbox"
                       value="1"
                       :checked="eventExtendedProps.task.completed"
                />
                <label for="modifyTaskBubbleCompleted" class="checkbox-label">
                    Complete
                </label>

                <label class="required" for="modifyTaskBubbleTitle">
                    Task
                </label>
                <input id="modifyTaskBubbleTitle"
                       ref="eventTitleInput"
                       name="title"
                       type="text"
                       :value="event.title"
                       @change="updateFormSubmittable"
                       @keyup="updateFormSubmittable"
                />

                <label class="detail">
                    Note
                    <textarea ref="eventDetailInput"
                              name="detail"
                              :value="eventExtendedProps.detail"
                              @change="updateFormSubmittable"
                              @keyup="updateFormSubmittable"
                    ></textarea>
                </label>

                <ul class="flex-list buttons">
                    <li><input ref="eventSubmit"
                               type="submit"
                               value="Update Task" /></li>
                    <li><button @click.prevent="$refs.eventDeleteModal.open">Delete Task</button></li>
                </ul>
            </form>
        </template>

        <template v-else>
            <div class="row">
                <div class="small-12">
                    <template v-if="eventExtendedProps.links.path">
                        <a :href="'/calendar/event/' + eventExtendedProps.eventId" title="Go to event page"><h2>{{ event.title }}</h2></a>
                    </template>
                    <template v-else>
                        <h2>{{ event.title }}</h2>
                    </template>
                    <meta-list>
                        <meta-item class="icon-resource-booking" v-if="eventExtendedProps.booking.status">
                            {{ eventExtendedProps.booking.status.name }}
                        </meta-item>
                        <meta-item class="icon-calendar" v-if="eventExtendedProps.dateSpan">
                            {{ eventExtendedProps.dateSpan }}
                        </meta-item>
                        <meta-item class="icon-location" v-if="eventExtendedProps.location">
                            {{ eventExtendedProps.location }}
                        </meta-item>
                        <meta-item class="icon-staff-students" v-if="eventExtendedProps.author.id">
                            Posted by
                            <a :href="'/search/user/' + String(eventExtendedProps.author.id)">
                                {{ eventExtendedProps.author.name }}
                            </a>
                        </meta-item>
                        <meta-item class="icon-staff-students" v-if="eventExtendedProps.reviewer.id">
                            Reviewed by
                            <a :href="'/search/user/' + String(eventExtendedProps.reviewer.id)">
                                {{ eventExtendedProps.reviewer.name }}
                            </a>
                        </meta-item>
                        <meta-item class="icon-class-list" v-if="eventExtendedProps.attendance.isAttendable && eventExtendedProps.attendance.status">
                            Attendance: {{ eventExtendedProps.attendance.status }}
                        </meta-item>
                    </meta-list>
                    <article style="white-space: pre-line">
                        <span v-html="eventExtendedProps.detail"></span>
                    </article>

                    <template v-if="eventExtendedProps.booking.related.length">
                        <h3>Related Bookings</h3>
                        <ul>
                            <li v-for="relatedBooking in eventExtendedProps.booking.related">
                                ({{ relatedBooking.status.name }})
                                <a :href="relatedBooking.href">{{ relatedBooking.title }}</a>
                            </li>
                        </ul>
                    </template>

                    <!--
                        Temporary solution to prevent a mysterious error:
                        schoolbox.min.js?v=19.1.alpha1:3710 Uncaught TypeError: elem.getAttribute is not a function
                        when there's a related booking. According to my research, it's not a sizzle error and  we'll
                        need to update jQuery to 3.x. However, doing so may potentially open un new issues.
                     -->
                    <template v-else></template>

                    <!--
                    <div v-if="eventExtendedProps.attendance.isAttendable">
                        <dl>
                            <dd><p :class="eventExtendedProps.attendance.icon"></p></dd>
                            <dd class="rm"><p>{{ eventExtendedProps.attendance.status }}</p></dd>
                        </dl>
                    </div>
                    -->
                    <template v-if="eventExtendedProps.folder.id ||
                    eventExtendedProps.links.view ||
                    eventExtendedProps.attendance.actions.list.isAvailable ||
                    eventExtendedProps.attendance.actions.toggle.isAvailable ||
                    eventExtendedProps.links.modify ||
                    eventExtendedProps.links.delete ||
                    eventExtendedProps.classAttendance.url ||
                    eventExtendedProps.booking.actions.length > 0">
                        <div class="component-action">
                            <section>
                                <!-- Various buttons -->
                                <span v-if="eventExtendedProps.folder.id">
                                    <a class="button"
                                       :href="'/homepage/' + String(eventExtendedProps.folder.id)">Go to homepage</a>
                                </span>
                                <span v-if="eventExtendedProps.links.view">
                                    <a class="button"
                                       :href="eventExtendedProps.links.view">View More Details</a>
                                </span>
                                <span v-if="eventExtendedProps.attendance.actions.toggle.isAvailable">
                                    <a class="button"
                                       @click.prevent="toggleAttendance"
                                       href="#">{{ eventExtendedProps.attendance.actions.toggle.text }}</a>
                                </span>
                                <span v-if="eventExtendedProps.attendance.actions.list.isAvailable">
                                    <a class="button"
                                       :href="attendanceListUrl + String(eventExtendedProps.eventId)">View Attendees</a>
                                </span>
                                <span v-if="eventExtendedProps.classAttendance.url">
                                    <a class="button"
                                       :href="eventExtendedProps.classAttendance.url">Mark Attendance</a>
                                </span>
                                <span v-if="eventExtendedProps.links.modify">
                                    <a class="button"
                                       :href="eventExtendedProps.links.modify">Modify</a>
                                </span>
                                <span v-if="eventExtendedProps.links.delete">
                                    <a class="button"
                                       :href="eventExtendedProps.links.delete"
                                       @click.prevent="$refs.eventDeleteModal.open">Delete</a>
                                </span>
                                <!-- Booking buttons: handle random events -->
                                <span v-for="action in eventExtendedProps.booking.actions">
                                    <a :class="buildBookingButtonClasses(action)"
                                       :data-booking-action="String(action.action)"
                                       @click="clickBookingButton($event)"
                                       :href="action.href || '#'">{{ action.label }}</a>
                                </span>
                            </section>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Modals within a modal you say? Sure, why not? -->
        <reveal-modal ref="eventDeleteModal">
            <template v-if="eventExtendedProps.seriesId">
                <h2>This event is part of a series</h2>
                <p>Would you like to delete only this event, or all in the series?</p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button" @click.prevent="confirmEventDeleteSelf">Only this event</a></li>
                    <li><a href="#" class="button" @click.prevent="confirmEventDeleteSeries">All events in the series</a></li>
                    <li><a href="#" class="button" @click.prevent="$refs.eventDeleteModal.close">Cancel</a></li>
                </ul>
            </template>
            <template v-else>
                <h2>Please confirm</h2>
                <p>Are you sure you wish to delete this event?</p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button submit" @click.prevent="confirmEventDeleteSelf">Yes</a></li>
                    <li><a href="#" class="button" @click.prevent="$refs.eventDeleteModal.close">No</a></li>
                </ul>
            </template>
        </reveal-modal>

        <reveal-modal ref="bookingDeleteModal">
            <template v-if="eventExtendedProps.seriesId">
                <h2>This booking is part of a series</h2>
                <p>Would you like to delete only this booking, or all in the series?</p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button" @click.prevent="confirmBookingDeleteSelf">Only this booking</a></li>
                    <li><a href="#" class="button" @click.prevent="confirmBookingDeleteSeries">All bookings in the series</a></li>
                    <li><a href="#" class="button" @click.prevent="$refs.bookingDeleteModal.close">Cancel</a></li>
                </ul>
            </template>
            <template v-else>
                <h2>Please confirm</h2>
                <p>Are you sure you wish to delete this booking?</p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button submit" @click.prevent="confirmBookingDeleteSelf">Yes</a></li>
                    <li><a href="#" class="button" @click.prevent="$refs.bookingDeleteModal.close">No</a></li>
                </ul>
            </template>
        </reveal-modal>

        <reveal-modal ref="bookingApproveSeriesModal">
            <h2>This booking is part of a series</h2>
            <p>Would you like to approve only this booking, or all in the series?</p>
            <ul class="flex-list buttons">
                <li><a href="#" class="button" @click.prevent="confirmBookingApproveSelf">Only this event</a></li>
                <li><a href="#" class="button" @click.prevent="confirmBookingApproveSeries">All events in the series</a></li>
                <li><a href="#" class="button" @click.prevent="$refs.bookingApproveSeriesModal.close">Cancel</a></li>
            </ul>
        </reveal-modal>

        <reveal-modal ref="bookingRejectSeriesModal">
            <h2>This booking is part of a series</h2>
            <p>Would you like to reject only this booking, or all in the series?</p>
            <ul class="flex-list buttons">
                <li><a href="#" class="button" @click.prevent="confirmBookingRejectSelf">Only this event</a></li>
                <li><a href="#" class="button" @click.prevent="confirmBookingRejectSeries">All events in the series</a></li>
                <li><a href="#" class="button" @click.prevent="$refs.bookingRejectSeriesModal.close">Cancel</a></li>
            </ul>
        </reveal-modal>

    </reveal-modal>
</template>
