<script>
import BadgeImage from "../../vue/badge-image";
import AwardDetailModal from './award-detail-modal';
import filterMixin from "../../vue/filter-mixin";

export default {
    name: 'award-list',
    props: {
        subjectTitle: {
            type: String,
            required: true,
        },
        getStudentListsEndpoint: {
            type: String,
            required: true,
        },
    },
    components: {
        BadgeImage,
        AwardDetailModal
    },
    mixins: [
        filterMixin
    ],
    data() {
        return {
            currentBadge: null,
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },
    computed: {
        filterEndpoint() {
            return this.getStudentListsEndpoint;
        },
    },
    methods: {
        openDetailModal(award) {
            this.$refs.awardDetailModal.open(award);
        },
        getCss(award) {
            if (award.isGoal) {
                return 'goal';
            }
            return '';
        },
        onFilterChange(selectedFilters) {
            this.selectedFilters    = selectedFilters;
            this.showSkeletonLoader = true;
            this.load(false);
            this.emitChange();
        },
    }
}
</script>
<template>
    <div>
        <div class="small-12 island">
            <section class="awards">
                <ul class="action-list">
                    <template>
                        <template v-for="(student, index) in data">
                            <li class="action-small-1 feed-item">
                                <div class="card wrap-down">
                                    <a :href="'/search/user/' + student.id">
                                        <img :src="'/portrait.php?id=' + student.id + '&size=square64'"
                                             :alt="student.fullName">
                                        {{ student.fullName }}
                                    </a>
                                </div>
                                <template v-for="(award, index) in student.awards">
                                    <div class="left badge-card" style="width:100px;height:120px;overflow:hidden;text-align:center">
                                        <a href="#" @click.prevent="openDetailModal(award)" :title="award.badge.name">
                                            <badge-image :badge="award.badge" :css="getCss(award)"></badge-image>
                                            {{ award.badge.name }}
                                        </a>
                                    </div>
                                </template>
                            </li>
                        </template>
                    </template>
                </ul>
            </section>
            <award-detail-modal ref="awardDetailModal"></award-detail-modal>
        </div>
    </div>
</template>
