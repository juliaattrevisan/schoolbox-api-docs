<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import { ENDPOINTS } from "@schoolboxCommsNews/Statistics/templates/visitation/endpoints.js";
import footerUI from "@schoolboxVue/components/vueTables2/mixinFooterUI";

export default {
    mixins: [
        footerUI, //to place and keep the tables UI in the footer
    ],

    props: {
        payload: {
            type: Object,
            required: true,
            default: () => {
                return {
                    article: {
                        id: 0,
                    },
                };
            },
        },
    },

    data() {
        return {
            isLoading: true, //used to show skeleton
            columns: ["fullname", "role", "occursAt"],
            options: {
                headings: {
                    fullname: "Name",
                    role: "Role",
                    occursAt: "Last Visited",
                },
                orderBy: {
                    ascending: false,
                    column: "occursAt", //initial sorting display
                },
                perPage: 50,
                perPageValues: [50, 100, 200],
                debounce: 100, //faster debouce as data is stored on the client side,
                resizableColumns: false,
                tabbable: false,
                texts: {
                    filterPlaceholder: "Search for visitors...",
                    noResults: "No matching visitors...",
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
                    fullname: (ascending) => {
                        //sort fullname by last name
                        return (a, b) => {
                            if (ascending) {
                                return a.lastname >= b.lastname ? 1 : -1;
                            }
                            return a.lastname <= b.lastname ? 1 : -1;
                        };
                    },

                    /* Sort by the visitation's occurrence date, using the unix timestamp
                     * in the sorting algorithm instead of the human readable version
                     */
                    occursAt: (ascending) => {
                        return (a, b) => {
                            if (ascending) {
                                return a.occursAt.absolute >= b.occursAt.absolute ? 1 : -1;
                            }
                            return a.occursAt.absolute <= b.occursAt.absolute ? 1 : -1;
                        };
                    },
                },
            },
            rows: [], //Will hold the row data once it's loaded
        };
    },

    mounted() {
        this.loadRows();
    },

    methods: {
        /**
         * Fetch payload asynchronously
         *
         */
        async loadRows() {
            try {
                const response = await fetch(ENDPOINTS.payloadFullPath(this.payload.article.id));
                this.rows = await response.json();
                this.isLoading = false;
            } catch (error) {
                this.isLoading = false;
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
         * format the ISO date for better readability
         *
         * @returns {String} - eg: Mar 19, 2021 08:52am (4 minutes ago)
         */
        formatLastVisited(value) {
            if (value.nice.length) {
                return value.nice + " (" + value.relative + ")";
            }

            return "";
        },
    },
};
</script>

<template>
    <div>
        <div class="row">
            <div class="small-12 island">
                <template v-if="this.isLoading">
                    <section class="content">
                        <div class="loader"></div>
                    </section>
                </template>
                <template v-else-if="!this.isLoading && rows.length === 0">
                    <div class="empty-state">
                        <i class="icon-staff"></i>
                        <p>Nobody has visited yet.</p>
                    </div>
                </template>
                <template v-else>
                    <section>
                        <v-client-table :data="rows" :columns="columns" :options="options" @loaded="onLoaded">
                            <template #fullname="props">
                                <a :href="`/search/user/${props.row.id}`" :title="props.row.fullname">{{
                                    props.row.fullname
                                }}</a>
                            </template>
                            <template #occursAt="props">
                                <span v-if="props.row.occursAt">{{ formatLastVisited(props.row.occursAt) }}</span>
                            </template>
                            <template #afterTable>
                                <div class="VueTables__table-footer" ref="tableFooter"></div>
                            </template>
                        </v-client-table>
                    </section>
                </template>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";
</style>
