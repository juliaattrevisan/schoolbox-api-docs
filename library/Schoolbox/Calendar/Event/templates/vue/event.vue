<template>
    <div>
        <div class="small-12 medium-8 island">
            <section class="detail content">
                <div class="title-section">
                    <time datetime="2014-09-20" class="icon">
                        <strong :style="{'background-color': this.data.options.type.color}"> {{ monthDisplay }}</strong>
                        <span>{{ dateDisplay }}</span>
                    </time>

                    <h1>{{ this.data.title }}</h1>
                </div>
                <div class="meta">
                    <span v-html="byLine"></span>
                    <span v-if="audiencesMeta"> | {{ audiencesMeta }}</span>
                </div>
                <article v-html="data.detail"></article>
            </section>
        </div>

        <div class="small-12 medium-4 island">
            <section class="detail content">
                <a href="#" class="button icon-approve">RSVP</a>
                <a href="#" class="button icon-calendar">Add to Calendar</a>

                <h4>Date and Time</h4>
                <p class="event-datetime-display" v-html="dateTimeDisplay"></p>

                <h4>Location</h4>
                <p v-html="this.data.location"></p>
            </section>
        </div>
    </div>
</template>

<script>
    import moment from 'moment';

    const FORMAT_LONG_DATE = 'dddd, MMMM DD, YYYY'; // Monday, October 01, 2019
    const FORMAT_LONG_DATE_WITH_TIME = 'LLLL'; // Monday, October 01, 2019 02:30 PM
    const FORMAT_DATE_ONLY = 'MM-DD-YYYY'; // 10-01-2019
    const FORMAT_TIME_ONLY = 'LT'; // 02:30 PM

    export default {
        props: ['data'],
        name: "event-details",
        data() {
            return {
                event: this.data,
            }
        },
        mounted() {
            console.log( this.data.audiences.campus );
        },
        computed: {
            dateTimeDisplay: function() {
                if (this.data.options.allDay) {
                    return moment(this.data.dates.start).format(FORMAT_LONG_DATE)
                } else {
                    let startDate = moment(this.data.dates.start),
                        endDate = moment(this.data.dates.end);

                    if (startDate.format(FORMAT_DATE_ONLY) === endDate.format(FORMAT_DATE_ONLY)) {
                        return startDate.format(FORMAT_LONG_DATE) +
                                '<br />' + startDate.format(FORMAT_TIME_ONLY) + ' - ' + endDate.format(FORMAT_TIME_ONLY)
                    } else {
                        return startDate.format(FORMAT_LONG_DATE_WITH_TIME) + ' - ' + endDate.format(FORMAT_LONG_DATE_WITH_TIME)
                    }
                }
            },

            monthDisplay: function() {
                return moment(this.data.dates.start).format('MMM')
            },

            dateDisplay: function() {
                return moment(this.data.dates.start).format('DD')
            },

            byLine: function() {
                if (this.data.author.id && this.data.author.fullName) {
                    return 'By ' + '<a href="/search/user/' + this.data.author.id + '">' + this.data.author.fullName + '</a>'
                } else if (!this.data.author.id && this.data.author.fullName) {
                    return 'By ' + this.data.author.fullName
                }

                return ''
            },

            audiencesMeta: function() {
                let result = [],
                    campusCount = this.data.audiences.campus.length,
                    rolesCount  = this.data.audiences.roles.length;

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
        width: 4rem;
        height: 4rem;
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
</style>
