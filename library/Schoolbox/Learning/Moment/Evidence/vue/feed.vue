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
            let groupedMoments = {};
            this.data.forEach(v => {
                let createdAt = moment(v.createdAt),
                    updatedAt = moment(v.updatedAt),
                    key       = createdAt.format('YYYYMMDD');
                if (!(key in groupedMoments)) {
                    groupedMoments[key] = {
                        date:    createdAt,
                        moments: []
                    }
                }
                v.createdAt = createdAt;
                v.updatedAt = updatedAt;
                groupedMoments[key].moments.push(v);
            });
            return Object.values(groupedMoments).sort(
                (a, b) => b.date.isBefore(a.date) ? 1 : -1
            );
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
    }
}
</script>

<template>
    <div>
        <div v-for="momentGroup in groupedMoments">
            <h2 class="subheader">{{ momentGroup.date.fromNow() }}</h2>

            <section>
                <div v-for="moment in momentGroup.moments" class="list-item">
                    <div class="card small-12">
                        <a href="#" title="View Profile">
                            <img :src="'/portrait.php?id=' + Number(moment.creator.id) + '&amp;size=square64'" alt="View Profile">
                        </a>
                        | {{ moment.createdAt.fromNow() }}
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
            Displaying {{ metadata.count }} learning moments.
        </section>
    </div>

</template>
