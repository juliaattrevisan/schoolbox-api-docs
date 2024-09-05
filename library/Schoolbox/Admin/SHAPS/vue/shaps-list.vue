<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Schoolbox Help
 */

import ShapsListFilters from "./shaps-list-filters.vue";
import ShapsListNewValues from "./shaps-list-new-values.vue";
import FilterEvents from "./shapsListFilterEvents";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer";
import footerUI from "@schoolboxVue/components/vueTables2/mixinFooterUI";

export default {
    components: {
        ShapsListFilters,
        ShapsListNewValues,
        BottomDrawer,
    },

    mixins: [
        footerUI, //to place and keep the tables UI in the footer
    ],

    props: {
        srcUserList: {
            type: String,
            required: true,
        },
        srcUserModify: {
            type: String,
            required: true,
        },
        campuses: {
            type: Array,
            required: true,
        },
        roles: {
            type: Array,
            required: true,
        },
        courses: {
            type: Array,
            required: true,
        },
        provisioned: {
            type: Boolean,
        },
        deleted: {
            type: Boolean,
        },
    },

    data() {
        return {
            hasDataLoaded: false, //show loading display until true
            hasLoadError: false, //display load error
            rows: [],
            selectedRows: [], //keep track of which rows could be modified
            tableColumns: ["firstname", "lastname", "email", "campuses", "role", "courses", "provisioned"],
            tableOptions: {
                customFilters: [
                    //refer to https://matanya.gitbook.io/vue-tables-2/custom-filters for further info
                    {
                        name: FilterEvents.CAMPUS,
                        callback: (row, query) => {
                            return row.campuses.join().includes(query); //need to flatten the campuses array then check
                        },
                    },
                    {
                        name: FilterEvents.ROLE,
                        callback: (row, query) => {
                            return row.role === query;
                        },
                    },
                    {
                        name: FilterEvents.COURSE,
                        callback: (row, query) => {
                            if (query === "null") {
                                return row.courses.length === 0;
                            }
                            return row.courses.join().includes(query);
                        },
                    },
                    {
                        name: FilterEvents.PROVISIONED,
                        callback: (row, query) => {
                            return row.provisioned === parseInt(query);
                        },
                    },
                ],
                debounce: 100, //faster debounce as data is stored on the client side,
                headings: {
                    firstname: "First Name",
                    lastname: "Surname",
                    email: "Email",
                    campuses: "Campuses",
                    role: "Role",
                    courses: "Courses",
                    provisioned: "Provisioned",
                },
                orderBy: {
                    ascending: false,
                    column: "Surname", //TODO ??
                },
                perPage: 25,
                perPageValues: [25, 50, 100, 150, 200],
                resizableColumns: false,
                selectable: {
                    mode: "multiple", //to enable multi-select
                    only: (row) => {
                        return (
                            row.email != null && row.email.length > 0 && row.lastname != null && row.lastname.length > 0
                        );
                    },
                },
                sortIcon: {
                    is: "glyphicon-sort",
                    base: "glyphicon",
                    up: "glyphicon-chevron-up",
                    down: "glyphicon-chevron-down",
                },
                tabbable: false,
                templates: {
                    campuses: (h, row, index) => {
                        return row.campuses.join(", ");
                    },
                    provisioned: (h, row, index) => {
                        return row.provisioned == 1 ? "Provisioned" : "";
                    },
                },
                texts: {
                    filterPlaceholder: "Search for users...",
                    noResults: "No matching users...",
                    count: "{from}-{to} of {count}",
                    limit: "Rows per page:",
                },
            },
            singleRowName: "user",
            pluralRowName: "users",
            selectContainer: null,
            collator: new Intl.Collator("en", { numeric: true, sensitivity: "base" }), //to naturally sort year level column
            isShiftKeySelected: false, //to multi-select fields,
            hasSelectedShiftKey: false, //to hide a UI note
        };
    },

    computed: {
        /**
         * To track how many are selected
         *
         * @returns {Number}
         */
        selectedRowsCount() {
            return this.selectedRows.length;
        },

        /**
         * For proper count display, eg: '1 user' / '4 users'
         *
         * @returns {String}
         */
        selectedRowsCountWithRowName() {
            if (this.selectedRowsCount === 0) {
                return "No " + this.singleRowName;
            } else if (this.selectedRowsCount === 1) {
                return this.selectedRowsCount + " " + this.singleRowName;
            } else {
                return this.selectedRowsCount + " " + this.pluralRowName;
            }
        },
    },

    mounted() {
        this.initShiftKeyDetection();
        this.loadRows();
    },

    methods: {
        /**
         * To hide a UI note informing of shift-selection once pressed
         */
        initShiftKeyDetection() {
            document.addEventListener("keydown", (event) => {
                if (event.key === "Shift") {
                    this.isShiftKeySelected = true;
                }
            });
            document.addEventListener("keyup", (event) => {
                if (event.key === "Shift") {
                    this.isShiftKeySelected = false;
                }
            });
        },

        /**
         * Load users
         */
        async loadRows() {
            try {
                const response = await fetch(this.srcUserList, {
                    method: "POST",
                    body: JSON.stringify(this.getRowsBody()),
                });
                this.rows = await response.json();
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.hasDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * Pass in the params to return all rows
         *
         * @retrun { Object}
         */
        getRowsBody() {
            return {
                campusId: -1,
                roleId: -1,
                courses: -1,
                provisioned: -1,
            };
        },

        /**
         * To update the table to show the UI in the footer
         */
        onLoaded() {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * To keep track of selected rows
         */
        onRowSelected(event) {
            this.selectedRows = event;
            if (this.selectedRows.length > 1 && this.isShiftKeySelected) {
                //to hide a UI note, notifying of availability of shift-select
                this.hasSelectedShiftKey = true;
            }
        },

        /**
         * Clear the selection
         */
        clearRows() {
            this.selectedRows = [];
            this.$refs.table.resetSelectedRows();
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onClearUsersSelected() {
            this.clearRows();
        },

        /**
         * To open the modify modal
         */
        onModifySelected() {
            this.$refs.newValues.modify(); //will open up the reveal-modal in shaps-list-new-values
        },

        /**
         * Selected modify is complete, so clear the rows and update the data
         */
        onUpdated() {
            this.clearRows();
            this.loadRows(); //refresh the data
        },
    },
};
</script>

<template>
    <div>
        <div v-if="hasLoadError">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load. Please contact your support if the problem persists.
                </div>
            </section>
        </div>
        <v-client-table
            v-else-if="hasDataLoaded"
            ref="table"
            :data="rows"
            :columns="tableColumns"
            :options="tableOptions"
            @loaded="onLoaded"
            @select="onRowSelected"
        >
            <template #beforeTable>
                <shaps-list-filters :campuses="campuses" :roles="roles" :courses="courses" :provisioned="provisioned" />
            </template>
            <template #firstname="props">
                <div v-if="props.row.deleted">{{ props.row.firstname }} (deleted)</div>
                <div v-else>
                    <span
                        v-if="
                            props.row.email === null ||
                            props.row.email.length === 0 ||
                            props.row.lastname === null ||
                            props.row.lastname.length === 0
                        "
                        data-tooltip
                        class="hint has-tip tip-top"
                        title="Please make sure that email and surname are both set to provision this user."
                        >?</span
                    >
                    <a :href="'/search/user/' + props.row.id">
                        {{ props.row.firstname }}
                    </a>
                </div>
            </template>
            <template #courses="props">
                <div>
                    <ul>
                        <li v-for="course in props.row.courses">{{ course }}</li>
                    </ul>
                </div>
            </template>
            <template #afterTable>
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </template>
        </v-client-table>
        <div v-else>
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
        </div>
        <bottom-drawer :showDrawer="selectedRowsCount > 0">
            <h3 class="selected-count">
                <em>{{ selectedRowsCountWithRowName }} selected</em>
            </h3>
            <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
            <button @click.prevent="onClearUsersSelected" class="button-clear">Clear User Selection</button>
            <button @click.prevent="onModifySelected" class="button submit">Modify</button>
        </bottom-drawer>
        <shaps-list-new-values
            ref="newValues"
            :src-user-modify="srcUserModify"
            :courses="courses"
            :selected-rows="selectedRows"
            :selected-rows-count-with-row-name="selectedRowsCountWithRowName"
            @updated="onUpdated"
        />
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";

.VueTables--client {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.selected-count {
    margin-right: 1rem;
}

.button-clear,
.selected-count {
    display: inline-block;
}

.shift-select-note {
    color: #9a9a9a;
    margin-right: 1rem;
    font-size: 13px;
    font-style: italic;
}

.button-clear {
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
    &:hover {
        background: none;
    }
}
</style>
