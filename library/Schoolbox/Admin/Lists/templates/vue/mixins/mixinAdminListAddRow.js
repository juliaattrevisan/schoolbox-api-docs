/**
 * Helper for Vue.js FullCalendar implementations:
 * contains common code that can be shared between all implementations.
 */

import { mapState } from 'vuex';
import { ADD_ROW } from '../store/adminListMutationTypes';

export default {

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
        addRow_onNewRowSelected () {
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
            this.$store.commit(ADD_ROW, newRow);
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
         * New rows are set with an ever increasing negative increment to
         * mark them as new for the back end
         *
         * @param { String }
         */
        getNextId () {
            const newId = this.loadedData.rows.length - (this.rows.length+1); //+1 as the row hasn't been added in yet, with each new row the id will decrement
            return newId.toString();
        },

    }
 
 }
 