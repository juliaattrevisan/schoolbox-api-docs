<script>

import mixinFilter from '@schoolboxVue/mixins/filter';

const SORT_OPTION_CREATED = 'created';
const SORT_OPTION_UPDATED = 'updated';
const SORT_OPTION_SEVERITY = 'severity';
const SORT_OPTIONS = [
    {value: SORT_OPTION_CREATED, label: 'Created'},
    {value: SORT_OPTION_UPDATED, label: 'Updated'},
    {value: SORT_OPTION_SEVERITY, label: 'Severity'},
];
const COUNT_OPTION_DEFAULT = 7;
const COUNT_OPTIONS = [
    {value: 3, label: '3'},
    {value: 7, label: '7'},
    {value: 12, label: '12'},
];

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
            sort: SORT_OPTION_CREATED,
            count: COUNT_OPTION_DEFAULT,
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },
    created () {
        // assign endpoint for the mixin filter
        this.filter_endpoint = this.getReportDataRoute;
        this.sortOptions     = SORT_OPTIONS;
        this.countOptions    = COUNT_OPTIONS;
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

        /**
         * Format a date for output.
         *
         * @param {String} date
         * @returns {String}
         */
        formatDate (date) {
            return dayjs(date).format(dateDisplayFormats.dayjs);
        },

        /**
         * Format a date+time for output.
         *
         * @param {String} date
         * @returns {String}
         */
        formatDateTime (date) {
            return dayjs(date).format(dateDisplayFormats.dayjsDatetime);
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
                <!-- sort options -->
                <label for="sortOption">
                    Sort by:
                </label>
                <select id="sortOption" v-model="sort">
                    <option v-for="option in sortOptions"
                            :label="option.label"
                            :value="option.value">{{ option.label }}</option>
                </select>

                <!-- count filter -->
                <label>Show amount:</label>
                <template v-for="option in countOptions">
                    <input :id="`countOption-${ option.value }`" type="radio" v-model="count" :value="option.value">
                    <label :for="`countOption-${ option.value }`" class="input-toggle">{{ option.label }}</label>
                </template>

                <ul class="action-list">
                    <li v-for="studentData in data" :key="'student-' + String(studentData.student.id)">
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
                                <li v-for="record in studentData.records" :key="'record-' + String(record.id)">
                                    <a :href="record._links.read">{{ record.heading }}</a>,
                                    created
                                    <time :datetime="formatDateTime(record.createdAt)"
                                          :title="formatDateTime(record.createdAt)"
                                    >
                                        {{ formatDate(record.createdAt) }}
                                    </time>
                                    <template v-if="record.createdAt !== record.updatedAt">
                                        updated
                                        <time :datetime="formatDateTime(record.updatedAt)"
                                              :title="formatDateTime(record.updatedAt)"
                                        >
                                            {{ formatDate(record.updatedAt) }}
                                        </time>
                                    </template>
                                </li>
                            </ul>
                        </div>

                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
