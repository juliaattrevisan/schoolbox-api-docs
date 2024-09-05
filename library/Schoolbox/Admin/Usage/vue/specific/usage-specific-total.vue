<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import { humanFileSize } from "@js/util/fileUtils";

export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "specific-total",

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
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: ["endpoint", "isS3Adapter"],

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
            /* True, when data is currently loading; used to show skeleton
             */
            loading: true,

            /* All the data for this visualisation
             */
            allData: [],
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {
        this.getData();
    },

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {},

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {
        /**
         * Display the chart once the data has been loaded
         */
        async getData() {
            this.loading = true;
            try {
                const response = await fetch(this.endpoint, {
                    body: JSON.stringify(this.gradeGroups),
                    headers: {
                        "Content-Type": "application/json",
                    },
                });
                this.allData = await response.json();
            } catch (error) {
                console.warn(error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Wrapper for this filesize library
         * @param {number} size - The size in bytes
         * @returns {string} - The size in human readable format
         */
        filesize(size) {
            return humanFileSize(size);
        },
    },
};
</script>
<template>
    <div>
        <template v-if="this.loading">
            <table class="skeleton">
                <thead>
                    <tr>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </template>
        <template v-else>
            <h2>Total Usage</h2>
            {{ filesize(allData.size) }}
        </template>
    </div>
</template>
