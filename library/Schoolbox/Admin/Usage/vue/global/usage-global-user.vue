<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import { humanFileSize } from "@js/util/fileUtils";
import footerUI from "@schoolboxVue/components/vueTables2/mixinFooterUI";

export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "user",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [
        footerUI, //to place and keep the tables UI in the footer
    ],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: {
        endpoint: {
            type: String,
            required: true,
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
            /* True, when data is currently loading; used to show skeleton
             */
            loading: true,

            /* All the data for this visualisation
             */
            allData: [],

            /* Table columns
             */
            columns: ["firstname", "lastname", "role", "size"],

            /* v-tables-3 options, see https://matanya.gitbook.io/vue-tables-2/options-api
             */
            options: {
                headings: {
                    firstname: "Firstname",
                    lastname: "Lastname",
                    role: "Role",
                    size: "Filesize",
                },
                orderBy: {
                    ascending: true,
                    column: "lastname",
                },
                perPage: 50,
                perPageValues: [50, 100, 200],
                debounce: 100, //faster debouce as data is stored on the client side,
                resizableColumns: false,
                tabbable: false,
                texts: {
                    filterPlaceholder: "Search for users...",
                    noResults: "No matching users...",
                    count: "{from}-{to} of {count}",
                    limit: "Rows per page:",
                },
                sortIcon: {
                    is: "glyphicon-sort",
                    base: "glyphicon",
                    up: "glyphicon-chevron-up",
                    down: "glyphicon-chevron-down",
                },
                customSorting: {
                    size: (ascending) => {
                        //ensure filesize is sorted numerically
                        return (a, b) => {
                            if (ascending) {
                                return parseInt(a.size) >= parseInt(b.size) ? 1 : -1;
                            }
                            return parseInt(a.size) <= parseInt(b.size) ? 1 : -1;
                        };
                    },
                },
            },
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted() {
        this.loadRows();
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
        lastGen() {
            return dayjs.unix(this.allData.time).fromNow();
        },

        /**
         * Fetch payload asynchronously
         *
         */
        async loadRows() {
            this.loading = true;
            try {
                const response = await fetch(this.endpoint);
                this.allData = await response.json();
            } catch (error) {
                console.warn(error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * To update the table to show the UI in the footer
         *
         */
        onLoaded() {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * To convert the bytes into a more readable string
         *
         * @param { Number } - 214430838
         *
         * @returns { String } - 204.5 MB
         */
        getHumanReadableFilesize(value) {
            return humanFileSize(value);
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
            <v-client-table :data="allData.data" :columns="columns" :options="options" @loaded="onLoaded">
                <template #firstname="props">
                    <a :href="`/adminv2/usage/specific/${props.row.userId}`" :title="props.row.fullname">{{
                        props.row.firstname
                    }}</a>
                </template>
                <template #lastname="props">
                    <a :href="`/adminv2/usage/specific/${props.row.userId}`" :title="props.row.fullname">{{
                        props.row.lastname
                    }}</a>
                </template>
                <template #size="props">
                    {{ getHumanReadableFilesize(props.row.size) }}
                </template>
                <template #afterTable>
                    <div class="VueTables__table-footer" ref="tableFooter"></div>
                    <div class="meta">^ Last Generated: {{ lastGen() }}</div>
                </template>
            </v-client-table>
        </template>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";
</style>
