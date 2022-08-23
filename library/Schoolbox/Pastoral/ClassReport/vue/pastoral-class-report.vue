<script>

import mixinFilter from '@schoolboxVue/mixins/filter';

export default {
    name: "pastoralClassReport",
    mixins: [
        mixinFilter
    ],
    props: {
        // The route via which the report data is retrieved
        getReportDataRoute: {
            type: String,
            default: '',
            required: true,
        },
    },
    data () {
        return {
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },
    created () {
        // assign endpoint for the mixin filter
        this.filter_endpoint = this.getReportDataRoute;
    },
    mounted () {
        document.addEventListener('pastoral-class-report__onFilterChange', this.onFilterChange);
    },
    methods: {
        /**
         * Callback for when the filters for this badge list are updated.
         *
         * Fired from outer space, currently from library/Schoolbox/Learning/Badge/templates/list.twig
         */
        onFilterChange (event) {
            this.changeFilters(event.detail.selectedFilters);
        },

        /**
         * Update the display with the selected filters
         *
         * @param {object} selectedFilters The filters that were selected
         */
        changeFilters (selectedFilters) {
            this.selectedFilters    = selectedFilters;
            this.showSkeletonLoader = true;
            this.filter_load(false);
            this.filter_emitChange();
        },
    }
}
</script>

<template>
    <div class="small-12 island badge-report">
        <section class="content">
            <div v-if="showSkeletonLoader" class="loader">
            </div>

            <div v-else-if="data.length === 0" class="empty-state">
                <i class="icon-class-list"></i>
                <p>This class contains no students.</p>
            </div>

            <div v-else>
                <ul class="action-list">
                    <li v-for="studentData in data" :key="studentData.student.id">
                        <!-- student profile -->
                        <div>
                            <img :src="studentData.student._links.avatar"
                                 :alt="`profile photo of ${studentData.student.fullName}`"
                                 class="portrait" />
                            <div>
                                <a :href="studentData.student._links.profile" title="View profile" class="name">
                                    {{ studentData.student.lastName }}, {{ studentData.student.firstName }}
                                </a>
                                <p v-if="studentData.student.role" class="meta">{{ studentData.student.role }}</p>
                            </div>
                        </div>

                        <!-- pastoral records -->
                        <div>
                            <ul class="information-list">
                                <li v-for="record in studentData.records">
                                    {{ record.heading }}
                                </li>
                            </ul>
                        </div>

                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
