<script>

/**
 * Select option, also ensuring respects nullable option and 
 * checks for options that have been deleted so they are no longer displayed
 */

import { isObject } from '@js/util/jsUtils'


export default {

    props: {
        field: { //field the lookup is linked to
            type: Object,
            required: true
        },
        row: { //the data for the lookup
            type: Object,
            required: true
        },
    },

    data () {
        return {
            currentSelection: {} //to reference the current selection, to check if deleted 
        }
    },

    computed: {

        /**
         * When using for example Synergetic, the lookup can return an object 
         * rather than an array. So we need to convert to array when it's an object
         * so we can later traverse it
         * 
         * @returns { Array } 
         */
        validatedLookup () {
            if (isObject(this.field.lookup)) {
                return Object.keys(this.field.lookup).map((key) => this.field.lookup[key]); //grab each object and linked key within the object and put in an array
            } else { //already an Array
                return this.field.lookup;
            }
        },

        /**
         * If the current selection is deleted, we should provide the option to select a blank field,
         * as well as including any options that aren't deleted
         * If there's no options that aren't deleted, there will be no options within the select input
         * 
         * @returns { Array } only show those options that haven't been deleted
         */
        availableOptions () {
            if (this.currentSelection.deleted) {
                const availableOptions = this.validatedLookup.filter((option) => {
                    return this.currentSelection.key === option.key || (!option.deleted && this.currentSelection.key !== option.key) //show the current selection, but hide those that are deleted and aren't the current selection
                }); 
                return [ { value: null, key: null, deleted: false }, ...availableOptions]; //then also include the blank select option
            } else {
                return this.validatedLookup.filter((option) => !option.deleted); //only those that aren't deleted
            }
        }

    },

    mounted () {
        this.getCurrentSelection();
    },

    methods: {

        /**
         * To properly dispay this.availableOptions, need to reference the currentSelection
         * Using this method just makes it easier to reference, as we are using the 
         * combination of row, referenced with field id, which is a little confusing otherwise
         */
        getCurrentSelection () {
            if (this.row[this.field.id] && !this.row.isNew) { //if it's not null, or not a new row entry, will be a reference to the field.lookup.key
                this.currentSelection = this.validatedLookup.find((option) => option.key === this.row[this.field.id]);
            }
        },

        /**
         * To see whether we need to notify that this option has been deleted, 
         * so it's no longer selectable
         * 
         * @params { Object }
         * 
         * @returns { String }
         */
        getOptionValue (option) {
            if (option.deleted) { //is using an option that has now been deleted elsewhere
                return `${option.value} (deleted)`;
            } else {
                return option.value;
            }
        }

    }

}
</script>

<template>
    <div>
        <template v-if="field.nullable && validatedLookup.length === 0">
            <select v-model="row[field.id]" disabled>
                <option :value="null">{{ field.nullable_label }}</option>
            </select>
        </template>
        <template v-else-if="validatedLookup.length === 0">
            <select v-model="row[field.id]" disabled>
                <option value="">None Available</option>
            </select>
        </template>
        <template v-else>
            <select v-model="row[field.id]">
                <template v-if="!field.mandatory">
                    <option :value="null">Not Selected</option>
                </template>
                <option v-for="(option, index) in availableOptions" :key="index" :value="option.key" :disabled="option.deleted">{{ getOptionValue(option) }}</option>
            </select>
        </template>
    </div>
</template>