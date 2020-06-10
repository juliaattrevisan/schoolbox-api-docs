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
                <div class="component-action" v-if="this.event.options.isAttendable">
                    <section>
                        <a href="#"
                           class="button"
                           v-if="this.event.options.isAttendable"
                           @click="toggleAttendance()"
                           :disabled="this.attendanceButtonIsDisabled">
                            {{ attendanceActionLabel }}
                        </a>
                    </section>
                </div>
            </section>

            <div class="row" v-if="this.event.options.isAttendable">
                <div id="rsvpAttendanceForm" class="small-12 columns" style="display:none" :data-guests-available="this.event.guests.available">
                    <h2 class="subheader">Guests</h2>
                    <section class="content no-margin">
                        <p><strong>Attending?</strong></p>

                        <form :action="''" method="post">
                            <ul class="flex-list buttons">
                                <li><a href="#">Yes</a></li>
                                <li><a href="#">No</a></li>
                            </ul>
                        </form>
                    </section>
                </div>
                <div id="attendance-guests" class="small-12 columns">
                    <section>
                        <div class="content">
                            <span class="meta" v-if="this.event.guests.available != null">
                                <span id="attendance-guests-available" v-if="this.event.guests.available > 0">{{ this.event.guests.available }}</span>
                                <span id="attendance-guests-available" v-else>No</span>
                                available places remaining
                            </span>

                            <form :id="'createAttendanceForm'" :action="createAttendancePath" method="post">
                                <input id='add-guests' name="guests" type='text' value='' placeholder="Start typing the name of a user" />
                            </form>
                        </div>

                        <article v-html="this.event.guests.fragment"></article>
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

<script>
    import moment           from 'moment';
    import audiencesModal   from './audiencesModal';
    import qs               from 'qs';

    const FORMAT_LONG_DATE = 'dddd, MMMM DD, YYYY'; // Monday, October 01, 2019
    const FORMAT_LONG_DATE_WITH_TIME = 'LLLL'; // Monday, October 01, 2019 02:30 PM
    const FORMAT_DATE_ONLY = 'MM-DD-YYYY'; // 10-01-2019
    const FORMAT_TIME_ONLY = 'LT'; // 02:30 PM
    const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';
    const URL_EVENT_MODIFY_FORM = '/calendar/event/{eventId}/modify';
    const URL_ATTENDEES = '/calendar/attendance/list/';
    const ATTENDANCE_NEXT_ACTION_ADD = 'add';
    const ATTENDANCE_NEXT_ACTION_REMOVE = 'remove';
    const ATTENDANCE_NEXT_ACTION_ADD_LABEL = 'RSVP';
    const ATTENDANCE_NEXT_ACTION_REMOVE_LABEL = 'Unattend';

    /* Inline alteration of attendees on event detail screen
     */
    const URL_ATTENDANCE_CREATE = '/calendar/event/attendance/{eventId}/create';
    const URL_ATTENDANCE_DELETE = '/calendar/event/attendance/{eventId}/delete';

    export default {
        props: ['event'],
        name: "event-details",
        components: {audiencesModal},
        data() {
            return {
                attendanceNextAction: this.event.attendance.nextAction,
                attendanceActionLabel: (this.event.attendance.nextAction === ATTENDANCE_NEXT_ACTION_ADD) ? ATTENDANCE_NEXT_ACTION_ADD_LABEL : ATTENDANCE_NEXT_ACTION_REMOVE_LABEL,
                attendanceButtonIsDisabled: false,
                isPersonalEvent: this.event.level === 'personal',
                isGroupEvent: this.event.level === 'group',
                isCampusEvent: this.event.level === 'campus',
                attendanceLink: URL_ATTENDEES + this.event.id
            }
        },
        methods: {

            /**
             * Route to the Event Modify screen with optional returnUrl redirect
             *
             * TODO: Obtain from backend, I guess, instead of hardcoding it front-end
             */
            generateModifyUrl(eventId, returnUrl) {
                var url = URL_EVENT_MODIFY_FORM.replace('{eventId}', eventId);

                if (returnUrl !== undefined) {
                    url += '?returnUrl=' + returnUrl;
                }

                return url;
            },

            /**
             * Route to the Attendance Create endpoint to asynchronously create attendees
             *
             * @param eventId
             * @param returnUrl
             * @returns {string}
             */
            generateAttendanceCreateUrl(eventId, returnUrl) {
                var url= URL_ATTENDANCE_CREATE.replace('{eventId}', eventId);

                if (returnUrl !== undefined) {
                    url += '?returnUrl=' + returnUrl;
                }

                return url;
            },


            /**
             * Route to the Attendance Delete endpoint to asynchronously remove attendees
             *
             * @param eventId
             * @param returnUrl
             * @returns {string}
             */
            generateAttendanceDeleteUrl(eventId, returnUrl) {
                var url= URL_ATTENDANCE_DELETE.replace('{eventId}', eventId);

                if (returnUrl !== undefined) {
                    url += '?returnUrl=' + returnUrl;
                }

                return url;
            },

            showAudiencesModal() {
                this.$refs.audiencesModal.open();
            },

            toggleAttendance() {
                let self = this,
                    data = {
                        status:      this.attendanceNextAction,
                        'event[id]': this.event.id,
                    };

                self.attendanceButtonIsDisabled = true;

                axios.post(URL_ATTENDANCE_TOGGLE, qs.stringify(data)).then(function () {

                    if (self.attendanceNextAction === ATTENDANCE_NEXT_ACTION_ADD) {
                        self.attendanceActionLabel = ATTENDANCE_NEXT_ACTION_REMOVE_LABEL;
                        self.attendanceNextAction = ATTENDANCE_NEXT_ACTION_REMOVE;
                    } else {
                        self.attendanceActionLabel = ATTENDANCE_NEXT_ACTION_ADD_LABEL;
                        self.attendanceNextAction = ATTENDANCE_NEXT_ACTION_ADD;
                    }

                }).finally(function() {
                    self.attendanceButtonIsDisabled = false;
                });
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

            modifyPath: function() {
                return this.generateModifyUrl(this.event.id, btoa(window.location.pathname));
            },

            /**
             * Create new attendances
             */
            createAttendancePath: function() {
                return this.generateAttendanceCreateUrl(this.event.id);
            },

            /**
             * Delete attendances
             */
            deleteAttendancePath: function() {
                return this.generateAttendanceDeleteUrl(this.event.id);
            }
        }
    }
</script>
