<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To be able to bulk delete users from the system
 * A sortable/searchable table is displayed to select the users to delete
 *
 */

import Toast from "@schoolboxVue/components/toast";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer";
import footerUI from "@schoolboxVue/components/vueTables2/mixinFooterUI";

export default {
    components: {
        Toast,
        RevealModal,
        BottomDrawer,
    },

    mixins: [
        footerUI, //to place and keep the tables UI in the footer
    ],

    props: {
        dateFormat: {
            type: String,
            required: true,
            default: "DD/MM/YYYY",
        },
        src: {
            type: String,
            required: true,
        },
        action: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            hasRowsLoaded: false,
            hasLoadError: false,
            rows: [], //will be populated once loaded
            selectedRows: [], //keep track of which rows could be deleted
            columns: ["username", "external_id", "firstname", "lastname", "role", "year", "updated_at"],
            options: {
                headings: {
                    username: "Username",
                    external_id: "External ID",
                    firstname: "First Name",
                    lastname: "Surname",
                    role: "Role",
                    year: "Year Level",
                    updated_at: "Last Updated",
                },
                orderBy: {
                    ascending: false,
                    column: "updated_at", //initial sorting display
                },
                templates: {
                    updated_at: (h, row, index) => {
                        return dayjs(row.updated_at).format(this.dateFormat); //to format date properly
                    },
                },
                perPage: 50,
                perPageValues: [50, 100, 200],
                selectable: {
                    mode: "multiple", //to enable multi-select
                },
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
                    year: (ascending) => {
                        //sort year level naturally
                        return (a, b) => {
                            if (ascending) {
                                return this.collator.compare(a.year, b.year);
                            } else {
                                return this.collator.compare(b.year, a.year);
                            }
                        };
                    },
                },
            },
            singleRowName: "user",
            pluralRowName: "users",
            selectContainer: null,
            collator: new Intl.Collator("en", { numeric: true, sensitivity: "base" }), //to natually sort year level column
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
         * to hide a UI note informing of shift-selection once pressed once
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
         * load the stale users for display
         */
        async loadRows() {
            try {
                const response = await fetch(this.src);
                this.rows = await response.json();
                this.hasRowsLoaded = true;
            } catch (error) {
                this.hasLoadError = true; //display error note in the DOM
            }
        },

        /**
         * To update the table to show the UI in the footer
         */
        onLoaded() {
            this.$nextTick(() => this.footerUI_update());
        },

        /**
         * to keep track of selected rows
         */
        onRowSelected(event) {
            this.selectedRows = event;
            if (this.selectedRows.length > 1 && this.isShiftKeySelected) {
                //to hide a UI note, notifying of availability of shift-select
                this.hasSelectedShiftKey = true;
            }
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onDeleteUsersSelected() {
            this.$refs.deleteModal.open();
        },

        /**
         * Delete the selected users, informing of deletion and clearing them from the table list as well
         *
         */
        async onConfirmDeleteUsersSelected() {
            this.$refs.toast.activate("Deleting...");
            try {
                await fetch(this.action, {
                    method: "POST",
                    body: this.getFormData(),
                });
                this.$refs.toast.timeout("Deleted", true, 2000);
                this.$refs.deleteModal.close();
                this.rows = this.getUpdatedRowsAfterUserDeletion();
                this.clearRows();
                this.$refs.table.resetSelectedRows();
            } catch (error) {
                this.$refs.toast.timeout("Unable to delete", false, 2000);
            }
        },

        /**
         * Set up a deleted users array to POST
         *
         * @returns {FormData}
         */
        getFormData() {
            const usersToDeleteIDList = this.selectedRows.map((row) => row.id);
            const formData = new FormData();
            usersToDeleteIDList.forEach((item) => {
                formData.append("users[]", item);
            });
            return formData;
        },

        /**
         * To update the row display after deletion
         *
         * @returns {Array}
         */
        getUpdatedRowsAfterUserDeletion() {
            return this.rows.filter((row) => {
                if (this.selectedRows.some((rowToDelete) => rowToDelete.id === row.id)) {
                    //pull out any deleted rows, those rows that are in selectedRows, which have just been deleted
                    return false;
                }
                return true;
            });
        },

        /**
         * clear the selection after deletion
         */
        clearRows() {
            this.selectedRows = [];
        },

        /**
         * Cancel the the selected users deletion
         */
        onCancelDeleteUsersSelected() {
            this.$refs.deleteModal.close();
        },

        /**
         * To confirm/cancel selected users deletion
         */
        onClearUsersToSelected() {
            this.clearRows();
            this.$refs.table.resetSelectedRows();
        },
    },
};
</script>

<template>
    <div>
        <div v-if="!hasRowsLoaded && !hasLoadError" class="loader"></div>
        <div v-else-if="!hasRowsLoaded && hasLoadError" data-alert class="alert-box alert">
            Sorry, we are currently unable to load the Stale Users Report.
        </div>

        <v-client-table
            v-if="hasRowsLoaded"
            ref="table"
            :data="rows"
            :columns="columns"
            :options="options"
            @loaded="onLoaded"
            @select="onRowSelected"
        >
            <template #afterTable>
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </template>
        </v-client-table>

        <toast ref="toast"></toast>

        <reveal-modal ref="deleteModal">
            <h2>Please Confirm Multiple User Deletion</h2>
            <div data-alert class="alert-box alert">
                You are about to permanently delete <strong>{{ selectedRowsCountWithRowName }}</strong
                >. Deleting users will prevent them from logging into the system and from accessing resources that
                belong to them. Attached resources that will be removed include; ePortfolio, My Files, Pastoral Records
                and My Links. Resources shared with or by the user like Files, Grades, News, Forum Posts and other
                collaborative activities will remain available.
            </div>
            <ul class="flex-list buttons">
                <li>
                    <button @click.prevent="onConfirmDeleteUsersSelected" class="button submit">
                        Confirm Deletion
                    </button>
                </li>
                <li><button @click.prevent="onCancelDeleteUsersSelected" class="button">Cancel</button></li>
            </ul>
        </reveal-modal>

        <bottom-drawer :showDrawer="selectedRowsCount > 0">
            <h3 class="selected-count">
                <em>{{ selectedRowsCountWithRowName }} selected</em>
            </h3>
            <span v-if="!hasSelectedShiftKey" class="shift-select-note">Use Shift+Select to select multiple users</span>
            <button @click.prevent="onClearUsersToSelected" class="button-clear">Clear User Selection</button>
            <button @click.prevent="onDeleteUsersSelected" class="button submit button-delete">Delete Users</button>
        </bottom-drawer>
    </div>
</template>

<style lang="scss">
@import "@schoolboxVue/components/vueTables2/vueTables2";

#stale-users-report {
    position: relative;
}

.VueTables--client {
    padding-bottom: 100px; // allow space for bottom-drawer at the bottom
}

.selected-count {
    margin-right: 1rem;
}

.button-clear,
.button-delete,
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
}
</style>
