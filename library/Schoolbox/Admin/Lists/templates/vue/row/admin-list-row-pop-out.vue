<script>

/**
 * A 'pop out' for a row, it's a list of options that animate out from the right side
 * Providing a clearer focus on the list of options available to the current row. 
 * Its current intent is to minimise the clutter of a checkbox farm that can become pretty messy UI wise. 
 */

import { mapState, mapActions } from 'pinia';
import { useAdminListStore } from '../store/adminListStore';


export default {

    props: {
        row: { //row to display
            type: Object,
            required: true
        },
    },

    data () {
        return {
            isPopOutDisabled: false //provide ability to access the pop out
        }
    },

    computed: {
        
        ...mapState(useAdminListStore, {
            selectedPopOutName: state => state.selectedPopOutName, //to show all rows with the same pop out option active
            headerField: state => state.popOutHeaderField, //the header field object
            fields: state => state.popOutFields, //the fields to display within the pop out
        }),

        /**
         * To retrieve a list of key/value pairs contained within the row object
         *
         * @returns { Array of Objects }
         */
        rowKeyValueList () {
            const rowKeyValueList = [];
            for (const [key, value] of Object.entries(this.row)) {
                rowKeyValueList.push({ key: key, value: value });
            }
            return rowKeyValueList;
        },

        /**
         * To show only those options in pop out list currently set to true
         *
         * @returns { Array of Objects }
         */
        activeFields () {
            const fieldIdList = this.fields.map((field) => field.id); //a list of just field.id to get the relevant key/value in rowKeyValueList
            const popOutKeyValueList = this.rowKeyValueList.filter((row) => fieldIdList.some((fieldId) => fieldId === row.key)); //pull out those rowKeyValue object pairs that are part of the pop out 
            return this.fields.filter((field, index) => popOutKeyValueList[index].value); //when the value is set to true, put in the list
        },

    },

    created () {
        this.checkChangePopOut();
    },

    methods: {

        ...mapActions(useAdminListStore, ['updateSelectedPopOutName', 'openPopUp']),

        /**
         * If changePopOut exists, there's a field on the row that will impact the status of what's in the pop out
         * For example on Lists > Roles, changing 'Role Type' will change the list of Permissions available 
         * So say for 'Guest' there will be less permissions available then for 'Staff'
         */
        checkChangePopOut () {
            if (this.headerField.changePopOut) {
                this.enableChangePopOutWatcher(); //start tracking changes
                const currentChangePopOutSelection = this.row[this.headerField.changePopOut]; //eg: this will return the current selection of 'Role Type' on Lists > Roles
                const changePopOutOptions = this.headerField.changePopOutOptions[currentChangePopOutSelection];  //the list of options available based on the selection of 'changePopOut'
                this.checkIfDisabled(changePopOutOptions);
            }
        },

        /**
         * If there's a changePopOut set, need to know when its value has changed 
         * so can respond to changes, showing/hiding the fields when updated
         * For example if there's 'Role Type' like in Lists > Roles, we would hide all fields if 'Guest' was selected
         */
        enableChangePopOutWatcher () {
            this.$watch(`row.${this.headerField.changePopOut}`, (newValue) => {
                const changePopOutOptions = this.headerField.changePopOutOptions[newValue]; //the list of options available based on the selection of 'changePopOut' 
                this.updatePopOut(changePopOutOptions);
            });
        },

        /**
         * Pass through the array of options that fill out the pop out and dicate its display status
         * 
         * @param { Array }
         */
        updatePopOut (changePopOutOptions) {
            this.checkIfDisabled(changePopOutOptions);
            this.toggleOptionsOnChangePopOut(changePopOutOptions);
        },

        /**
         * Pass through the array of options that fill out the pop out and dicate its display status
         * 
         * @param { Array }
         */
        checkIfDisabled (changePopOutOptions) {
            if (changePopOutOptions.length > 0) { //if there's no options avaiable in the current changePopOut value, opening the pop out should be disabled
                this.isPopOutDisabled = false; 
            } else {
                this.isPopOutDisabled = true;
            }
        },

        /**
         * Pass through the array of options that fill out the pop out and dicate its display status
         * 
         * @param { Array }
         */
        toggleOptionsOnChangePopOut (changePopOutOptions) {
            this.fields.forEach((option) => { //loop through and set those options to true that are available in the current changePopOutOption
                if (changePopOutOptions.includes(option.id)) {
                    this.row[option.id] = true;
                } else {
                    this.row[option.id] = false;
                }
            });
        },

        /**
         * To highlight all rows which also have the same pop out option
         * for an easier visual way to check which rows have the same pop out option
         * 
         * @param { String }
         */
        onPopOutNameSelected (popOutName) {
            this.updateSelectedPopOutName(popOutName);
        },

        /**
         * Display the options within the pop out
         */
        onOpenPopUpSelected () {
            this.openPopUp(this.row);
        },

    }

}
</script>

<template>
    <div class="list-container">
        <transition-group name="list" tag="div" class="list">
            <button v-for="field in activeFields" :key="field.caption" :class="[ selectedPopOutName === field.caption ? 'item-active' : '', 'item']" @click.prevent="onPopOutNameSelected(field.caption)">{{ field.caption }}</button>
        </transition-group>
        <button @click.prevent="onOpenPopUpSelected" :disabled="isPopOutDisabled" class="update-button button icon-modify" title="Modify"></button>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/core/utils/_variables.scss';

.list-container { //to contain the .pop-out-list and the .update-button
    display: flex;
    align-items: stretch;
    width: 100%;
    border: 1px solid $form-border-color;
}

.list {
    width: 100%;
    padding: 2px;
    transition: height 0.3s;
}

.list-enter, .list-leave-to { //to animate on/off when toggling in #roles-list-permissions.vue
    opacity: 0;
    transform: translateY(5px);
}

.list-leave-active {
    position: absolute;
}

.item {
    min-height: auto;
    margin: 2px;
    padding: 5px 10px;
    color: #000;
    font-size: 0.7rem;
    text-align: center;
    background: $body-bg;
    transition: all 0.3s;
    &:hover {
        opacity: 0.8;
    }
}

.item-active {
    background: $content-ui-background;
}

.update-button {
    flex: 0 0 40px;
    margin: 0px;
    padding: 0.5rem;
    &:before { //to fix weird button wiggle on press otherwise
        float: none;
        padding: 0;
    }
}

.delete-row-button {
    width: 40px;
    height: 40px;
    margin: 14px 8px 0px 0px;
}

</style>
