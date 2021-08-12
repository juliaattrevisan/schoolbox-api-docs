/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { mapState } from 'vuex';

export const mixinAddRow = {
 
    data () {
        return {
            newId: 0 //to add another row to the db, decrement with each new row added,
        }
    },

    computed: {
        ...mapState({
            loadedData: state => state.loadedData,
            rows: state => state.rows,
        }),
    },
 
    methods: {

        /**
         * Push in a new row, retrieving the default value for each property and 
         * then adding in additional values required to track and add
         */
        $_mixinAddRow_onNewRowSelected () {
            const newRow = {}
            this.loadedData.fields.forEach((field) => {
                if (Object.keys(field).includes('default')) {
                    newRow[`${field.id}`] = field.default;
                } else if (Object.keys(field).includes('lookup')) { //for lookups, use the first value as default
                    newRow[`${field.id}`] = field.lookup[0]?.key ? field.lookup[0].key : '';
                }
            });
            newRow.sequence = this.getNextSequence();
            newRow.id = this.getNextId(),
            newRow.isNew = true;
            this.$store.commit('addRow', newRow);
        },

        /**
         * The new row is placed at the bottom of the list, so it'll
         * be the last number in the sequence
         *
         * @param { Number }
         */
        getNextSequence () {
            return this.rows.length + 1;
        },

        /**
         * Retrieve the lowest id and decrement for it to
         * be used for the new row
         *
         * @param { String }
         */
        getNextId () {
            this.newId--;
            return this.newId.toString();
        },

    }
 
 }
 