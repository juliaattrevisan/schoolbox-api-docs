<template>
    <div>
        <div class="small-12 medium-8 island">
            <section class="detail content">
                <div class="event-type">
                    <span :style="{'background-color': this.event.options.type.color}">{{ this.event.options.type.name }}</span>
                </div>
                <div class="title-section">
                    <time datetime="2014-09-20" class="icon">
                        <strong :style="{'background-color': this.event.options.type.color}"> {{ monthDisplay }}</strong>
                        <span>{{ dateDisplay }}</span>
                    </time>

                    <h1>{{ this.event.title }}</h1>
                </div>
                <div class="meta">
                    <span v-html="byLine"></span>
                    <span v-if="audiencesMeta"> | <a @click="showAudiencesModal()">{{ audiencesMeta }}</a></span>
                </div>
                <article v-html="this.event.detail"></article>
            </section>
        </div>

        <div class="small-12 medium-4 island">
            <section class="detail content">
                <a href="#" class="button" :class="attendanceIcon" v-if="this.event.options.isAttendable" @click="toggleAttendance()" :disabled="this.attendanceButtonIsDisabled">{{ attendanceActionLabel }}</a>
                <a href="#" class="button icon-calendar">Add to Calendar</a>

                <h4>Date and Time</h4>
                <p class="event-datetime-display" v-html="dateTimeDisplay"></p>

                <h4>Location</h4>
                <p v-html="this.event.location"></p>
            </section>
        </div>

        <auidences-modal
                ref="audiencesModal"
                :campus-audiences="this.event.audiences.campus"
                :role-audiences="this.event.audiences.roles"
        ></auidences-modal>

    </div>
</template>

<script>
    import moment           from 'moment';
    import auidencesModal   from './audiencesModal';
    import qs               from 'qs';

    const FORMAT_LONG_DATE = 'dddd, MMMM DD, YYYY'; // Monday, October 01, 2019
    const FORMAT_LONG_DATE_WITH_TIME = 'LLLL'; // Monday, October 01, 2019 02:30 PM
    const FORMAT_DATE_ONLY = 'MM-DD-YYYY'; // 10-01-2019
    const FORMAT_TIME_ONLY = 'LT'; // 02:30 PM
    const URL_ATTENDANCE_TOGGLE = '/service/attendance/setAttendance';
    const ATTENDANCE_APPROVE_ICON = 'icon-approve';
    const ATTENDANCE_CLOSE_ICON = 'icon-close';
    const ATTENDANCE_NEXT_ACTION_ADD = 'add';
    const ATTENDANCE_NEXT_ACTION_REMOVE = 'remove';
    const ATTENDANCE_NEXT_ACTION_ADD_LABEL = 'RSVP';
    const ATTENDANCE_NEXT_ACTION_REMOVE_LABEL = 'Unattend';

    export default {
        props: ['event'],
        name: "event-details",
        components: {auidencesModal},
        data() {
            return {
                attendanceNextAction: this.event.attendance.nextAction,
                attendanceActionLabel: (this.event.attendance.nextAction === ATTENDANCE_NEXT_ACTION_ADD) ? ATTENDANCE_NEXT_ACTION_ADD_LABEL : ATTENDANCE_NEXT_ACTION_REMOVE_LABEL,
                attendanceIcon: (this.event.attendance.nextAction === ATTENDANCE_NEXT_ACTION_ADD) ? ATTENDANCE_APPROVE_ICON : ATTENDANCE_CLOSE_ICON,
                attendanceButtonIsDisabled: false
            }
        },
        methods: {
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
                        self.attendanceIcon = ATTENDANCE_CLOSE_ICON;
                        self.attendanceNextAction = ATTENDANCE_NEXT_ACTION_REMOVE;
                    } else {
                        self.attendanceActionLabel = ATTENDANCE_NEXT_ACTION_ADD_LABEL;
                        self.attendanceIcon = ATTENDANCE_APPROVE_ICON;
                        self.attendanceNextAction = ATTENDANCE_NEXT_ACTION_ADD;
                    }

                }).finally(function() {
                    self.attendanceButtonIsDisabled = false;
                });
            }
        },
        computed: {
            dateTimeDisplay: function() {
                if (this.event.options.allDay) {
                    return moment(this.event.dates.start).format(FORMAT_LONG_DATE)
                } else {
                    let startDate = moment(this.event.dates.start),
                        endDate = moment(this.event.dates.end);

                    if (startDate.format(FORMAT_DATE_ONLY) === endDate.format(FORMAT_DATE_ONLY)) {
                        return startDate.format(FORMAT_LONG_DATE) +
                                '<br />' + startDate.format(FORMAT_TIME_ONLY) + ' - ' + endDate.format(FORMAT_TIME_ONLY)
                    } else {
                        return startDate.format(FORMAT_LONG_DATE_WITH_TIME) + ' - ' + endDate.format(FORMAT_LONG_DATE_WITH_TIME)
                    }
                }
            },

            monthDisplay: function() {
                return moment(this.event.dates.start).format('MMM')
            },

            dateDisplay: function() {
                return moment(this.event.dates.start).format('DD')
            },

            byLine: function() {
                if (this.event.author.id && this.event.author.fullName) {
                    return 'By ' + '<a href="/search/user/' + this.event.author.id + '">' + this.event.author.fullName + '</a>'
                } else if (!this.event.author.id && this.data.author.fullName) {
                    return 'By ' + this.event.author.fullName
                }

                return ''
            },

            audiencesMeta: function() {
                let result = [],
                    campusCount = this.event.audiences.campus.length,
                    rolesCount  = this.event.audiences.roles.length;

                if (campusCount) {
                    let suffix = (campusCount > 1) ? ' Campuses' : ' Campus';
                    result.push(campusCount + suffix);
                }

                if (rolesCount) {
                    let suffix = (rolesCount > 1) ? ' Roles' : ' Role';
                    result.push(rolesCount + suffix);
                }

                return result.join(' & ');
            }
        }
    }
</script>

<style scoped>
    h1 {
        margin-top: 1rem;
    }

    time.icon
    {
        font-size: 1rem;
        display: block;
        position: relative;
        min-width: 4rem;
        min-height: 4rem;
        background-color: #fff;
        border-radius: 0.1em;
        box-shadow: 0 1px 0 #bdbdbd, 0 2px 0 #fff, 0 3px 0 #bdbdbd, 0 4px 0 #fff, 0 5px 0 #bdbdbd, 0 0 0 1px #bdbdbd;
        overflow: hidden;
    }

    time.icon *
    {
        display: block;
        width: 100%;
        font-size: 1rem;
        font-weight: bold;
        font-style: normal;
        text-align: center;
    }

    time.icon strong
    {
        position: absolute;
        top: 0;
        color: #fff;
        text-transform: uppercase;
    }

    time.icon span
    {
        font-size: 2.3rem;
        letter-spacing: -0.05rem;
        padding-top: 1.1rem;
        color: #2f2f2f;
    }

    time {
        margin-right: .5rem;
    }

    .event-type {
        text-align: right;
    }

    .event-type span {
        color: #fff;
        padding: .5rem;
        font-size: .8rem;
        font-weight: bold;
        text-transform: uppercase;
        border-radius: .5rem;
    }
</style>
