<script>
    import moment           from 'moment';
    import audiencesModal   from './audiencesModal';
    import qs               from 'qs';

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

    export default {
        props: ['event'],
        name: "event-details",
        components: {audiencesModal},
        data() {
            return {
                isPersonalEvent: this.event.level === 'personal',
                isGroupEvent: this.event.level === 'group',
                isCampusEvent: this.event.level === 'campus'
            }
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

            showAudiencesModal() {
                this.$refs.audiencesModal.open();
            }
        },
        computed: {
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
                <div id="rsvpAttendanceForm" class="small-12 columns" style="display:none" :data-guests-available="this.event.attendance.guests.available">
                    <section class="content no-margin">
                        <p><strong>Attending?</strong></p>

                        <ul class="flex-list buttons rsvp">
                            <li><a :href="rsvpAcceptPath" data-rsvp="accept" :class="this.event.attendance.status.current === 'accepted' ? 'accepted' : ''">Yes</a></li>
                            <li><a :href="rsvpDeclinePath" data-rsvp="decline" :class="this.event.attendance.status.current === 'declined' ? 'declined' : ''">No</a></li>
                        </ul>
                    </section>
                </div>
                <div id="attendance-guests" class="small-12 columns">
                    <section>
                        <div class="content" v-if="this.event.attendance.guests.available != null || this.event.canModify">
                            <span class="meta" v-if="this.event.attendance.guests.available != null">
                                <span id="attendance-guests-available" :data-available="this.event.attendance.guests.available">
                                    <template v-if="this.event.attendance.guests.available > 0">{{ this.event.attendance.guests.available }}</template>
                                    <template v-else>No</template>
                                </span> available place(s) remaining
                            </span>

                            <form :id="'createAttendanceForm'" :action="createAttendancePath" :style="{display: 'none'}" method="post" v-if="this.event.canModify">
                                <input id='add-guests' name="guests" type='text' value='' placeholder="Start typing the name of a user" />
                            </form>
                        </div>

                        <article v-html="this.event.attendance.guests.fragment"></article>
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

