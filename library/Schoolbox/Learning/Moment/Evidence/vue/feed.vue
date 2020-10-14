<script>

import axios  from 'axios';
import moment from 'moment';

const URL_LEARNING_MOMENT_SOURCE = '/learning/evidence/user/';

export default {
    components: {
    },
    data() {
        return {
            data:     [],
            metadata: {
                count:  null,
                cursor: null,
            },
            showSkeletonLoader: true,
        }
    },
    computed: {
        config: () => window.learningMomentsFeedConfig,
        groupedMoments() {
            return this.groupMoments(this.data);
        }
    },
    mounted() {
        this.load();
    },
    methods: {
        /**
         * Load the list of learning moments.
         *
         * Once received, group them by date.
         */
        load() {
            axios.get(URL_LEARNING_MOMENT_SOURCE + String(this.config.studentId))
                .then(response => {
                    this.data            = response?.data?.data             ? response.data.data            : [];
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                })
                .finally(() => this.showSkeletonLoader = false);
        },

        /**
         * Groups the learning moments by the date which they were posted.
         *
         * @param {Array} moments
         * @return Array
         */
        groupMoments(moments) {
            let groupedMomentsByDate = {};
            moments.forEach(v => {
                let createdAt = moment(v.createdAt),
                        updatedAt = moment(v.updatedAt),
                        key       = createdAt.format('YYYYMMDD');
                if (!(key in groupedMomentsByDate)) {
                    groupedMomentsByDate[key] = {
                        date:    moment(createdAt).startOf('d'),
                        moments: []
                    }
                }
                v.createdAt = createdAt;
                v.updatedAt = updatedAt;
                groupedMomentsByDate[key].moments.push(v);
            });

            // Flatten grouped moments into an array
            let groupedMoments = Object.values(groupedMomentsByDate).sort(
                (a, b) => a.date.isBefore(b.date) ? 1 : -1
            );

            // Ensure that each moment within the group is sorted
            groupedMoments.forEach(v => {
                v.moments.sort(
                    (a, b) => a.createdAt.isBefore(b.createdAt) ? 1 : -1
                )
            });

            return groupedMoments;
        },

        /**
         * Renders the created-at time as a string.
         * *
         *
         * @param {Moment} createdAt
         */
        renderCreatedAt(createdAt) {
            let now     = moment().startOf('d'),
                dayDiff = createdAt.diff(now, 'd');
            if (dayDiff === 0) {
                return 'today';
            }
            if (dayDiff === -1) {
                return 'yesterday';
            }
            if (dayDiff === 1) {
                return 'tomorrow';
            }
            return moment.duration(dayDiff, 'd').humanize(true);
        },
    }
}
</script>

<template>
    <div>
        <div v-for="momentGroup in groupedMoments">
            <h2 class="subheader">{{ renderCreatedAt(momentGroup.date) }}</h2>

            <section>
                <div v-for="moment in momentGroup.moments" class="list-item">
                    <div class="card small-12">
                        <a href="#" title="View Profile">
                            <img :src="'/portrait.php?id=' + Number(moment.creator.id) + '&amp;size=square64'" alt="View Profile">
                        </a>
                        | {{ moment.createdAt.format('h:mma') }}
                    </div>
                </div>

            </section>
        </div>

        <section v-if="showSkeletonLoader">
            <div v-for="n in 3" class="list-item">
                <div class="small-12 card skeleton">
                    <div class="img"></div>
                    <h3></h3>
                    <p class="meta"></p>
                </div>
            </div>
        </section>
        <section v-else>
            <template v-if="metadata.count === 0">
                No learning moments yet.
            </template>
            <template v-else>
                <!-- TODO get the student's name in -->
                Student has {{ metadata.count }} learning moments.
            </template>
        </section>
    </div>

</template>
