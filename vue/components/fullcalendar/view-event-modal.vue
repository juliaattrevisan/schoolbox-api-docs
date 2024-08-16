<script>
import RevealModal from "@schoolboxVue/components/reveal-modal";
import MetaList from "@schoolboxVue/components/meta/list";
import MetaItem from "@schoolboxVue/components/meta/item";
import MediaArticle from "@schoolboxVue/components/media-article";
import fullCalendarHelper from "@schoolboxVue/components/fullcalendar/mixinFullCalendarHelper";

/* Path aliases like @schoolboxCalendarEvent are defined in webpack.common.js
 */
import { ATTENDANCE_STATUS } from "@schoolboxCalendarEvent/attendance-status";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import checks from "@schoolboxCalendarEvent/checks";
import { injectScript, isScriptAdded } from "@js/util/domUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorToolbar } from "@js/editor/types/editorTypes";

const EFFICACY_SELF = "self";
const EFFICACY_SERIES = "series";

const EVENT_MODIFIED = "eventModified";
const EVENT_DELETED = "eventDeleted";

/* Initialise vars
 *
 * Mirrors backend Payload instance to prevent random js error about
 * undefined vars being accessed; null operator doesn't work when supplying
 * as an arg, apparently
 *
 * This gets updated asynchronously when modals open
 */
const INITIAL_PAYLOAD = {
    level: null,
    currentUser: {
        user: {
            id: null,
        },
        status: null,
        canModify: null,
        canInvite: null,
    },
    attendees: {
        guests: [],
        accepted: null,
        available: null,
        declined: null,
        max: null,
        num: null,
        pending: null,
    },
};

export default {
    name: "view-event-modal",

    components: {
        MetaItem,
        MetaList,
        RevealModal,
        MediaArticle,
    },

    mixins: [fullCalendarHelper],

    props: {
        eventModifyFormUrl: {},
        eventModifyFormActionUrl: {},
        eventDeleteUrl: {},
        eventUpdateUrl: {},
        formatDisplayDate: {},
        formatDisplayTime: {},
        formatDisplayDatetime: {},
        returnUrl: {},
    },

    emits: ["eventModified"],

    data() {
        return {
            payload: INITIAL_PAYLOAD,
            event: {},

            /* Keep track of when an async request is in progress, locking
             * out other async requests until this has finished, preventing
             * rsvp-button spamming
             */
            isTransacting: false,
        };
    },

    computed: {
        window: () => window,
        jQuery: () => window.jQuery,

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

            /* [Vue warn]: Error in render: "TypeError: Cannot read property 'extendedProps' of undefined"
             *
             * There's a weird modal overlay that now spawns, which can cause
             * this.event to be undefined. That leads to subsequent crashes
             * Therefore we now have to check whether this exists before using it
             */
            // if (this.event == undefined) {
            //     return;
            // }

            let event = this.event,
                data = event?.extendedProps?.data,
                metadata = data?.meta,
                modifyLink = null,
                deleteLink = null;

            // Modify/delete links
            if (metadata?.editable) {
                let returnUrl = btoa(window.location.pathname);
                if (data?.custom?.modifyLink) {
                    // Inject a return URL:
                    // maybe the destination will understand it, maybe it won't
                    modifyLink = ENDPOINTS.generateUrlFromConst(data?.custom?.modifyLink, null, returnUrl);
                } else if (data?.custom?.modifyLink !== false && metadata?.eventId) {
                    // this.returnUrl does not get updated while switching months
                    modifyLink = ENDPOINTS.generateUrlFromConst(this.eventModifyFormUrl, metadata?.eventId, returnUrl);
                    deleteLink = ENDPOINTS.generateUrlFromConst(this.eventDeleteUrl, metadata?.eventId, returnUrl);
                }
            }

            return {
                eventId: metadata?.eventId,
                seriesId: metadata?.seriesId,
                editable: metadata?.editable,
                detail: metadata?.detail,
                location: metadata?.location,
                classCancelled: data?.styles?.visible,
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
                    isBooking: (data?.booking?.status || "").length,
                    status: data?.booking?.status,
                    related: data?.booking?.related || [],
                    actions: (data?.booking?.buttons || []).filter((button) => button?.href),
                },
                timetable: {
                    code: data?.timetable?.code,
                    staff: data?.timetable?.staff,
                },
                attendance: {
                    payload: this.payload,
                },
                classAttendance: {
                    url: data?.classAttendance?.url,
                    target: data?.classAttendance?.target,
                },
                task: {
                    isTask: this.fullCalendarHelper_isTask(event),
                    completed: metadata?.completed,
                },
                eotc: {
                    isEOTC: this.fullCalendarHelper_isEOTC(event),
                    numStudents: data?.custom?.numStudents,
                    numStaff: data?.custom?.numStaff,
                },
                links: {
                    view: data?.custom?.moreDetailsLink,
                    modify: modifyLink,
                    delete: deleteLink,
                    path: data?.links?.path,
                },
            };
        },
    },

    created() {
        this.ENDPOINTS = ENDPOINTS;
        this.canRsvp = checks.canRsvp;
        this.showRsvpButtons = checks.showRsvpButtons;
        this.isInvited = checks.isInvited;
        this.isPending = checks.isPending;
        this.isAccepted = checks.isAccepted;
        this.isDeclined = checks.isDeclined;
        this.isLimitedAttendance = checks.isLimitedAttendance;
        this.showAttendanceMeta = checks.showAttendanceMeta;
        this.isOpen = checks.isOpen;
        this.isInviteOnly = checks.isInviteOnly;
    },

    methods: {
        /**
         * Decide whether the modal has component actions or not
         *
         * Show the bar if you can do anything with its contents
         * Otherwise, hide it
         *
         * @return boolean
         */
        hasComponentActions() {
            return (
                this.eventExtendedProps.folder.id ||
                this.eventExtendedProps.links.path ||
                this.eventExtendedProps.links.view ||
                this.eventExtendedProps.links.modify ||
                this.eventExtendedProps.links.delete ||
                this.eventExtendedProps.classAttendance.url ||
                this.eventExtendedProps.booking.actions.length > 0 ||
                this.canRsvp(
                    this.eventExtendedProps.attendance.payload.level,
                    this.eventExtendedProps.attendance.payload.attendees.guests,
                    this.eventExtendedProps.attendance.payload.currentUser.user.id,
                    this.eventExtendedProps.attendance.payload.currentUser.status,
                    this.eventExtendedProps.attendance.payload.attendees.max,
                    this.eventExtendedProps.attendance.payload.attendees.available,
                )
            );
        },

        /**
         * Accept an invitation
         */
        rsvpAccept() {
            if (!this.isAccepted(this.eventExtendedProps.attendance.payload.currentUser.status)) {
                if (!this.isTransacting) {
                    /* If you haven't already accepted,
                     * async request to accept invitation
                     */
                    this.rsvp(ATTENDANCE_STATUS.ACCEPTED, ENDPOINTS.rsvpAcceptPath(this.eventExtendedProps.eventId));
                }
            }
        },

        /**
         * Decline an invitation
         */
        rsvpDecline() {
            if (this.isInvited(this.eventExtendedProps.attendance.payload.currentUser.status)) {
                if (!this.isDeclined(this.eventExtendedProps.attendance.payload.currentUser.status)) {
                    if (!this.isTransacting) {
                        this.rsvp(
                            ATTENDANCE_STATUS.DECLINED,
                            ENDPOINTS.rsvpDeclinePath(this.eventExtendedProps.eventId),
                        );
                    }
                }
            }
        },

        /**
         * Respond to an invitation with new status $status, using accept/decline endpoint $endpoint
         *
         * @param status
         * @param endpoint
         */
        rsvp(status, endpoint) {
            let self = this;
            let toast = this.jQuery("[data-toast]");

            /* Set a flag to prevent async spammage
             */
            self.isTransacting = true;

            toast.toastActivate({
                text: "Updating invitation...",
            });

            fetch(endpoint, {
                method: "POST",
            })
                .then((response) => {
                    if (response.ok) {
                        return response.json();
                    } else {
                        throw new Error(response.statusText);
                    }
                })
                .then((data) => {
                    self.payload.currentUser.status = status;
                    self.payload.attendees = data.attendees;

                    if (typeof self?.event?.extendedProps?.data?.attendance?.status === "object") {
                        self.event.extendedProps.data.attendance.status.current = status;
                        self.event.setExtendedProp("data", self.event.extendedProps.data);
                    }
                    toast.toastTimeout({
                        text: "Invitation successfully updated",
                        css: "success",
                    });
                })
                .catch((error) => {
                    toast.toastTimeout({
                        text: "Unable to update invitation. " + error.response.data.message,
                        css: "alert",
                    });
                })
                .finally(function () {
                    self.isTransacting = false;
                });
        },

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
        async updateEvent() {
            let self = this,
                form = self.$refs.updateForm,
                data = {
                    eventId: this.eventExtendedProps.eventId,
                    completed: this.$refs.eventCompletedInput.checked ? 1 : 0,
                    title: this.$refs.eventTitleInput.value.trim(),
                    detail: window.sbProps.editors["view-detail"].getData(),
                },
                toast = this.jQuery("[data-toast]");

            const formData = new FormData();
            formData.append("eventId", data.eventId);
            formData.append("completed", data.completed);
            formData.append("title", data.title);
            formData.append("detail", data.detail);

            toast.toastActivate({
                text: "Saving...",
            });

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formData,
                    responseType: "json",
                    headers: {
                        Accept: "application/json",
                    },
                });
                const responseData = await response.json();
                if (response.ok) {
                    let eventData = Object.assign({}, self.event?.extendedProps?.data || {});
                    if (typeof eventData?.meta === "object") {
                        eventData.meta.completed = Boolean(responseData?.data?.meta?.completed);
                        eventData.meta.detail = response?.data?.data?.meta?.detail;
                    }
                    self.event.setExtendedProp("data", eventData);
                    self.event.setProp("title", responseData?.title);
                    self.$refs.modal.close();
                    toast.toastTimeout({
                        text: "Saved",
                        css: "success",
                    });
                    this.$emit("eventModified", responseData);
                } else {
                    toast.toastTimeout({
                        text: "Unable to update task. " + responseData.message,
                        css: "alert",
                    });
                }
            } catch (error) {
                console.warn(error);
                toast.toastTimeout({
                    text: "Failed - " + error.message,
                    css: "alert",
                });
                submit.prop("disabled", false);
            }
        },

        /**
         * Initialise CKEditor
         */
        async initCKE() {
            const editorScript = `${window.cdnPrefix}/js/bundle/editor.js?v=${window.siteVersion}`;
            if (!isScriptAdded(editorScript)) {
                await injectScript(editorScript);
            }

            // if the editor already exists, set the data and avoid re-initialising
            const detailEditor = window.sbProps.editors["view-detail"];
            if (detailEditor) {
                detailEditor.setData(this.eventExtendedProps?.detail);
                return;
            }

            this.$nextTick(() => {
                // otherwise initialise the editor and set the data once its created
                this.$refs.eventDetailInput.addEventListener(EditorEvent.EDITOR__CREATED, (event) => {
                    event.detail.editor.setData(this.eventExtendedProps?.detail);
                });

                document.dispatchEvent(
                    new CustomEvent(EditorEvent.EDITOR__CREATE, {
                        detail: {
                            targetElements: ["#view-detail"],
                            toolbar: EditorToolbar.SIMPLE,
                            removeFromToolbar: ["link"],
                        },
                    }),
                );
            });
        },

        /**
         * Open this modal.
         */
        open(event) {
            let self = this;

            /* Initialise data
             */
            this.event = event;
            this.payload = INITIAL_PAYLOAD;

            /* Pop modal open
             */
            this.$refs.modal.open();

            //loadCkeditor
            if (this.eventExtendedProps.editable && this.eventExtendedProps.task.isTask) {
                //only if we are inside a task
                this.initCKE();
            }

            /**
             * Enusre video/audio files can be played in the modal
             * when it's opened. We call ProxyReplacement() again as
             * the modal isn't in the DOM when it's called initially.
             */
            this.$nextTick(() => {
                $.fn.schoolboxResource_ProxyReplacement(`#${this.$el.id}`);
            });

            /* Non-numeric eventIds are ignored
             * These could be hashes sourced from a third party, like the sis or a subscription
             * In those cases, we can't do anything about their attendance
             * We can only deal with attendance in our local db
             */
            if (isNaN(this.eventExtendedProps.eventId)) {
                return;
            }

            /* Events with the hasAttendance flag set to false should also exit early
             * Beware that this flag has dual meaning: it also refers to the timetable events'
             * ability to mark rolls for student attendance, in addition to the ability to invite
             * people to attend your regular calendar events
             */
            if (!this.event?.extendedProps?.data?.attendance?.hasAttendance) {
                return;
            }

            /* Asynchronously fetch attendance payload
             * This will update the modal in realtime when it's ready
             */
            fetch(ENDPOINTS.payloadPath(this.eventExtendedProps.eventId))
                .then((response) => {
                    if (response.ok) {
                        return response.json();
                    } else {
                        throw new Error(response.statusText);
                    }
                })
                .then((data) => {
                    self.payload = data;
                })
                .catch((error) => {
                    /* Attendance won't be available for some events such as those
                     * streamed from a third party source, or a sis; looking for their
                     * identifying hashes will result in 404 because they won't exist
                     * in our local db
                     */
                });
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
                return "";
            }
            const format = allDay ? this.formatDisplayDate : this.formatDisplayDatetime;
            const startDateFormatted = dayjs(startDate).format(format);
            let endDateFormatted = null;

            if (endDate != null) {
                if (allDay) {
                    // Event end dates in FullCalendar are exclusive.
                    // This means that all-day events from the event source
                    // have had their end date incremented by one day:
                    // roll it back for rendering the user-visible end.
                    endDateFormatted = dayjs(endDate).subtract(1, "d").format(format);
                } else {
                    endDateFormatted = dayjs(endDate).format(format);
                }
            }

            if (
                dayjs(endDate) &&
                dayjs(endDate).isValid() &&
                !dayjs(startDate).isSame(dayjs(endDate).subtract(1, "d"))
            ) {
                // Start and end aren't the same - stitch them together.
                if (!allDay && dayjs(startDate).isSame(dayjs(endDate), "day")) {
                    // Between two times on the same day - show the timespan but only one date
                    return startDateFormatted + " – " + dayjs(endDate).format(this.formatDisplayTime);
                } else {
                    // Between two different days - show the whole timespan
                    return startDateFormatted + " – " + endDateFormatted;
                }
            }

            // Otherwise start and end are the same - just show the start
            return startDateFormatted;
        },

        /**
         * Handle the clicking of a booking button.
         *
         * @param $event
         */
        clickBookingButton($event) {
            switch ($event.target.dataset.bookingAction) {
                case "approve":
                    $event.preventDefault();
                    if (this.eventExtendedProps.seriesId) {
                        this.$refs.bookingApproveSeriesModal.open();
                    } else {
                        this.bookingApprove(false);
                    }
                    break;

                case "reject":
                    $event.preventDefault();
                    if (this.eventExtendedProps.seriesId) {
                        this.$refs.bookingRejectSeriesModal.open();
                    } else {
                        this.bookingReject(false);
                    }
                    break;

                case "cancel":
                    $event.preventDefault();
                    this.$refs.bookingDeleteModal.open();
                    break;

                case "modify":
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
            // Ensure we are sending standard POST data, NOT a JSON encoded string
            let data = new URLSearchParams({
                id: this.eventExtendedProps.eventId,
                action: "Delete",
                efficacy: efficacy,
            });

            fetch(this.eventExtendedProps.links.delete, {
                method: "POST",
                body: data.toString(),
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                },
            })
                .then((response) => {
                    if (response.ok) {
                        return response;
                    } else {
                        throw new Error(response.statusText);
                    }
                })
                .then(() => this.event.remove())
                .then(() => this.$emit(EVENT_DELETED, this.event))
                .catch((error) => {
                    console.error(error);
                });
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
            let data = new URLSearchParams({
                    efficacy: efficacy,
                }),
                cancelActionUrl = this.eventExtendedProps.booking.actions.find(
                    (action) => action.action === "cancel",
                ).href;
            fetch(cancelActionUrl, {
                method: "POST",
                body: data.toString(),
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                },
            })
                .then((response) => {
                    if (response.ok) {
                        return response;
                    } else {
                        throw new Error(response.statusText);
                    }
                })
                .then(() => this.event.remove())
                .then(() => this.$emit(EVENT_DELETED, this.event))
                .catch((error) => {
                    console.error(error);
                });
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
            let data = new URLSearchParams({
                    efficacy: efficacy,
                }),
                approveActionUrl = this.eventExtendedProps.booking.actions.find(
                    (action) => action.action === "approve",
                ).href;
            fetch(approveActionUrl, {
                method: "POST",
                body: data.toString(),
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                },
            })
                .then((response) => {
                    if (response.ok) {
                        return response;
                    } else {
                        throw new Error(response.statusText);
                    }
                })
                .then(() => this.event.remove())
                .then(() => this.$emit(EVENT_MODIFIED, this.event))
                .catch((error) => {
                    console.error(error);
                });
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
            let data = new URLSearchParams({
                    efficacy: efficacy,
                }),
                rejectActionUrl = this.eventExtendedProps.booking.actions.find(
                    (action) => action.action === "reject",
                ).href;
            fetch(rejectActionUrl, {
                method: "POST",
                body: data.toString(),
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                },
            })
                .then((response) => {
                    if (response.ok) {
                        return response;
                    } else {
                        throw new Error(response.statusText);
                    }
                })
                .then(() => this.event.remove())
                .then(() => this.$emit(EVENT_MODIFIED, this.event))
                .catch((error) => {
                    console.error(error);
                });
        },
    },
};
</script>

<template>
    <reveal-modal ref="modal" size="medium">
        <template v-if="eventExtendedProps.editable && eventExtendedProps.task.isTask">
            <form ref="updateForm" method="post" :action="eventUpdateUrl" @submit.prevent="updateEvent">
                <input ref="eventIdInput" name="eventId" type="hidden" :value="eventExtendedProps.eventId" />

                <!-- Currently only tasks have this form -->
                <h3>Task</h3>

                <label> Due {{ eventExtendedProps.dateSpan }} </label>

                <input
                    ref="eventCompletedInput"
                    id="modifyTaskBubbleCompleted"
                    name="completed"
                    type="checkbox"
                    value="1"
                    :checked="eventExtendedProps.task.completed"
                />
                <label for="modifyTaskBubbleCompleted" class="checkbox-label"> Complete </label>

                <label class="required" for="modifyTaskBubbleTitle"> Task </label>
                <input
                    id="modifyTaskBubbleTitle"
                    ref="eventTitleInput"
                    name="title"
                    type="text"
                    :value="event.title"
                    @change="updateFormSubmittable"
                    @keyup="updateFormSubmittable"
                    maxlength="250"
                />

                <label class="detail">
                    Note
                    <textarea id="view-detail" ref="eventDetailInput" name="detail"></textarea>
                </label>

                <ul class="flex-list buttons">
                    <li><input ref="eventSubmit" type="submit" value="Update Task" /></li>
                    <li><button @click.prevent="$refs.eventDeleteModal.open">Delete Task</button></li>
                </ul>
            </form>
        </template>

        <template v-else>
            <template v-if="eventExtendedProps.links.path">
                <a :href="eventExtendedProps.links.path" title="Go to event page"
                    ><h2>{{ event.title }}</h2></a
                >
            </template>
            <template v-else>
                <h2 :class="eventExtendedProps.classCancelled">{{ event.title }}</h2>
            </template>
            <meta-list>
                <template v-if="eventExtendedProps.timetable.code && eventExtendedProps.timetable.code.length">
                    <meta-item class="icon-info">{{ eventExtendedProps.timetable.code }}</meta-item>
                </template>
                <meta-item class="icon-resource-booking" v-if="eventExtendedProps.booking.status">{{
                    eventExtendedProps.booking.status.name
                }}</meta-item>
                <meta-item class="icon-calendar" v-if="eventExtendedProps.dateSpan">{{
                    eventExtendedProps.dateSpan
                }}</meta-item>
                <meta-item class="icon-location" v-if="eventExtendedProps.location">{{
                    eventExtendedProps.location
                }}</meta-item>
                <meta-item class="icon-staff-students" v-if="eventExtendedProps.author.id"
                    >Posted by
                    <a :href="'/search/user/' + String(eventExtendedProps.author.id)">{{
                        eventExtendedProps.author.name
                    }}</a></meta-item
                >
                <meta-item class="icon-staff-students" v-if="eventExtendedProps.reviewer.id"
                    >Reviewed by
                    <a :href="'/search/user/' + String(eventExtendedProps.reviewer.id)">{{
                        eventExtendedProps.reviewer.name
                    }}</a></meta-item
                >
                <template
                    v-if="eventExtendedProps.timetable.staff && Object.keys(eventExtendedProps.timetable.staff).length"
                >
                    <meta-item class="icon-staff">
                        <template v-for="(name, id, index) in eventExtendedProps.timetable.staff" :key="id">
                            <template v-if="id">
                                <a :href="'/search/user/' + String(id)">{{ name }}</a>
                            </template>
                            <template v-else>{{ name }}</template
                            ><span v-if="index != Object.keys(eventExtendedProps.timetable.staff).length - 1">, </span>
                        </template>
                    </meta-item>
                </template>
                <meta-item
                    class="icon-class-list"
                    v-if="this.showAttendanceMeta(eventExtendedProps.attendance.payload.level)"
                >
                    Attendance:
                    <template v-if="isInviteOnly(eventExtendedProps.attendance.payload.level)">Invite only</template>
                    <template v-else-if="isOpen(eventExtendedProps.attendance.payload.level)">Open</template>

                    <template
                        v-if="
                            this.canRsvp(
                                eventExtendedProps.attendance.payload.level,
                                eventExtendedProps.attendance.payload.attendees.guests,
                                eventExtendedProps.attendance.payload.currentUser.user.id,
                                eventExtendedProps.attendance.payload.currentUser.status,
                                eventExtendedProps.attendance.payload.attendees.max,
                                eventExtendedProps.attendance.payload.attendees.available,
                            ) && this.isLimitedAttendance(eventExtendedProps.attendance.payload.attendees.max)
                        "
                    >
                        (<template v-if="eventExtendedProps.attendance.payload.attendees.available > 0">{{
                            eventExtendedProps.attendance.payload.attendees.available
                        }}</template>
                        <template v-else>No</template>
                        available
                        <template v-if="eventExtendedProps.attendance.payload.attendees.available === 1"
                            >place</template
                        >
                        <template v-else>places</template>
                        remaining)
                    </template>
                </meta-item>

                <!-- EOTC metadata -->
                <meta-item class="icon-teacher" v-if="eventExtendedProps.eotc.isEOTC"
                    >Assigned Staff: {{ eventExtendedProps.eotc.numStaff }}</meta-item
                >
                <meta-item class="icon-staff-students" v-if="eventExtendedProps.eotc.isEOTC"
                    >Students: {{ eventExtendedProps.eotc.numStudents }}</meta-item
                >
            </meta-list>
            <media-article :content="eventExtendedProps.detail"></media-article>

            <template v-if="eventExtendedProps.booking.related.length">
                <h3>Related Bookings</h3>
                <ul>
                    <li v-for="(relatedBooking, index) in eventExtendedProps.booking.related" :key="index">
                        ({{ relatedBooking.status.name }}) <a :href="relatedBooking.href">{{ relatedBooking.title }}</a>
                    </li>
                </ul>
            </template>

            <!--
                Temporary solution to prevent a mysterious error:
                main.js?v=19.1.alpha1:3710 Uncaught TypeError: elem.getAttribute is not a function
                when there's a related booking. According to my research, it's not a sizzle error and  we'll
                need to update jQuery to 3.x. However, doing so may potentially open un new issues.
             -->
            <template v-else></template>

            <template v-if="hasComponentActions()">
                <div class="component-action l-grid l-grid--12">
                    <template
                        v-if="
                            this.canRsvp(
                                eventExtendedProps.attendance.payload.level,
                                eventExtendedProps.attendance.payload.attendees.guests,
                                eventExtendedProps.attendance.payload.currentUser.user.id,
                                eventExtendedProps.attendance.payload.currentUser.status,
                                eventExtendedProps.attendance.payload.attendees.max,
                                eventExtendedProps.attendance.payload.attendees.available,
                            )
                        "
                    >
                        <div :class="['rsvp', 'large-6', 'medium-12', 'small-12']">
                            <span :class="'attend-label'">Attending?</span>
                            <span>
                                <a
                                    :href="this.ENDPOINTS.rsvpAcceptPath(this.eventExtendedProps.eventId)"
                                    @click.prevent="rsvpAccept"
                                    :class="{
                                        button: true,
                                        accepted: this.isAccepted(
                                            eventExtendedProps.attendance.payload.currentUser.status,
                                        ),
                                        disabled: this.isTransacting,
                                    }"
                                    data-rsvp="accept"
                                    >Yes</a
                                >
                            </span>
                            <span>
                                <a
                                    :href="this.ENDPOINTS.rsvpDeclinePath(this.eventExtendedProps.eventId)"
                                    @click.prevent="rsvpDecline"
                                    :class="{
                                        button: true,
                                        disabled: this.isTransacting,
                                        declined: this.isDeclined(
                                            eventExtendedProps.attendance.payload.currentUser.status,
                                        ),
                                    }"
                                    data-rsvp="decline"
                                    >No</a
                                >
                            </span>
                        </div>
                    </template>
                    <template v-else></template>

                    <div class="large-6 medium-12 small-12 text-right">
                        <!-- Various buttons -->
                        <span v-if="eventExtendedProps.links.path">
                            <a class="button" :href="eventExtendedProps.links.path">View details</a>
                        </span>
                        <span v-if="eventExtendedProps.folder.id">
                            <a class="button" :href="'/homepage/' + String(eventExtendedProps.folder.id)"
                                >Go to homepage</a
                            >
                        </span>
                        <span v-if="eventExtendedProps.links.view">
                            <a class="button" :href="eventExtendedProps.links.view">View More Details</a>
                        </span>
                        <span v-if="eventExtendedProps.classAttendance.url">
                            <a
                                class="button"
                                :href="eventExtendedProps.classAttendance.url"
                                :target="eventExtendedProps.classAttendance.target"
                                >Mark Attendance</a
                            >
                        </span>
                        <span v-if="eventExtendedProps.links.modify">
                            <a class="button" :href="eventExtendedProps.links.modify">Modify</a>
                        </span>
                        <span v-if="eventExtendedProps.links.delete">
                            <a
                                class="button"
                                :href="eventExtendedProps.links.delete"
                                @click.prevent="$refs.eventDeleteModal.open"
                                >Delete</a
                            >
                        </span>
                        <!-- Booking buttons: handle random events -->
                        <span v-for="(action, index) in eventExtendedProps.booking.actions" :key="index">
                            <a
                                class="button"
                                :data-booking-action="String(action.action)"
                                @click="clickBookingButton($event)"
                                :href="action.href || '#'"
                                >{{ action.label }}</a
                            >
                        </span>
                    </div>
                </div>
            </template>
            <!--
            When using reveal-modal.vue, all <template v-if> usages *must* have <template v-else>
            This is something to do with the vue/jquery/foundation modal crossover that we have
            because it happens only in our reveal-modal.vue usage (something might be broken there
            but digging deeper into this.modal init suggests there's something else going on in
            either jquery or foundation itself, as that syntax/usage seems fine)

            Interestingly someone else encountered it differently but had the same solution:
            https://stackoverflow.com/questions/50662851/v-if-not-working-on-template

            ...despite the official docs saying that the v-else is optional:
            https://vuejs.org/v2/guide/conditional.html#Conditional-Groups-with-v-if-on-lt-template-gt
            -->
            <template v-else></template>
        </template>

        <!-- Modals within a modal you say? Sure, why not? -->
        <reveal-modal ref="eventDeleteModal">
            <template v-if="eventExtendedProps.seriesId">
                <h2>This event is part of a series</h2>
                <p>Would you like to delete only this event, or all in the series?</p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button" @click.prevent="confirmEventDeleteSelf">Only this event</a></li>
                    <li>
                        <a href="#" class="button" @click.prevent="confirmEventDeleteSeries"
                            >All events in the series</a
                        >
                    </li>
                    <li><a href="#" class="button" @click.prevent="$refs.eventDeleteModal.close">Cancel</a></li>
                </ul>
            </template>
            <template v-else>
                <h2>Please confirm</h2>
                <p>Are you sure you wish to delete this event?</p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button submit" @click.prevent="confirmEventDeleteSelf">Delete</a></li>
                    <li><a href="#" class="button" @click.prevent="$refs.eventDeleteModal.close">Cancel</a></li>
                </ul>
            </template>
        </reveal-modal>

        <reveal-modal ref="bookingDeleteModal">
            <template v-if="eventExtendedProps.seriesId">
                <h2>This booking is part of a recurring series</h2>
                <p>
                    Would you like to delete only this booking, or all recurrences in the series?
                    <template v-if="eventExtendedProps.booking.related.length">
                        (All bookings for related resources for any deleted bookings will also be deleted.)
                    </template>
                </p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button" @click.prevent="confirmBookingDeleteSelf">Only this booking</a></li>
                    <li>
                        <a href="#" class="button" @click.prevent="confirmBookingDeleteSeries"
                            >All bookings in the series</a
                        >
                    </li>
                    <li><a href="#" class="button" @click.prevent="$refs.bookingDeleteModal.close">Cancel</a></li>
                </ul>
            </template>
            <template v-else>
                <h2>Please confirm</h2>
                <p>
                    Are you sure you wish to delete this booking?
                    <template v-if="eventExtendedProps.booking.related.length">
                        (All bookings for related resources will also be deleted.)
                    </template>
                </p>
                <ul class="flex-list buttons">
                    <li><a href="#" class="button submit" @click.prevent="confirmBookingDeleteSelf">Delete</a></li>
                    <li><a href="#" class="button" @click.prevent="$refs.bookingDeleteModal.close">Cancel</a></li>
                </ul>
            </template>
        </reveal-modal>

        <reveal-modal ref="bookingApproveSeriesModal">
            <h2>This booking is part of a series</h2>
            <p>Would you like to approve only this booking, or all in the series?</p>
            <ul class="flex-list buttons">
                <li><a href="#" class="button" @click.prevent="confirmBookingApproveSelf">Only this event</a></li>
                <li>
                    <a href="#" class="button" @click.prevent="confirmBookingApproveSeries">All events in the series</a>
                </li>
                <li><a href="#" class="button" @click.prevent="$refs.bookingApproveSeriesModal.close">Cancel</a></li>
            </ul>
        </reveal-modal>

        <reveal-modal ref="bookingRejectSeriesModal">
            <h2>This booking is part of a series</h2>
            <p>Would you like to reject only this booking, or all in the series?</p>
            <ul class="flex-list buttons">
                <li><a href="#" class="button" @click.prevent="confirmBookingRejectSelf">Only this event</a></li>
                <li>
                    <a href="#" class="button" @click.prevent="confirmBookingRejectSeries">All events in the series</a>
                </li>
                <li><a href="#" class="button" @click.prevent="$refs.bookingRejectSeriesModal.close">Cancel</a></li>
            </ul>
        </reveal-modal>
    </reveal-modal>
</template>
