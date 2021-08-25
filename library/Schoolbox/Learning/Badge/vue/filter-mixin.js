import {stringify} from "qs";
import axios from "axios";
const EVENT_CHANGE = 'change';
export default {
    computed: {
    },
    methods: {
        /**
         * Load badges.
         *
         * @param {Boolean} showToast Should the toast be activated on load of
         *                            badges?
         *                            The filter calls `onFilterChange` when its
         *                            selections change, and that has its own
         *                            toast so don't show another.
         */
        load(showToast) {
            this.data = [];
            this.append(null, showToast);
        },

        /**
         * Appends badges to the data list.
         *
         * @param {String|null} cursor
         * @param {Boolean}     showToast
         */
        append(cursor, showToast) {
            // TODO this is very generic, a candidate to be factored into its own place
            let params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            if (this.selectedFilters) {
                params.filters = this.selectedFilters;
            }
            if (showToast) {
                this.$refs.toast.activate('Loading...');
            }
            let config = {
                params:           params,
                paramsSerializer: params => stringify(params, {arrayFormat: 'brackets'}),
            }
            axios
                .get(this.filterEndpoint, config)
                .then(response => {
                    this.data            = this.data.concat(response?.data?.data ? response.data.data : [])
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                    if (showToast) {
                        this.$refs.toast.timeout('Loaded', true);
                    }
                })
                .catch(() => {
                    if (showToast) {
                        this.$refs.toast.timeout('Loading failed', false);
                    }
                })
                .finally(() => this.showSkeletonLoader = false);
        },
        /**
         * Helper for emitting the change event: this happens when selecting a
         * badge, or reloading the filter.
         */
        emitChange() {
            this.$emit(EVENT_CHANGE);
        },
    },
}
