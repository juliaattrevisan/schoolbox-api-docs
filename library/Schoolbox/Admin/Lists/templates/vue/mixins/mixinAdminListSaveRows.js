/**
 * To save the rows data to the db, used by both admin-list.vue and admin-list-pop-out.vue
 */

import { mapState, mapActions } from "pinia";
import { useAdminListStore } from "../store/adminListStore";

export default {
    data() {
        return {
            saveRows_isProcessing: false,
            saveRows_saveStatus: "Save",
        };
    },

    computed: {
        ...mapState(useAdminListStore, ["loadedData", "rows"]),
    },

    methods: {
        ...mapActions(useAdminListStore, ["updateSequence", "deleteRowCancelled", "isValidated", "closePopUp"]),

        /**
         * Save all data
         */
        async saveRows_onSaveSelected() {
            try {
                this.saveRows_isProcessing = true;
                //check rows for valid / invalid data
                if (!this.isValidated()) {
                    //if form is invalid... return
                    return;
                }
                this.saveRows_saveStatus = "Saving...";
                this.$refs.toast.activate(this.saveRows_saveStatus);
                this.updateSequenceOrder();
                const response = await fetch(this.loadedData.saveRoute, {
                    method: "POST",
                    body: this.getSaveAllFormData(),
                    headers: {
                        Accept: "application/json",
                    },
                });
                const contentType = response.headers.get("content-type");
                if (response.status === 200) {
                    if (contentType.includes("application/json")) {
                        //if it's json there's a list of new row ids
                        const responseJson = await response.json();
                        this.updateNewRowIds(responseJson.newRowIdList);
                    }
                    this.$refs.toast.timeout("Save successful", true, 2000);
                } else {
                    const responseJson = await response.json(); //grab the json error message and show to the user
                    this.$refs.toast.timeout(responseJson.title, false, 2000);
                }
            } catch (error) {
                const responseJson = await response.json(); //grab the json error message and show to the user
                console.warn(error);
                this.$refs.toast.timeout(responseJson.title, false, 2000);
            } finally {
                this.$nextTick(() => {
                    //to avoid animating any newly inserted rows in onBeforeEnter
                    this.saveRows_isProcessing = false;
                    this.saveRows_saveStatus = "Save";
                    this.closePopUp();
                });
            }
        },

        /**
         * Ensure the correct sorting order is reflected
         */
        updateSequenceOrder() {
            const newSequenceList = [];
            const allRowElements = document.querySelector("[data-list]").children;
            Array.from(allRowElements).forEach((rowElement) => {
                // loop through each row in order to set the latest correct sequence
                newSequenceList.push(rowElement.dataset.rowId);
            });
            this.updateSequence(newSequenceList);
        },

        /**
         * Previously spectrum.js was used to set the colour, but this is no longer used.
         * When the colour was cleaed with spectrum.js, it would set the colour value to null.
         * Coloris.js instead sets this to an empty string, so need to replace all instances of "" with null.
         * This is to ensure the colour is set to null in the db, so if somewhere else expects null, we don't have an any side effects.
         * It feels a bit dity, but it's the easiest way to ensure the colour is set to null without getting into the weeds.
         *
         * @param { string } rows - the rows data
         * @returns { string } - rows data with nulls instead of empty strings
         */
        getRowsWithNullColours(rows) {
            let rowsWithNulls = rows.replace(/"hexColour":""/g, '"hexColour":null');
            rowsWithNulls = rows.replace(/"color":""/g, '"color":null');
            return rowsWithNulls;
        },

        /**
         * Send through the data to save the whole list
         *
         * @returns { FormData }
         */
        getSaveAllFormData() {
            const formData = new FormData();
            formData.append("table", this.tableName);
            formData.append("rows", this.getRowsWithNullColours(JSON.stringify(this.rows)));
            return formData;
        },

        /**
         * The new rows have been assigned, so need to match the front end
         *
         * @param { Array or undefined }
         */
        updateNewRowIds(newRowIdList) {
            if (typeof newRowIdList !== "undefined") {
                //won't be returned currently if there's no new rows
                const newRows = this.rows.filter((row) => row.id < 0);
                newRows.forEach((row, index) => {
                    row.id = newRowIdList[index];
                    row.isNew = false;
                });
            }
        },
    },
};
