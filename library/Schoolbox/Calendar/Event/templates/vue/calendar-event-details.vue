<script>
    import moment           from 'moment';
    import AudiencesModal   from './audiences-modal';
    import qs               from 'qs';
    import axios            from "axios";

    /* Path aliases like @schoolboxCalendarEvent are defined in webpack.config.js
     */
    import {ATTENDANCE_STATUS} from '@schoolboxCalendarEvent/attendance-status';
    import {ATTENDANCE_LEVELS} from '@schoolboxCalendarEvent/attendance-levels';
    import {ENDPOINTS}         from '@schoolboxCalendarEvent/endpoints';
    import checks              from '@schoolboxCalendarEvent/checks';

    /* Vue select
     */
    import vSelect from "vue-select";
    import "vue-select/dist/vue-select.css";

    /* Render custom template for Deselect icon/button
     * It's not well documented, but you can trawl through closed PRs to figger out how
     * https://github.com/sagalbot/vue-select/issues/994
     */
    import Deselect from '@vue/components/selectize/Deselect';
    vSelect.props.components.default = () => ({ Deselect });

    const FORMAT_LONG_DATE = 'dddd, MMMM DD, YYYY'; // Monday, October 01, 2019
    const FORMAT_LONG_DATE_WITH_TIME = 'LLLL'; // Monday, October 01, 2019 02:30 PM
    const FORMAT_DATE_ONLY = 'MM-DD-YYYY'; // 10-01-2019
    const FORMAT_TIME_ONLY = 'LT'; // 02:30 PM

    export default {

        name: "event-details",

        components: {
            AudiencesModal,
            vSelect
        },
        
        /* All props form a one-way-down binding between the child property and the parent one:
         * when the parent property updates, it will flow down to the child, but not the other way around
         * https://vuejs.org/v2/guide/components-props.html
         */
        props: [
            'event'
        ],
        
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
                isPersonalEvent: this.event.level === 'personal',
                isGroupEvent: this.event.level === 'group',
                isCampusEvent: this.event.level === 'campus',

                /* Keep track of when an async request is in progress, locking
                 * out other async requests until this has finished, preventing
                 * rsvp-button spamming
                 */
                isTransacting: false
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
            }
        },

        watch: {
            selected: function() {
                if (this.selected.id) {
                    this.rsvpCreate(this.selected.id, this.selected.first_name + ' ' + this.selected.last_name);
                }
            }
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
            rsvpCreate(id, fullname) {
                let self = this;
                let toast = this.jQuery('[data-toast]');

                let request = {
                    url: ENDPOINTS.createAttendancePath(this.event.id),
                    data:  qs.stringify({guests: id}),
                    method: 'post',
                    responseType: 'json'
                }

                /* A toast - to your valour, my sword, and our victory together
                 */
                toast.toastActivate({
                    text: 'Creating invitation...'
                });

                axios.request(request)
                        .then(function (response) {

                            /* Update data with the result from async request
                             */
                            self.updateCountsFromPayload(response.data);
                            self.guests = response.data.attendees.guests;

                            toast.toastTimeout({
                                text: fullname + ' has been invited',
                                css: 'success'
                            });
                        })
                        .catch(error => {
                            if (error.response) {
                                toast.toastTimeout({
                                    text: 'Unable to invite ' + fullname + '; please try again.',
                                    css: 'alert'
                                });
                            }
                        })
                        .then(function() {

                            /* Clear selection after async outcome
                             */
                            self.selected = [];
                        });
            },


            /**
             * Remove an invitation
             *
             * @param id    the user's id
             * @param name  the user's name
             */
            rsvpDelete(id, name) {
                let self = this;
                let toast = this.jQuery('[data-toast]');

                let request = {
                    url: ENDPOINTS.rsvpDeletePath(this.event.id),
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
                            let userId = response.data.currentUser.user.id;

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
                            self.updateCountsFromPayload(response.data);

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
             * @param status    the new status
             * @param endpoint  the endpoint to send this new status
             */
            rsvp(status, endpoint) {
                let self = this;
                let toast = this.jQuery('[data-toast]');

                /* Set a flag to prevent async spammage
                 */
                self.isTransacting = true;

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

                            /* Update data with the result from async request
                             */
                            self.currentAttendanceStatus = status;
                            self.updateCountsFromPayload(response.data);
                            self.guests = response.data.attendees.guests;

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
                        })
                        .then(function (response) {

                            /* Reset flag
                             */
                            self.isTransacting = false;
                        });
            },


            /**
             * The Guests island is visible under these conditions
             * This island also contains the attendance list and invite selectize
             *
             * @return boolean
             */
            showGuestsPanel()
            {
                /* The Guests panel is available when any of the following can be performed:
                 *
                 * You can rsvp to an event (shows rsvp buttons)
                 * There's limited attendance and the attendance list is visible to you (shows metadata about counts)
                 * You can modify the event (you can see the Invite Guest selectize and attendance level != None)
                 * You can see the attendance list (shows invited dudes)
                 */
                return this.canRsvp(this.event.attendance.level.current, this.guests, this.event.attendance.payload.currentUser.user.id, this.currentAttendanceStatus, this.numMax, this.numAvailable) ||
                        (this.isLimitedAttendance(this.numMax) && this.isAttendeesVisible()) ||
                        (this.canModify() && !this.isAttendanceLevelNone()) ||
                        this.isAttendeesVisible();
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
                 *   - be able to edit the event (you're its author)
                 *   - have available places
                 */
                if (this.canInvite && this.canModify()) {
                    if ((this.isLimitedAttendance(this.numMax) && this.numAvailable) || !this.isLimitedAttendance(this.numMax)) {
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
            isAttendanceLevelNone: function() {
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
            search: _.debounce(function(loading, query, vm) {
                axios.get(ENDPOINTS.URL_SELECTIZE, {
                    params: {
                        query: query,
                        groupID: 0
                    }
                })
                .then(function(response) {
                    vm.options = response.data;
                    loading(false);
                });
            }, 350),


            showAudiencesModal() {
                this.$refs.audiencesModal.open();
            }
        }

    }

</script>

<template>
    <div class="row">
        <div class="small-12 columns">
            <div class="actions-small-1 row" v-if="canModify()">
                <div class="list-item"></div>
                <nav><a :href="this.ENDPOINTS.modifyPath(this.event.id)" class="icon-modify" title="Modify Event"></a></nav>
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

            <template v-if="showGuestsPanel()">
                <h2 class="subheader">Guests</h2>
                    <template v-if="this.canRsvp(this.event.attendance.level.current, this.guests, this.event.attendance.payload.currentUser.user.id, this.currentAttendanceStatus, this.numMax, this.numAvailable)">
                        <section class="content attending">
                            <p><strong>Attending?</strong></p>

                            <ul class="flex-list buttons rsvp">
                                <li><a :href="this.ENDPOINTS.rsvpAcceptPath(this.event.id)" @click.prevent="rsvpAccept()" :class="{ accepted: this.isAccepted(this.currentAttendanceStatus) }">Yes</a></li>
                                <li><a :href="this.ENDPOINTS.rsvpDeclinePath(this.event.id)" @click.prevent="rsvpDecline()" :class="{ declined: this.isDeclined(this.currentAttendanceStatus) }">No</a></li>
                            </ul>
                        </section>
                    </template>

                    <section>
                        <div class="content" v-if="(this.isLimitedAttendance(this.numMax) && isAttendeesVisible()) || canModify()">
                            <span class="meta" v-if="this.isLimitedAttendance(this.numMax) && isAttendeesVisible()">
                                <template v-if="this.numAvailable > 0">{{ this.numAvailable }}</template>
                                <template v-else>No</template>
                                available place(s) remaining
                            </span>

                            <template v-if="canInviteAttendees()">
                                <v-select id="createAttendanceForm" :options="options" @search="onSearch" label="name" placeholder="Search for people to invite" v-model="selected">
                                    <template slot="option" slot-scope="option">
                                        <div class="d-center">
                                            {{ option.name }}
                                        </div>
                                    </template>
                                    <template slot="selected-option" slot-scope="option">
                                        <div class="selected d-center">
                                            {{ option.name }}
                                        </div>
                                    </template>
                                </v-select>
                            </template>
                        </div>

                        <template v-if="isAttendeesVisible()">
                            <p class="content"><strong>
                                {{ numAccepted }} accepted,
                                {{ numDeclined }} declined,
                                {{ numPending }} pending
                            </strong></p>

                            <ul class="action-list attendance-guest-list" v-if="guests">
                                <li class="actions-small-1" v-for="guest in guests" :key="guest.id">
                                    <div class="compact card small-11">
                                        <a :href="'/search/user/' + guest.id" title="View Profile">
                                            <img :src="'/portrait.php?id=' + guest.id + '&size=square64'" />

                                            <p :class="{
                                                pending: isPending(guest.status),
                                                accepted: isAccepted(guest.status),
                                                declined: isDeclined(guest.status)
                                            }">{{ guest.name }}<span class="status-icon"></span></p>
                                        </a>
                                    </div>
                                    <nav>
                                        <a class="icon-close no-text" v-if="canModify()" @click.prevent="rsvpDelete(guest.id, guest.name)" title="Remove guest"></a>
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
