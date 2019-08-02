<script>
    import RevealModal   from '@schoolbox/reveal-modal'
    import MetaList      from '@schoolbox/meta/list'
    import MetaItem      from '@schoolbox/meta/item'
    import axios         from 'axios';
    import toQueryString from "./helper/toQueryString";

    const EFFICIACY_SELF = 'self';
    const EFFICIACY_SERIES = 'series';

    const EVENT_MODIFIED = 'eventModified';
    const EVENT_DELETED = 'eventDeleted';

    const ATTENDANCE_ACTION_ADD = 'add';
    const ATTENDANCE_ACTION_REMOVE = 'remove';

    export default {
        name: "view-event-modal",
        props: ['event', 'toMoment', 'taskTypeId',],
        components: {
            MetaItem,
            MetaList,
            RevealModal
        },
        computed: {
            window: () => window,
            eventExtendedProps: function () {
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
                        modifyLink = '/calendar/modify.php?eventID=' + metadata?.eventId + '&returnTo=calendar';
                        deleteLink = '/calendar/modifyForm.php?action=Delete&id=' + metadata?.eventId + '&returnTo=calendar';
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
                        isAttending: data?.attendance?.status !== '',
                        status: data?.attendance?.status,
                        actions: {
                            toggle: {
                                isAvailable: Boolean(data?.attendance?.listButton),
                                href: '/service/attendance/setAttendance',
                                text: data?.attendance?.nextAction === ATTENDANCE_ACTION_ADD ? 'RSVP' : 'Unattend',
                            },
                            list: {
                                isAvailable: Boolean(data?.attendance?.requestButton),
                                href: '/calendar/attendance/list/' + String(metadata?.eventId),
                            },
                        },
                        // nextAction: data?.attendance?.nextAction,
                        // nextActionButton: data?.attendance?.listButton,
                        // requestButton: data?.attendance?.requestButton,
                    },
                    task: {
                        isTask: Number(metadata?.eventTypeId) === Number(this.taskTypeId),
                        completed: metadata?.completed,
                    },
                    links: {
                        view: data?.custom?.moreDetailsLink,
                        modify: modifyLink,
                        delete: deleteLink,
                    },
                };
            }
        },
        methods: {
            isTaskSubmittable: function () {
                return this.$refs.eventTitleInput.value.length !== 0;
            },
            updateEvent: function () {
                let self = this,
                    form = self.$refs.updateForm,
                    data = {
                        eventId:   this.eventExtendedProps.eventId,
                        completed: this.$refs.eventCompletedInput.checked ? 1 : 0,
                        title:     this.$refs.eventTitleInput.value,
                        detail:    this.$refs.eventDetailInput.value,
                    },
                    request = {
                        url:          form.action,
                        data:         toQueryString(data),
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
            deleteEvent: function () {
                // Handle event deletion (series dialog, confirmation)
                // and all that
                let self    = this,
                    request = {
                        url:    '/calendar/modifyForm.php',
                        method: 'post',
                        data:   toQueryString({id: this.eventExtendedProps.eventId}),
                        params: {
                            action: 'Delete',
                            // TODO add efficacy, redirects, etc?
                        },
                    };
                axios
                    .request(request)
                    .then(function () {
                        // Remove this event to the calendar
                        self.event.remove();
                    }).finally(function () {
                        self.$refs.modal.close();
                    });
            },
            open() {
                this.$refs.modal.open();
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
            buildBookingButtonClasses: function(action) {
                let base = {
                    button: true
                };
                if (action.class) {
                    base[action.class] = true;
                }
                return base;
            },
            clickBookingButton: function($event) {
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
            // Event actions
            confirmEventDeleteSelf: function () {
                this.eventDelete(EFFICIACY_SELF);
            },
            confirmEventDeleteSeries: function () {
                this.eventDelete(EFFICIACY_SERIES);
            },
            confirmBookingDeleteSelf: function () {
                this.bookingDelete(EFFICIACY_SELF);
            },
            confirmBookingDeleteSeries: function () {
                this.bookingDelete(EFFICIACY_SERIES);
            },
            confirmBookingApproveSelf: function () {
                this.bookingApprove(EFFICIACY_SELF);
            },
            confirmBookingApproveSeries: function () {
                this.bookingApprove(EFFICIACY_SERIES);
            },
            confirmBookingRejectSelf: function () {
                this.bookingReject(EFFICIACY_SELF);
            },
            confirmBookingRejectSeries: function () {
                this.bookingReject(EFFICIACY_SERIES);
            },
            eventDelete: function (efficacy) {
                this.$refs.eventDeleteModal.close();
                let data = {
                    id: this.eventExtendedProps.eventId,
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.links.delete, toQueryString(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_DELETED, this.event));
            },
            bookingDelete: function (efficacy) {
                this.$refs.bookingDeleteModal.close();
                let data = {
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.booking.actions.cancel.href, toQueryString(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_DELETED, this.event));
            },
            bookingApprove: function (efficacy) {
                this.$refs.bookingApproveSeriesModal.close();
                let data = {
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.booking.actions.approve.href, toQueryString(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_MODIFIED, this.event));
            },
            bookingReject: function (efficacy) {
                this.$refs.bookingRejectSeriesModal.close();
                let data = {
                    efficacy: efficacy,
                };
                return axios
                    .post(this.eventExtendedProps.booking.actions.reject.href, toQueryString(data))
                    .then(() => this.event.remove())
                    .then(() => this.$emit(EVENT_MODIFIED, this.event));
            },
            toggleAttendance: function () {
                let eventData = Object.assign({}, this.event?.extendedProps?.data || {});
                if (typeof eventData?.attendance === 'object') {
                    eventData.attendance.nextAction = eventData.attendance.nextAction === ATTENDANCE_ACTION_ADD
                        ? ATTENDANCE_ACTION_REMOVE
                        : ATTENDANCE_ACTION_ADD
                    ;
                }
                this.event.setExtendedProp('data', eventData);
                this.$refs.modal.close();

                // // TODO get this to work
                // var button = $(this);
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
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <template v-if="eventExtendedProps.editable && eventExtendedProps.task.isTask">
            <form ref="updateForm" method="post" action="/calendar/ajax/update" @submit.prevent="updateEvent">
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
                       :checked="eventExtendedProps.completed"
                />
                <label for="modifyTaskBubbleCompleted" class="checkbox-label">
                    Complete
                </label>

                <label>
                    Task
                    <input ref="eventTitleInput"
                           name="title"
                           type="text"
                           :value="event.title"
                    />
                </label>

                <label class="detail">
                    Note
                    <textarea ref="eventDetailInput"
                              name="detail"
                              :value="eventExtendedProps.detail"
                    ></textarea>
                </label>

                <ul class="flex-list buttons">
                    <li><input type="submit"
                               :disabled="!isTaskSubmittable"
                               value="Update Task" /></li>
                    <li><button @click.prevent="deleteEvent">Delete Task</button></li>
                </ul>
            </form>
        </template>

        <template v-else>
            <div class="row">
                <div class="small-12">
                    <h2 class="note">{{ event.title }}</h2>
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
                    </meta-list>
                    <article style="white-space: pre-line">
                        <span v-html="eventExtendedProps.detail"></span>
                    </article>

                    <div v-if="eventExtendedProps.booking.related.length">
                        <h3>Related Bookings</h3>
                        <ul>
                            <li v-for="relatedBooking in eventExtendedProps.booking.related">
                                ({{ relatedBooking.status.name }})
                                <a :href="relatedBooking.status.href">{{ relatedBooking.title }}</a>
                            </li>
                        </ul>
                    </div>

                    <!--
                    <div v-if="eventExtendedProps.attendance.isAttendable">
                        <dl>
                            <dd><p :class="eventExtendedProps.attendance.icon"></p></dd>
                            <dd class="rm"><p>{{ eventExtendedProps.attendance.status }}</p></dd>
                        </dl>
                    </div>
                    -->


                    <!-- Various buttons -->
                    <span v-if="eventExtendedProps.folder.id">
                        <a class="button icon-home"
                           :href="'/homepage/' + String(eventExtendedProps.folder.id)">Go to homepage</a>
                    </span>

                    <span v-if="eventExtendedProps.links.view">
                        <a class="button icon-due-work"
                           :href="eventExtendedProps.links.view">View More Details</a>
                    </span>

                    <span v-if="eventExtendedProps.attendance.actions.list.isAvailable">
                        <a class="button icon-group"
                           :href="'/calendar/attendance/list/' + String(eventExtendedProps.eventId)">Attendees</a>
                    </span>

                    <span v-if="eventExtendedProps.attendance.actions.toggle.isAvailable">
                        <a class="button icon-reply"
                           @click.prevent="toggleAttendance"
                           href="#">{{ eventExtendedProps.attendance.actions.toggle.text }}</a>
                    </span>

                    <span v-if="eventExtendedProps.links.modify">
                        <a class="button icon-modify"
                           :href="eventExtendedProps.links.modify">Modify</a>
                    </span>

                    <span v-if="eventExtendedProps.links.delete">
                        <a class="button icon-delete"
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

<style scoped>

</style>
