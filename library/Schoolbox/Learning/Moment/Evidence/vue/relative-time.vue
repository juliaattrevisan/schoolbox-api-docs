<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "relative-time",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hypen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        /**
         * The date-time to display in this relative time input.
         */
        datetime: {
            required: true,
        },

        /**
         * Is `datetime` to be considered a date only?
         *
         * If so, the absolute time is displayed as a date only.
         */
        dateOnly: {
            type: Boolean,
            default: false,
        },
    },

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {},

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            formattedDate: {
                absolute: "",
                relative: "",
            },
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        this.formattedDate = this.renderFormattedDatetimes(this.datetime);
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {
        dateDisplayFormats: () => window.dateDisplayFormats,
    },

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {
        /**
         * Renders the given datetime as a string.
         *
         * @param {*} datetime Any date-like thing MomentJS accepts in its
         *                     constructor.
         */
        renderFormattedDatetimes(datetime) {
            let now = dayjs().startOf("d"),
                date = dayjs(datetime),
                dayDiff = date.diff(now, "d"),
                dateFormat = this.dateOnly ? this.dateDisplayFormats.dayjs : this.dateDisplayFormats.dayjsDatetime;

            let struct = {
                absolute: date.format(dateFormat),
                relative: "",
            };

            /* We determine a relative time equivalence based on how different
             * $datetime was, compared to today. Anything outside the (-1, 1) range
             * gets a non-relative format
             */
            if (dayDiff === 0) {
                struct.relative = "today";
            } else if (dayDiff === -1) {
                struct.relative = "yesterday";
            } else if (dayDiff === 1) {
                struct.relative = "tomorrow";
            } else {
                struct.relative = dayjs.duration(dayDiff, "d").humanize(true);
            }

            return struct;
        },
    },
};
</script>
<template>
    <time :title="this.formattedDate.absolute">{{ this.formattedDate.relative }}</time>
</template>
