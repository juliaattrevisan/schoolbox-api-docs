<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import AudiencesModal from "./audiences-modal";
import MediaArticle from "@schoolboxVue/components/media-article";
import debounce from "lodash/debounce";

/* Path aliases like @schoolboxCalendarEvent are defined in webpack.common.js
 */
import { ATTENDANCE_STATUS } from "@schoolboxCalendarEvent/attendance-status";
import { ATTENDANCE_LEVELS } from "@schoolboxCalendarEvent/attendance-levels";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import checks from "@schoolboxCalendarEvent/checks";

/* Vue select
 */
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

/* Render custom template for Deselect icon/button
 * It's not well documented, but you can trawl through closed PRs to figger out how
 * https://github.com/sagalbot/vue-select/issues/994
 */
import Deselect from "@schoolboxVue/components/selectize/Deselect";
vSelect.props.components.default = () => ({ Deselect });

const FORMAT_LONG_DATE = "dddd, MMMM DD, YYYY"; // Monday, October 01, 2019
const FORMAT_LONG_DATE_WITH_TIME = "dddd, MMMM DD, YYYY h:mma"; // Monday, October 01, 2019 02:30pm
const FORMAT_DATE_ONLY = "MM-DD-YYYY"; // 10-01-2019
const FORMAT_TIME_ONLY = "h:mma"; // 02:30pm

export default {
    name: "event-details",

    components: {
        AudiencesModal,
        MediaArticle,
        vSelect,
    },

    /* All props form a one-way-down binding between the child property and the parent one:
     * when the parent property updates, it will flow down to the child, but not the other way around
     * https://vuejs.org/v2/guide/components-props.html
     */
    props: ["event"],

    data() {
        return {
            currentAttendanceStatus: this.event.attendance.status.current,
            numMax: this.event.attendance.payload.attendees.max,
            numAvailable: this.event.attendance.payload.attendees.available,
            numPending: this.event.attendance.payload.attendees.pending,
            numAccepted: this.event.attendance.payload.attendees.accepted,
            numDeclined: this.event.attendance.payload.attendees.declined,
            guests: this.event.attendance.payload.attendees.guests,
            canInvite: this.event.attendance.payload.currentUser.canInvite,

            /* Vue Select
             */
            options: [],
            selected: [],

            /* These are probably better off as functions inside computed{}
             * rather than out here, and should be centralised consts
             * instead of happenstance strings but we can deal with it later
             */
            isPersonalEvent: this.event.level === "personal",
            isGroupEvent: this.event.level === "group",
            isCampusEvent: this.event.level === "campus",

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

        monthDisplay: function () {
            return dayjs(this.event.dates.start).format("MMM");
        },

        dateDisplay: function () {
            return dayjs(this.event.dates.start).format("DD");
        },

        audiencesMeta: function () {
            let result = [],
                campusCount = this.event.audiences.campus.length,
                rolesCount = this.event.audiences.roles.length;

            if (this.isPersonalEvent || this.isGroupEvent) {
                return true;
            }

            if (campusCount) {
                let suffix = campusCount > 1 ? " Campuses" : " Campus";
                result.push(campusCount + suffix);
            }

            if (rolesCount) {
                let suffix = rolesCount > 1 ? " Roles" : " Role";
                result.push(rolesCount + suffix);
            }

            return result.join(" & ");
        },

        showMoreGroups: function () {
            let suffix = this.event.audiences.folders.length > 2 ? "groups" : "group";

            return this.event.audiences.folders.length - 1 + " more " + suffix;
        },
    },

    watch: {
        selected: function () {
            if (this.selected.id) {
                this.rsvpCreate(this.selected.id, this.selected.first_name + " " + this.selected.last_name);
            }
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
        this.FORMAT_LONG_DATE = FORMAT_LONG_DATE;
        this.FORMAT_LONG_DATE_WITH_TIME = FORMAT_LONG_DATE_WITH_TIME;
        this.FORMAT_DATE_ONLY = FORMAT_DATE_ONLY;
        this.FORMAT_TIME_ONLY = FORMAT_TIME_ONLY;
    },

    methods: {
        /**
         * Update attendance counts from Payload data
         *
         * @param payload  Payload instance
         */
        updateCountsFromPayload(payload) {
            this.numAvailable = payload.attendees.available;
            this.numPending = payload.attendees.pending;
            this.numAccepted = payload.attendees.accepted;
            this.numDeclined = payload.attendees.declined;
        },

        /**
         * Accept an invitation
         */
        rsvpAccept() {
            if (!this.isAccepted(this.currentAttendanceStatus)) {
                if (!this.isTransacting) {
                    /* If you haven't already accepted,
                     * async request to accept invitation
                     */
                    this.rsvp(ATTENDANCE_STATUS.ACCEPTED, ENDPOINTS.rsvpAcceptPath(this.event.id));
                }
            }
        },

        /**
         * Decline an invitation
         */
        rsvpDecline() {
            if (this.isInvited(this.currentAttendanceStatus)) {
                if (!this.isDeclined(this.currentAttendanceStatus)) {
                    if (!this.isTransacting) {
                        /* If you're invited, and you haven't already declined,
                         * async request to decline invitatino
                         */
                        this.rsvp(ATTENDANCE_STATUS.DECLINED, ENDPOINTS.rsvpDeclinePath(this.event.id));
                    }
                }
            }
        },

        /**
         * Create an invitation
         *
         * @param id        the user's id
         * @param fullname  the user's name
         */
        async rsvpCreate(id, fullname) {
            const toast = $("[data-toast]");
            toast.toastActivate({
                text: "Creating invitation...",
            });

            try {
                const response = await fetch(ENDPOINTS.createAttendancePath(this.event.id), {
                    method: "POST",
                    body: this.getRsvpCreateFormData(id),
                });
                const responseData = await response.json();
                this.updateCountsFromPayload(responseData); //Update data with the result from async request
                this.guests = responseData.attendees.guests;
                toast.toastTimeout({
                    text: `${fullname} has been invited`,
                    css: "success",
                });
            } catch (error) {
                toast.toastTimeout({
                    text: `Unable to invite ${fullname}; please try again.`,
                    css: "alert",
                });
                console.warn(error);
            } finally {
                this.selected = []; //Clear selection after async outcome
            }
        },

        /**
         * Get the form data
         *
         * @param id        the user's id
         */
        getRsvpCreateFormData(id) {
            const formData = new FormData();
            formData.append("guests", id);
            return formData;
        },

        /**
         * Remove an invitation
         *
         * @param id    the user's id
         * @param name  the user's name
         */
        async rsvpDelete(id, name) {
            const toast = $("[data-toast]");
            toast.toastActivate({
                text: "Removing invitation...",
            });

            try {
                const response = await fetch(ENDPOINTS.rsvpDeletePath(this.event.id), {
                    method: "POST",
                    body: this.getRsvpDeleteFormData(id),
                });
                const responseData = await response.json();
                const userId = responseData.currentUser.user.id;

                this.guests = this.guests.filter((guest) => guest.id !== id); //to remove the guest from the list

                if (id === userId) {
                    //If you removed yourself from the list, reset your currentAttendanceStatus to null
                    this.currentAttendanceStatus = null;
                }

                this.updateCountsFromPayload(responseData); //Update data with the result from async request

                toast.toastTimeout({
                    text: `${name} has been uninvited`,
                    css: "success",
                });
            } catch (error) {
                toast.toastTimeout({
                    text: `Unable to uninvite ${fullname}; please try again.`,
                    css: "alert",
                });
                console.warn(error);
            }
        },

        /**
         * Get the form data
         *
         * @param id        the user's id
         */
        getRsvpDeleteFormData(id) {
            const formData = new FormData();
            formData.append("userId", id);
            return formData;
        },

        /**
         * Respond to an invitation with new status $status, using accept/decline endpoint $endpoint
         *
         * @param status    the new status
         * @param endpoint  the endpoint to send this new status
         */
        async rsvp(status, endpoint) {
            this.isTransacting = true; //Set a flag to prevent async spammage

            const toast = $("[data-toast]");
            toast.toastActivate({
                text: "Updating invitation...",
            });

            try {
                const response = await fetch(endpoint, {
                    method: "POST",
                });
                const responseData = await response.json();

                this.currentAttendanceStatus = status; //Update data with the result from async request
                this.updateCountsFromPayload(responseData);
                this.guests = responseData.attendees.guests;

                toast.toastTimeout({
                    text: "Invitation successfully updated",
                    css: "success",
                });
            } catch (error) {
                toast.toastTimeout({
                    text: "Unable to update invitation.",
                    css: "alert",
                });
                console.warn(error);
            } finally {
                this.isTransacting = false; //Reset flag
            }
        },

        /**
         * The Guests island is visible under these conditions
         * This island also contains the attendance list and invite selectize
         *
         * @return boolean
         */
        showGuestsPanel() {
            /* The Guests panel is available when any of the following can be performed:
             *
             * You can rsvp to an event (shows rsvp buttons)
             * There's limited attendance and the attendance list is visible to you (shows metadata about counts)
             * You can modify the event (you can see the Invite Guest selectize and attendance level != None)
             * You can see the attendance list (shows invited dudes)
             */
            return (
                this.canRsvp(
                    this.event.attendance.level.current,
                    this.guests,
                    this.event.attendance.payload.currentUser.user.id,
                    this.currentAttendanceStatus,
                    this.numMax,
                    this.numAvailable,
                ) ||
                (this.isLimitedAttendance(this.numMax) && this.isAttendeesVisible()) ||
                (this.canModify() && !this.isAttendanceLevelNone()) ||
                this.isAttendeesVisible()
            );
        },

        /**
         * Whether you can edit this event,
         * including whether you can delete an invitation
         *
         * @returns boolean
         */
        canModify() {
            return this.event.canModify;
        },

        /**
         * Whether you can invite people to this event
         * Requires WRITE perm and free spaces under the current event configuration and setup
         *
         * @return boolean
         */
        canInviteAttendees() {
            /* To invite others, you must
             *   - be a staff member or the config var lets you invite others
             *   - have available places
             *   - be able to edit the event (you're its author)
             */
            if (this.canInvite && this.canModify()) {
                if (
                    (this.isLimitedAttendance(this.numMax) && this.numAvailable) ||
                    !this.isLimitedAttendance(this.numMax)
                ) {
                    return true;
                }
            }

            return false;
        },

        /**
         * Whether the list of attendees is visible to the current user
         *
         * @return boolean
         */
        isAttendeesVisible() {
            /* If event has Attendance Level None, hide the panel
             */
            if (this.isAttendanceLevelNone()) {
                return false;
            }

            /* If you've got write access, you can always see it
             * regardless of the showAttendees flag
             */
            if (this.canModify()) {
                return true;
            }

            /* Otherwise, check the showAttendees flag
             * If that's on, you can see it
             */
            if (this.event.showAttendees) {
                return true;
            }

            /* Otherwise, it's hidden
             */
            return false;
        },

        /**
         * This event has a Attendance Level of None
         */
        isAttendanceLevelNone: function () {
            return this.event.attendance.level.current === ATTENDANCE_LEVELS.NONE;
        },

        /**
         * Start searching for keyword
         *
         * @param query
         * @param loading
         */
        onSearch(query, loading) {
            loading(true);
            this.search(loading, query, this);
        },

        /**
         * Perform async request and load results into component
         */
        search: debounce(function (loading, query, vm) {
            fetch(
                ENDPOINTS.URL_SELECTIZE +
                    "?" +
                    new URLSearchParams({
                        query: query,
                        groupID: 0,
                    }),
            )
                .then((response) => response.json())
                .then((data) => {
                    vm.options = data;
                    loading(false);
                });
        }, 350),

        showAudiencesModal() {
            this.$refs.audiencesModal.open();
        },
    },
};
</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <div class="actions-small-1 row" v-if="canModify()">
                <div class="list-item"></div>
                <nav>
                    <a :href="this.ENDPOINTS.modifyPath(this.event.id)" class="icon-modify" title="Modify Event"></a>
                </nav>
            </div>
        </div>
        <div class="small-12 medium-8 island">
            <section class="detail content">
                <div class="title-section">
                    <time class="icon">
                        <strong> {{ monthDisplay }}</strong>
                        <span>{{ dateDisplay }}</span>
                    </time>
                    <h1>{{ this.event.title }}</h1>
                    <div class="event-type label" :style="{ 'background-color': this.event.options.type.color }">
                        {{ this.event.options.type.name }}
                    </div>
                </div>
                <div class="meta">
                    <span>
                        <template v-if="event.author.id && event.author.fullName">
                            By <a :href="`/search/user/${event.author.id}`">{{ event.author.fullName }}</a>
                        </template>
                        <template v-else-if="!event.author.id && data.author.fullName">
                            By {{ event.author.fullName }}
                        </template>
                    </span>
                    <template v-if="audiencesMeta">
                        |
                        <span v-if="this.isCampusEvent"
                            ><a @click="showAudiencesModal()">{{ audiencesMeta }}</a></span
                        >
                        <span v-else-if="isPersonalEvent">Personal</span>
                        <span v-else-if="isGroupEvent">
                            <span>
                                <a :href="`/homepage/${event.audiences.folders[0].id}`">{{
                                    event.audiences.folders[0].name
                                }}</a>
                            </span>
                            <span v-if="this.event.audiences.folders.length > 1">
                                and <a @click="showAudiencesModal()">{{ showMoreGroups }}</a></span
                            >
                        </span>
                    </template>
                </div>
                <media-article :content="event.detail"></media-article>
            </section>
        </div>

        <div class="small-12 medium-4 island">
            <section class="detail">
                <div class="content">
                    <h2 class="subheader">Date and Time</h2>
                    <p class="event-datetime-display">
                        <template v-if="event.options.allDay">{{
                            window.dayjs(event.dates.start).format(FORMAT_LONG_DATE)
                        }}</template>
                        <template
                            v-else-if="
                                window.dayjs(event.dates.start).format(FORMAT_DATE_ONLY) ===
                                window.dayjs(event.dates.end).format(FORMAT_DATE_ONLY)
                            "
                        >
                            {{ window.dayjs(event.dates.start).format(FORMAT_LONG_DATE) }}<br />
                            {{ window.dayjs(event.dates.start).format(FORMAT_TIME_ONLY) }}
                            -
                            {{ window.dayjs(event.dates.end).format(FORMAT_TIME_ONLY) }}
                        </template>
                        <template v-else>
                            {{ window.dayjs(event.dates.start).format(FORMAT_LONG_DATE_WITH_TIME) }} -
                            {{ window.dayjs(event.dates.end).format(FORMAT_LONG_DATE_WITH_TIME) }}
                        </template>
                    </p>
                    <div v-if="event.location">
                        <h2 class="subheader">Location</h2>
                        <p>{{ event.location }}</p>
                    </div>
                </div>
            </section>

            <template v-if="showGuestsPanel()">
                <h2 class="subheader">Guests</h2>
                <template
                    v-if="
                        this.canRsvp(
                            this.event.attendance.level.current,
                            this.guests,
                            this.event.attendance.payload.currentUser.user.id,
                            this.currentAttendanceStatus,
                            this.numMax,
                            this.numAvailable,
                        )
                    "
                >
                    <section class="content attending">
                        <p><strong>Attending?</strong></p>

                        <ul class="flex-list buttons rsvp">
                            <li>
                                <a
                                    :href="this.ENDPOINTS.rsvpAcceptPath(this.event.id)"
                                    @click.prevent="rsvpAccept()"
                                    :class="{ accepted: this.isAccepted(this.currentAttendanceStatus) }"
                                    >Yes</a
                                >
                            </li>
                            <li>
                                <a
                                    :href="this.ENDPOINTS.rsvpDeclinePath(this.event.id)"
                                    @click.prevent="rsvpDecline()"
                                    :class="{ declined: this.isDeclined(this.currentAttendanceStatus) }"
                                    >No</a
                                >
                            </li>
                        </ul>
                    </section>
                </template>

                <section>
                    <div
                        class="content"
                        v-if="(this.isLimitedAttendance(this.numMax) && isAttendeesVisible()) || canModify()"
                    >
                        <span class="meta" v-if="this.isLimitedAttendance(this.numMax) && isAttendeesVisible()">
                            <template v-if="this.numAvailable > 0">{{ this.numAvailable }}</template>
                            <template v-else>No</template>
                            available place(s) remaining
                        </span>

                        <template v-if="canInviteAttendees()">
                            <v-select
                                id="createAttendanceForm"
                                :options="options"
                                @search="onSearch"
                                label="name"
                                placeholder="Search for people to invite"
                                v-model="selected"
                            >
                                <template #option="option">
                                    <div class="d-center">
                                        {{ option.name }}
                                    </div>
                                </template>
                                <template #selected-option="option">
                                    <div class="selected d-center">
                                        {{ option.name }}
                                    </div>
                                </template>
                            </v-select>
                        </template>
                    </div>

                    <template v-if="isAttendeesVisible()">
                        <p class="content">
                            <strong>
                                {{ numAccepted }} accepted, {{ numDeclined }} declined, {{ numPending }} pending
                            </strong>
                        </p>

                        <ul class="action-list attendance-guest-list" v-if="guests">
                            <li class="actions-small-1" v-for="guest in guests" :key="guest.id">
                                <div class="compact card small-11">
                                    <a :href="'/search/user/' + guest.id" title="View Profile">
                                        <img
                                            :src="'/portrait.php?id=' + guest.id + '&size=square64'"
                                            alt="portrait photo"
                                        />

                                        <p
                                            :class="{
                                                pending: isPending(guest.status),
                                                accepted: isAccepted(guest.status),
                                                declined: isDeclined(guest.status),
                                            }"
                                        >
                                            {{ guest.name }}<span class="status-icon"></span>
                                        </p>
                                    </a>
                                </div>
                                <nav>
                                    <a
                                        class="icon-close no-text"
                                        v-if="canModify()"
                                        @click.prevent="rsvpDelete(guest.id, guest.name)"
                                        title="Remove guest"
                                    ></a>
                                </nav>
                            </li>
                        </ul>
                    </template>
                </section>
            </template>
        </div>

        <div data-toast class="toast"></div>

        <audiences-modal
            ref="audiencesModal"
            :campus-audiences="this.event.audiences.campus"
            :role-audiences="this.event.audiences.roles"
            :group-audiences="this.event.audiences.folders"
            :is-group="this.isGroupEvent"
        />
    </div>
</template>
