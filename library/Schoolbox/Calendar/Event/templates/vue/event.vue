<script>
    import moment           from 'moment';
    import audiencesModal   from './audiencesModal';
    import qs               from 'qs';
    import axios from "axios";

    const FORMAT_LONG_DATE = 'dddd, MMMM DD, YYYY'; // Monday, October 01, 2019
    const FORMAT_LONG_DATE_WITH_TIME = 'LLLL'; // Monday, October 01, 2019 02:30 PM
    const FORMAT_DATE_ONLY = 'MM-DD-YYYY'; // 10-01-2019
    const FORMAT_TIME_ONLY = 'LT'; // 02:30 PM

    /* Event Modify screen
     */
    const URL_EVENT_MODIFY_FORM = '/calendar/event/{eventId}/modify';

    /* Inline alteration of attendees on event detail screen
     */
    const URL_ATTENDANCE_CREATE = '/calendar/event/attendance/{eventId}/create';
    const URL_ATTENDANCE_DELETE = '/calendar/event/attendance/{eventId}/delete';

    /* RSVP Status mutation routes
     */
    const URL_ATTENDANCE_RSVP_ACCEPT = '/calendar/event/attendance/{eventId}/accept';
    const URL_ATTENDANCE_RSVP_DECLINE = '/calendar/event/attendance/{eventId}/decline';
    const URL_ATTENDANCE_RSVP_DELETE = '/calendar/event/attendance/{eventId}/delete';

    /* RSVP Status consts synonymous with Attendance/Model consts
     */
    const ATTENDANCE_STATUS_PENDING = 1;
    const ATTENDANCE_STATUS_ACCEPTED = 2;
    const ATTENDANCE_STATUS_DECLINED = 3;

    export default {

        /* All props form a one-way-down binding between the child property and the parent one:
         * when the parent property updates, it will flow down to the child, but not the other way around
         * https://vuejs.org/v2/guide/components-props.html
         */
        props: [
            'event'
        ],
        name: "event-details",
        components: {
            audiencesModal
        },
        created() {

            /* RSVP Status consts synonymous with Attendance/Model consts
             *
             * Expost consts in vue land
             * https://stackoverflow.com/questions/42662144/how-could-i-use-const-in-vue-template
             */
            this.ATTENDANCE_STATUS_PENDING = ATTENDANCE_STATUS_PENDING;
            this.ATTENDANCE_STATUS_ACCEPTED = ATTENDANCE_STATUS_ACCEPTED;
            this.ATTENDANCE_STATUS_DECLINED = ATTENDANCE_STATUS_DECLINED;
        },
        data() {
            return {
                currentAttendanceStatus: this.event.attendance.status.current,
                numAvailable: this.event.attendance.payload.attendees.available,
                numPending: this.event.attendance.payload.attendees.pending,
                numAccepted: this.event.attendance.payload.attendees.accepted,
                numDeclined: this.event.attendance.payload.attendees.declined,
                guests: this.event.attendance.payload.guests,

                isPersonalEvent: this.event.level === 'personal',
                isGroupEvent: this.event.level === 'group',
                isCampusEvent: this.event.level === 'campus'
            }
        },
        computed: {
            window: () => window,
            jQuery: () => window.jQuery,

            dateTimeDisplay: function() {
                if (this.event.options.allDay) return moment(this.event.dates.start).format(FORMAT_LONG_DATE);

                let startDate = moment(this.event.dates.start),
                        endDate = moment(this.event.dates.end);

                if (startDate.format(FORMAT_DATE_ONLY) === endDate.format(FORMAT_DATE_ONLY)) {
                    return startDate.format(FORMAT_LONG_DATE) +
                            '<br />' + startDate.format(FORMAT_TIME_ONLY) + ' - ' + endDate.format(FORMAT_TIME_ONLY)
                }

                return startDate.format(FORMAT_LONG_DATE_WITH_TIME) + ' - ' + endDate.format(FORMAT_LONG_DATE_WITH_TIME);
            },

            monthDisplay: function() {
                return moment(this.event.dates.start).format('MMM');
            },

            dateDisplay: function() {
                return moment(this.event.dates.start).format('DD');
            },

            byLine: function() {
                if (this.event.author.id && this.event.author.fullName) return 'By ' + '<a href="/search/user/' + this.event.author.id + '">' + this.event.author.fullName + '</a>';
                if (!this.event.author.id && this.data.author.fullName) return 'By ' + this.event.author.fullName;

                return '';
            },

            audiencesMeta: function() {
                let result = [],
                        campusCount = this.event.audiences.campus.length,
                        rolesCount  = this.event.audiences.roles.length;

                if (this.isPersonalEvent) return 'Personal';
                if (this.isGroupEvent) return '<a href="/homepage/'+ this.event.audiences.folders[0].id +'">' + this.event.audiences.folders[0].name  + '</a>';

                if (campusCount) {
                    let suffix = (campusCount > 1) ? ' Campuses' : ' Campus';
                    result.push(campusCount + suffix);
                }

                if (rolesCount) {
                    let suffix = (rolesCount > 1) ? ' Roles' : ' Role';
                    result.push(rolesCount + suffix);
                }

                return result.join(' & ');
            },

            showMoreGroups: function() {
                let suffix = (this.event.audiences.folders.length > 2) ? 'groups' : 'group';

                return (this.event.audiences.folders.length - 1) + ' more ' + suffix;
            },

            /**
             * Onwards to the modify screen
             */
            modifyPath: function() {
                return this.generateUrlFromConst(URL_EVENT_MODIFY_FORM, this.event.id, btoa(window.location.pathname));
            },

            /**
             * Create new attendances
             */
            createAttendancePath: function() {
                return this.generateUrlFromConst(URL_ATTENDANCE_CREATE, this.event.id, btoa(window.location.pathname));
            },

            /**
             * Delete attendances
             */
            deleteAttendancePath: function() {
                return this.generateUrlFromConst(URL_ATTENDANCE_DELETE, this.event.id, btoa(window.location.pathname));
            },

            /**
             * RSVP Accept
             */
            rsvpAcceptPath: function() {
                return this.generateUrlFromConst(URL_ATTENDANCE_RSVP_ACCEPT, this.event.id, btoa(window.location.pathname));
            },

            /**
             * RSVP Decline
             */
            rsvpDeclinePath: function() {
                return this.generateUrlFromConst(URL_ATTENDANCE_RSVP_DECLINE, this.event.id, btoa(window.location.pathname));
            },

            /**
             * RSVP Delete
             */
            rsvpDeletePath: function() {
                return this.generateUrlFromConst(URL_ATTENDANCE_RSVP_DELETE, this.event.id, btoa(window.location.pathname));
            },
        },
        methods: {

            /**
             * Generate a path via const
             */
            generateUrlFromConst(str, eventId, returnUrl) {
                let url = str.replace('{eventId}', eventId);

                if (returnUrl !== undefined) {
                    url += '?returnUrl=' + returnUrl;
                }

                return url;
            },

            /**
             * Accept an invitation
             */
            rsvpAccept() {
                this.rsvp(ATTENDANCE_STATUS_ACCEPTED, this.rsvpAcceptPath);
            },

            /**
             * Decline an invitation
             */
            rsvpDecline() {
                this.rsvp(ATTENDANCE_STATUS_DECLINED, this.rsvpDeclinePath);
            },

            /**
             * Remove an invitation
             */
            rsvpDelete(id, name) {
                let self = this;
                let toast = this.jQuery('[data-toast]');

                let request = {
                    url: this.rsvpDeletePath,
                    data:  qs.stringify({userId: id}),
                    method: 'post',
                    responseType: 'json'
                }

                /* A toast - to your valour, my sword, and our victory together
                 */
                toast.toastActivate({
                    text: 'Removing invitation...'
                });

                axios.request(request)
                        .then(function (response) {
                            let userId = response.data.user.id;

                            for (let i = 0; i < self.guests.length; i++) {
                                if (self.guests[i].id === id) {

                                    /* Remove invitation from list
                                     */
                                    self.guests.splice(i, 1);

                                    /* If you removed yourself from the list,
                                     * reset your currentAttendanceStatus to null
                                     */
                                    if (id === userId) {
                                        self.currentAttendanceStatus = null;
                                    }

                                    break;
                                }
                            }

                            /* Update data with the result from async request
                             */
                            self.numAvailable = response.data.attendees.available
                            self.numPending = response.data.attendees.pending;
                            self.numAccepted = response.data.attendees.accepted;
                            self.numDeclined = response.data.attendees.declined;

                            toast.toastTimeout({
                                text: name + ' has been uninvited',
                                css: 'success'
                            });
                        })
                        .catch(error => {
                            if (error.response) {
                                toast.toastTimeout({
                                    text: 'Unable to uninvite ' + name + '; please try again.',
                                    css: 'alert'
                                });
                            }
                        });
            },

            /**
             * Respond to an invitation with new status $status, using accept/decline endpoint $endpoint
             *
             * @param status
             * @param endpoint
             */
            rsvp(status, endpoint) {
                let self = this;
                let attendanceList = this.jQuery('#attendance-guests');
                let toast = this.jQuery('[data-toast]');

                let request = {
                    url: endpoint,
                    method: 'post',
                    responseType: 'json'
                }

                /* A toast - to your valour, my sword, and our victory together
                 */
                toast.toastActivate({
                    text: 'Updating invitation...'
                });

                axios.request(request)
                        .then(function (response) {
                            attendanceList.find('article').html(response.data.fragment);

                            let userId = response.data.user.id;
                            let fullname = response.data.user.title + ' ' + response.data.user.firstname + ' ' + response.data.user.lastname;
                            let found = false;

                            /* Update data with the result from async request
                             */
                            self.currentAttendanceStatus = status;
                            self.numAvailable = response.data.attendees.available;
                            self.numPending = response.data.attendees.pending;
                            self.numAccepted = response.data.attendees.accepted;
                            self.numDeclined = response.data.attendees.declined;

                            for (let i = 0; i < self.guests.length; i++) {
                                if (self.guests[i].id === userId) {
                                    self.guests[i].status = status;
                                    found = true;
                                    break;
                                }
                            }

                            if (!found) {
                                self.guests.push({
                                    id: userId,
                                    name: fullname,
                                    status: status
                                });
                            }

                            toast.toastTimeout({
                                text: 'Invitation successfully updated',
                                css: 'success'
                            });
                        })
                        .catch(error => {
                            if (error.response) {
                                toast.toastTimeout({
                                    text: 'Unable to update invitation. ' + error.response.data.message,
                                    css: 'alert'
                                });
                            }
                        });
            },


            /**
             * Decide whether you can RSVP to an invitation or, alternatively,
             * insert yourself into the guest list (same approach either way)
             */
            canRsvp(id) {

                /* If your currentAttendanceStatus is null and there are available spaces,
                 * then you haven't yet responded and are able to insert yourself into the list
                 *
                 * The buttons should be shown
                 */

                /* If your currentAttendanceStatus is null and there aren't available spaces
                 * then you can't insert yourself into the list
                 *
                 * The buttons should be hidden
                 */

                /* If your currentAttendanceStatus is Accepted, you can always decline
                 * freeing up a spot for someone else to occupy
                 *
                 * The buttons should be shown
                 */

                /* If your currentAttendanceStatus is Declined and there are available spaces
                 * then you can change your mind and accept the invitation
                 *
                 * The buttons should be shown
                 */

                /* If your currentAttendanceStatus is Declined and there aren't available spaces
                 * then you can't change your mind; embrace it
                 *
                 * The buttons should be hidden
                 */
            },

            /**
             * Decide whether $id is currently on the guest list, regardless of status
             *
             * @param id
             * @returns {boolean}
             */
            isInvited(id) {
                for (let i = 0; i < this.guests.length; i++) {
                    if (this.guests[i].id === id) {
                        return true;
                    }
                }

                return false;
            },

            showAudiencesModal() {
                this.$refs.audiencesModal.open();
            }
        }
    }
</script>

<template>
    <div>
        <div class="small-12 columns" v-if="this.event.canModify">
            <div class="actions-small-2 row">
                <nav><a :href="modifyPath" class="icon-modify" title="Modify Event"></a></nav>
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
                    <div class="event-type label" :style="{'background-color': this.event.options.type.color}">{{ this.event.options.type.name }}</div>
                </div>
                <div class="meta">
                    <span v-html="byLine"></span>
                    <template v-if="audiencesMeta"> |
                        <span v-if="this.isCampusEvent"><a @click="showAudiencesModal()">{{ audiencesMeta }}</a></span>
                        <span v-else-if="this.isPersonalEvent">{{ audiencesMeta }}</span>
                        <span v-else-if="this.isGroupEvent">
                            <span v-html="audiencesMeta"></span>
                            <span v-if="this.event.audiences.folders.length > 1"> and <a @click="showAudiencesModal()">{{ showMoreGroups }}</a></span>
                        </span>
                    </template>
                </div>
                <article v-html="this.event.detail"></article>
            </section>
        </div>

        <div class="small-12 medium-4 island">
            <section class="detail">
                <div class="content">
                    <h2 class="subheader">Date and Time</h2>
                    <p class="event-datetime-display" v-html="dateTimeDisplay"></p>

                    <div v-if="this.event.location">
                        <h2 class="subheader">Location</h2>
                        <p v-html="this.event.location"></p>
                    </div>
                </div>
            </section>

            <h2 class="subheader">Guests</h2>

            <div class="row">
                <div id="rsvpAttendanceForm" class="small-12 columns">
                    <section class="content no-margin">
                        <p><strong>Attending?</strong></p>

                        <ul class="flex-list buttons rsvp">
                            <li><a :href="rsvpAcceptPath" @click.prevent="rsvpAccept" :class="{ accepted: this.currentAttendanceStatus === ATTENDANCE_STATUS_ACCEPTED }">Yes</a></li>
                            <li><a :href="rsvpDeclinePath" @click.prevent="rsvpDecline" :class="{ declined: this.currentAttendanceStatus === ATTENDANCE_STATUS_DECLINED }">No</a></li>
                        </ul>
                    </section>
                </div>
                <div id="attendance-guests" class="small-12 columns">
                    <section>
                        <div class="content" v-if="this.numAvailable != null || this.event.canModify">
                            <span class="meta" v-if="this.numAvailable != null">
                                <span id="attendance-guests-available">
                                    <template v-if="this.numAvailable > 0">{{ this.numAvailable }}</template>
                                    <template v-else>No</template>
                                </span> available place(s) remaining
                            </span>

                            <form :id="'createAttendanceForm'" :action="createAttendancePath" :style="{display: 'none'}" method="post" v-if="this.event.canModify && this.numAvailable">
                                <input id='add-guests' name="guests" type='text' value='' placeholder="Start typing the name of a user" />
                            </form>
                        </div>

                        <p class="content"><strong>
                            {{ numAccepted }} accepted,
                            {{ numDeclined }} declined,
                            {{ numPending }} pending
                        </strong></p>

                        <ul class="action-list attendance-guest-list" v-if="guests">
                            <li class="actions-small-1" v-for="(guest, index) in guests">
                                <div class="compact card small-11">
                                    <a :href="'/search/user/' + guest.id" title="View Profile">
                                        <img :src="'/portrait.php?id=' + guest.id + '&size=square64'" />

                                        <p :class="{
                                            pending: guest.status === ATTENDANCE_STATUS_PENDING,
                                            accepted: guest.status === ATTENDANCE_STATUS_ACCEPTED,
                                            declined: guest.status === ATTENDANCE_STATUS_DECLINED
                                        }">{{ guest.name }}<span class="status-icon"></span></p>
                                    </a>
                                </div>
                                <nav>
                                    <a class="icon-close no-text" @click.prevent="rsvpDelete(guest.id, guest.name)" title="Remove guest"></a>
                                </nav>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>

        <div data-toast class="toast"></div>

        <audiences-modal
                ref="audiencesModal"
                :campus-audiences="this.event.audiences.campus"
                :role-audiences="this.event.audiences.roles"
                :group-audiences="this.event.audiences.folders"
                :is-group="this.isGroupEvent"
        ></audiences-modal>
    </div>
</template>

