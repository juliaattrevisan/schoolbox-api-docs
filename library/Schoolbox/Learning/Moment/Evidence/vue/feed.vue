<script>

import axios  from 'axios';
import moment from 'moment';

import MediaArticle from '@schoolbox/media-article';

const URL_LEARNING_EVIDENCE_SOURCE = '/learning/evidence/user/';

export default {
    components: {
        MediaArticle,
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
        config: () => window.learningEvidenceFeedConfig,
        dateDisplayFormats: () => window.dateDisplayFormats,
        groupedEvidence() {
            return this.groupEvidence(this.data);
        },
        currentCount () {
            return data.length;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        /**
         * Load learning evidence for the student in question.
         *
         * Once received, group them by date.
         */
        load() {
            axios.get(URL_LEARNING_EVIDENCE_SOURCE + String(this.config.student.id))
                .then(response => {
                    this.data            = response?.data?.data             ? response.data.data            : [];
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                })
                .finally(() => this.showSkeletonLoader = false);
        },

        /**
         * Groups learning evidence by the date which it was posted.
         *
         * @param {Array} evidence
         * @return Array
         */
        groupEvidence(evidence) {
            let groupedEvidenceByDate = {};
            evidence.forEach(v => {
                let createdAt = moment(v.createdAt),
                    updatedAt = moment(v.updatedAt),
                    key       = createdAt.format('YYYYMMDD');
                if (!(key in groupedEvidenceByDate)) {
                    groupedEvidenceByDate[key] = {
                        date:     moment(createdAt).startOf('d'),
                        evidence: []
                    }
                }
                v.createdAt = createdAt;
                v.updatedAt = updatedAt;
                groupedEvidenceByDate[key].evidence.push(v);
            });

            // Flatten grouped evidence into an array
            let groupedEvidence = Object.values(groupedEvidenceByDate).sort(
                (a, b) => a.date.isBefore(b.date) ? 1 : -1
            );

            // Ensure that each evidence within the group is sorted
            groupedEvidence.forEach(v => {
                v.evidence.sort(
                    (a, b) => a.createdAt.isBefore(b.createdAt) ? 1 : -1
                )
            });

            return groupedEvidence;
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
        <div v-for="evidenceGroup in groupedEvidence">
            <h2 class="subheader">
                <time :datetime="evidenceGroup.date.format('YYYY-MM-DD')"
                      :title="evidenceGroup.date.format(dateDisplayFormats.momentJsDateLong)"
                >{{ renderCreatedAt(evidenceGroup.date) }}</time>
            </h2>

            <section>
                <ul class="information-list">
                    <li v-for="evidence in evidenceGroup.evidence">
                        <div class="card small-12 compact">
                            <a :href="evidence.creator._links.profile" title="View Profile">
                                <img :src="evidence.creator._links.avatar + '&amp;size=square64'" alt="View Profile">
                                <p>{{ evidence.creator.fullName }}</p>
                                <time class="pipe meta"
                                      :datetime="evidence.createdAt.format()"
                                      :title="evidence.createdAt.format(dateDisplayFormats.momentJsDatetime)"
                                >{{ evidence.createdAt.format(dateDisplayFormats.momentJsTime) }}</time>
                            </a>
                        </div>

                        <media-article v-if="evidence.body">
                                <div v-html="evidence.body" />
                        </media-article>
                    </li>
                </ul>
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
                {{ config.student.firstname }} has no learning moments yet.
            </template>
            <template v-else>
                {{ config.student.firstname }} has {{ metadata.count }} learning moments.
            </template>
        </section>
    </div>

</template>
