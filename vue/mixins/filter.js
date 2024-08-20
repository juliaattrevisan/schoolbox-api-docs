import { createQueryString } from "@js/util/queryUtils";

const ON_FILTER_CHANGE = "on-filter-change";

export default {
    data() {
        return {
            filter_endpoint: "", //the endpoint of the filter action
        };
    },
    methods: {
        /**
         * Load filter.
         *
         * @param {Boolean} showToast Should the toast be activated on load?
         *                            The filter calls `onFilterChange` when its
         *                            selections change, and that has its own
         *                            toast so don't show another.
         */
        filter_load(showToast) {
            this.data = [];
            this.filter_append(null, showToast, true);
        },

        /**
         * Build a list of parameters to send to the filter.
         *
         * By default empty: this may have other query string parameters if
         * required.
         *
         * @returns {Object}
         */
        filter_build_params() {
            return {};
        },

        /**
         * Appends data to the data list.
         *
         * @param {String|null} cursor
         * @param {Boolean}     showToast
         * @param {Boolean}     clear     Is the data cleared before appending?
         */
        filter_append(cursor, showToast, clear) {
            let params = this.filter_build_params();
            if (cursor) {
                params.cursor = cursor;
            }
            if (this.selectedFilters) {
                params.filters = this.selectedFilters;
            }
            if (showToast) {
                this.$refs.toast.activate("Loading...");
            }
            fetch(`${this.filter_endpoint}?${createQueryString(params)}`)
                .then((response) => response.json())
                .then((response) => {
                    if (clear) {
                        this.data = [];
                    }
                    this.data = this.data.concat(response?.data ? response.data : []);
                    this.metadata.count = response?.metadata?.count ? response.metadata.count : 0;
                    this.metadata.cursor = response?.metadata?.cursor ? response.metadata.cursor : null;
                    if (showToast) {
                        this.$refs.toast.timeout("Loaded", true);
                    }
                })
                .catch(() => {
                    if (showToast) {
                        this.$refs.toast.timeout("Loading failed", false);
                    }
                })
                .finally(() => (this.showSkeletonLoader = false));
        },

        /**
         * Helper for emitting the change event: this happens when reloading the filter
         */
        filter_emitChange() {
            this.$emit(ON_FILTER_CHANGE);
        },
    },
};
