/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { mapState, mapActions } from "pinia";
import { useAdminListStore } from "../store/adminListStore";

export default {
    computed: {
        ...mapState(useAdminListStore, ["loadedData", "rows"]),
    },

    methods: {
        ...mapActions(useAdminListStore, ["addRow"]),

        /**
         * Push in a new row, retrieving the default value for each property and
         * then adding in additional values required to track and add
         */
        addRow_onNewRowSelected() {
            const newRow = {};
            this.loadedData.fields.forEach((field) => {
                if (Object.keys(field).includes("default")) {
                    newRow[`${field.id}`] = field.default;
                } else if (Object.keys(field).includes("lookup") && Object.keys(field).includes("nullable")) {
                    //ensure null is the default select option when available
                    newRow[`${field.id}`] = null;
                } else if (Object.keys(field).includes("lookup")) {
                    //otherwise use the first value as default
                    newRow[`${field.id}`] = field.lookup[0]?.key ? field.lookup[0].key : ""; //use an empty string if can't find a key
                }
            });
            newRow.sequence = this.getNextSequence();
            ((newRow.id = this.getNextId()), (newRow.isNew = true));
            this.addRow(newRow);
        },

        /**
         * The new row is placed at the bottom of the list, so it'll
         * be the last number in the sequence
         *
         * @param { Number }
         */
        getNextSequence() {
            return this.rows.length + 1;
        },

        /**
         * New rows are set with an ever increasing negative increment to
         * mark them as new for the back end
         *
         * @param { String }
         */
        getNextId() {
            // Find the lowest negative number that isn't already in use. with each new row the id will decrement by 1
            const newId = Math.min(...this.rows.map((row) => parseInt(row.id, 10)), 0) - 1;
            return newId.toString();
        },
    },
};
