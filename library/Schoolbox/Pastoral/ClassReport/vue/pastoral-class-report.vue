<script>

import Toast from '@schoolboxVue/components/toast';
import mixinFilter from '@schoolboxVue/mixins/filter';
import {stringify} from 'qs';
import {reactive} from "vue";

const SORT_OPTION_CREATED  = 'created';
const SORT_OPTION_UPDATED  = 'updated';
const SORT_OPTION_SEVERITY = 'severity';
const SORT_OPTION_DEFAULT  = SORT_OPTION_CREATED;
const SORT_OPTIONS         = [
    {value: SORT_OPTION_CREATED, label: 'Created'},
    {value: SORT_OPTION_UPDATED, label: 'Updated'},
    {value: SORT_OPTION_SEVERITY, label: 'Severity'},
];

const COUNT_OPTION_DEFAULT = 7;
const COUNT_OPTIONS        = [
    {value: 3, label: '3'},
    {value: 7, label: '7'},
    {value: 12, label: '12'},
];

export default {
    name: "pastoralClassReport",
    mixins: [
        mixinFilter
    ],
    components: {
        Toast
    },
    props: {
        // The route via which the report data is retrieved
        getReportDataRoute: {
            type: String,
            required: true,
        },
        // Is pastoral severity enabled?
        severityEnabled: {
            type: Boolean,
            default: true,
        },
    },
    data () {
        return {
            data: [],
            metadata: {
                count: null,
                cursor: null,
            },
            sort: SORT_OPTION_DEFAULT,
            count: COUNT_OPTION_DEFAULT,
            showSkeletonLoader: true,
            selectedFilters: null,
        }
    },
    created () {
        // assign endpoint for the mixin filter
        this.filter_endpoint = this.getReportDataRoute;
        this.countOptions    = COUNT_OPTIONS;
        this.sortOptions     = this.severityEnabled
            ? SORT_OPTIONS
            : SORT_OPTIONS.filter(v => v.value !== SORT_OPTION_SEVERITY);
    },
    mounted () {
        document.addEventListener('pastoral-class-report__onFilterChange', this.onFilterChange);
    },
    methods: {
        /**
         * Override mixin to set:
         * * sort type
         * * count
         */
        filter_build_params () {
            return {
                sort: this.sort,
                count: this.count,
            }
        },

        /**
         * Callback for when the filters for this report are updated.
         *
         * Fired from outer space currently.
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

        /**
         * Load more records for the student at this index.
         *
         * @param {Integer} studentIndex
         */
        async loadMore (studentIndex) {
            // Find the endpoint which fetches data for students,
            // and build a set of QS params to append to the endpoint
            let endpoint = new URL(this.data[studentIndex].records.metadata._links.report, window.location.origin),
                params   = this.filter_build_params();

            // Add filters and cursor value, and append to endpoint
            params.filters  = this.selectedFilters;
            params.cursor   = this.data[studentIndex].records.metadata.cursor.next;
            endpoint.search = stringify(params, { arrayFormat: 'brackets' });

            // Fetch data from endpoint,
            // update this student's report data and metadata
            this.$refs.toast.activate('Loading...');
            const response = await fetch(endpoint);
            if (response.ok) {
                const jsonResponse = await response.json();
                this.data[studentIndex].records.data     = this.data[studentIndex].records.data.concat(jsonResponse.data);
                this.data[studentIndex].records.metadata = reactive(jsonResponse.metadata);
                this.$refs.toast.timeout('Loaded', true);
            } else {
                this.$refs.toast.timeout('Loading failed', false);
            }
        },
    }
}
</script>

<template>
    <section class="content">
        <div v-if="showSkeletonLoader" class="loader">
        </div>

        <div v-else-if="data.length === 0" class="empty-state">
            <i class="icon-class-list"></i>
            <p>This class contains no students.</p>
        </div>
        <div v-else>
            <div class="row">
                <!-- sort options -->
                <div class="small-6 no-pad columns">
                    <div class="sort-filter">
                        <label for="sortOption">
                            Sort by:
                        </label>
                        <select id="sortOption" v-model="sort" @change="filter_append(null, true, true)">
                            <option v-for="option in sortOptions" :label="option.label" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>

                <!-- count filter -->
                <div class="small-6 no-pad columns">
                    <div class="count-filter">
                        <label>Show amount:</label>
                        <template v-for="option in countOptions">
                            <input :id="`countOption-${ option.value }`"
                                   type="radio"
                                   v-model="count"
                                   :value="option.value"
                                   @change="filter_append(null, true, true)"
                            >
                            <label :for="`countOption-${ option.value }`" class="input-toggle">{{ option.label }}</label>
                        </template>
                    </div>
                </div>
            </div>

            <ul class="action-list threaded content-container">
                <template v-for="(studentData, studentIndex) in data" :key="'student-' + String(studentData.student.id)">
                    <li class="list-item">
                        <!-- student profile -->
                        <div class="student-container">
                            <div class="student-card card small-12">
                                <div class="image-container">
                                    <img :src="studentData.student._links.avatar" :alt="`Profile photo of ${studentData.student.fullName}`" class="portrait"/>
                                </div>

                                <div class="text-container">
                                    <h4><a :href="studentData.student._links.profile" title="View profile" class="name">
                                    {{ studentData.student.lastName }}, {{ studentData.student.firstName }}
                                    </a></h4>
                                    <p v-if="studentData.student.role" class="meta">{{ studentData.student.role }}</p>

                                    <div v-for="record in studentData.records.data" :key="'record-' + String(record.id)" class="pastoral-container">
                                        <div class="tiny card small-12">
                                            <a :href="record._links.read" title="View record">
                                                <img :src="record.createdBy._links.avatar"
                                                     :alt="`Profile photo of ${record.createdBy.fullName}`"
                                                     :title="`Created by ${ record.createdBy.fullName }`"
                                                     class="portrait" />
                                                {{ record.heading }}
                                            </a>
                                            <div class="time-container">
                                                <template v-if="record.createdAt !== record.updatedAt">
                                                    <span :title="`Updated on ${formatDateTime(record.updatedAt)}`">⭐</span>
                                                </template>
                                                <time :datetime="formatDateTime(record.createdAt)" :title="formatDateTime(record.createdAt)">
                                                {{ formatDate(record.createdAt) }}
                                                </time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- pastoral records -->
                        </div>
                    </li>

                    <li v-if="studentData.records.metadata.count > studentData.records.data.length">
                        <div class="component-action">
                            <section>
                                <button @click.prevent="loadMore(studentIndex)">Show more</button>
                            </section>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <toast ref="toast"></toast>
    </section>

</template>

<style lang="scss" scoped>


.island .content {
    padding: 2rem;
    margin-bottom: 0;
}

.sort-filter {
    align-items: center;
    display: flex;
    flex-direction: row;
    padding-left: 1rem;

    label {
        width: 70px;
        line-height: 2rem;
    }

    select {
        border-radius: 2px;
        margin: 0;
        width: 30%;
    }
}

.count-filter {
    align-items: center;
    display: flex;
    justify-content: flex-end;
    padding-right: 1rem;

    label {
        margin-right: 1rem;
    }
}

input[type=radio]:not(.plain) + label.input-toggle {
    border: 1px solid var(--form-border-color);
    margin-left: 0.5rem;
}

.component-action {
    section {
        border: none;
    }

    button {
        padding: 0 1rem;
    }

    button:hover {
        background-color: var(--content-ui-foreground);
        color: white;
        transition: all .2s cubic-bezier(.5,1,.89,1);
    }
}

.content-container {
    border: 1px solid var(--form-border-color);
    margin: auto;
    margin-top: 1rem;

    .list-item {
        .student-container {
            width: 100%;

            .student-card {
                display: flex;
                flex-wrap: wrap;
                flex-direction: row;
                align-items: start;
                padding: 0;
                float: none;

                .image-container  {
                    margin-left: 0;
                    margin-right: 0;
                    padding: 0.75rem;

                    img {
                        border-radius: 50%;
                        transform: scale(0.85);
                        float: none;
                        margin: 0;

                    }
                }

                h4 {
                    overflow: visible;
                    padding: 0.75rem;
                    margin-top: 0.25rem;
                    padding-left: 0;
                }

                .pastoral-container {
                    height: 2.5rem;
                    width: 100%;
                    margin-left: 0.25rem;

                    .tiny {
                        display: grid;
                        grid-template-columns: 6fr 2fr;

                        a {
                            line-height: 1.5rem;

                            img {
                                border-radius: 50%;
                                margin-right: 0.75rem;
                            }
                        }

                        p {
                            text-overflow: inherit;
                            overflow: visible;
                            white-space: inherit;
                        }

                        .time-container {
                            padding-right: 1rem;
                            text-align: right;
                        }
                    }
                }

                .text-container {
                    flex: 1 1 0px;
                    min-height: 5.5rem;
                    padding-left: 0.25rem;
                    width: 0;
                }
            }
        }
    }
}

</style>
