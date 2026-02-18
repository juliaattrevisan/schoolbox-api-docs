import { defineStore } from "pinia";
import { cloneDeep, orderBy } from "lodash";

export const useAdminListStore = defineStore("main", {
    state: () => ({
        loadedData: null, // contains the loaded data
        rows: [], // to display and possibly edit the rows
        selectedRow: {}, // to track which row is selected
        rowToDelete: {}, // to track which row could be deleted
        isPopOutOpen: false, // to open and close the list
        selectedPopOutName: "", // to highlight all other rows that have that permission
        colorPickerId: 0, // to get a new colour picker id
    }),

    getters: {
        /**
         * Retrieve only those fields that will appear in the header
         *
         * @returns { Array of Objects }
         */
        headerFields(state) {
            return state.loadedData.fields.filter((field) => field.hasOwnProperty("width")); //use width to determine if it's to display in the header
        },

        /**
         * the popOut field that's used in the header, and is the reference for
         * the following popOutFields
         *
         * @returns { Object }
         */
        popOutHeaderField() {
            const popOutHeaderField = this.headerFields.find((field) => field.isPopOutList); //use width to determine if it's to display in the header
            if (typeof popOutHeaderField !== "undefined") {
                return popOutHeaderField;
            }
            return {};
        },

        /**
         * Just those fields that are used in a popoutList
         *
         * @returns { Array of Objects }
         */
        popOutFields(state) {
            return state.loadedData.fields.filter((field) => field.popOutList);
        },
    },

    actions: {
        /**
         * The requested data is loaded from the backend
         */
        updateLoadedData(loadedData) {
            this.loadedData = Object.freeze(loadedData); //freeze the loaded data to ensure it's not changed
            const loadedRows = cloneDeep(this.loadedData.rows);
            const sortedRows = orderBy(loadedRows, "sequence", "asc"); // to display according to sequence
            sortedRows.forEach((row) => (row.isNew = false)); //Add a boolean to know whether the row is one being newly added
            this.rows = sortedRows;
        },

        /**
         * Can be chosen by opening the pop out
         */
        updateSelectedRow(row) {
            this.selectedRow = row;
            this.selectedPopOutName = "";
        },

        /**
         * To highlight all rows which also have the same pop out option
         * for an easier visual way to check which rows have the same pop out option
         */
        updateSelectedPopOutName(popOutName) {
            if (this.selectedPopOutName === popOutName) {
                //so can toggle on/off
                this.selectedPopOutName = "";
            } else {
                this.selectedPopOutName = popOutName;
            }
        },

        /**
         * Add a row
         */
        addRow(row) {
            this.rows = [...this.rows, row];
        },

        /**
         * Provide a prompt to confirm deletion of row
         */
        deleteRowPrompt(rowToDelete) {
            this.rowToDelete = rowToDelete;
        },

        /**
         * Now the row is deleted, remove it from the rows list
         */
        deleteRowConfirmed(rowToDelete) {
            this.rows = this.rows.filter((row) => row.id !== rowToDelete.id);
        },

        /**
         * To clear the rowToDelete object, otherwise we couldn't reselect the same row to delete
         */
        deleteRowCancelled() {
            this.rowToDelete = null;
        },

        /**
         * Display the options within the pop out
         */
        openPopUp(row) {
            this.selectedRow = row;
            this.isPopOutOpen = true;
            this.selectedPopOutName = "";
        },

        /**
         * Close the pop out
         */
        closePopUp() {
            this.isPopOutOpen = false;
        },

        /**
         * Ensure the latest sorted order is saved to the DB
         */
        updateSequence(sequenceList) {
            sequenceList.forEach((item, index) => {
                const matchingRowData = this.rows.find((row) => Number(row.id) === Number(item)); //get the row data matched to the current row DOM element
                matchingRowData.sequence = ++index;
            });
        },

        /**
         * Before the form is saved we need to check if there's any empty
         * fields that are mandatory and if so prevent the form from submission
         * and display the error on the relevant row fields.
         * To do this we loop through the fields
         * and the associated rows for each. Because of the structure
         * of the data, (with the fields and rows being separate objects)
         * the check is a little more complicated than what we might expect.
         **/
        isValidated() {
            let valid = true;
            this.rows.forEach((row, index) => {
                // check each row
                // set the field validation attribute
                this.rows[index].hasValidationError = [];
                // check each cell in the row
                this.loadedData.fields.forEach((header) => {
                    const headerId = `${header.id}`;
                    // if the header for that cell is mandatory, and the cell is empty, set the error state
                    if (header.mandatory && String(row[headerId]).trim() === "") {
                        valid = false;
                        this.rows[index].hasValidationError[headerId] = true;
                    } else {
                        this.rows[index].hasValidationError[headerId] = false;
                    }
                });
            });
            return valid;
        },

        /**
         * To get a new colour picker id
         *
         * @return { number }
         */
        getNewColourPickerId() {
            this.colorPickerId++;
            return this.colorPickerId;
        },
    },
});
