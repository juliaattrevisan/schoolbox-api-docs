<script>

/**
 * Update an individual rows pop out options
 * Currently only checkboxes are being considered, can be extended as required
 */

import { mapState, mapGetters } from 'vuex';
import { gsap } from 'gsap/all';
import saveRows from './mixins/mixinAdminListSaveRows';
import { CLOSE_POP_UP } from './store/adminListMutationTypes'
import Toast from '@vue/components/toast';

export default {

    components: {
        Toast
    },

    props: {
        tableName: { //the name of the current list, used to submit updates to the DB
            type: String,
            required: true
        }
    },

    mixins: [
        saveRows
    ],

    data () {
        return {
            isActive: true
        }
    },

    computed: {

        ...mapState({
            selectedRow: state => state.selectedRow,
            isOpen: state => state.isPopOutOpen
        }),

        ...mapGetters({
            headerField: 'popOutHeaderField', //the header field object
            fields: 'popOutFields' //the fields to display within the pop out
        }),

        /**
         * When 'changePopOut' is set on the popOutList, we need to consider 
         * displaying only those fields linked to the current selection of 'changePopOutOptions'
         * For example on Lists > Roles, changing 'Role Type' will change the list of Permissions available 
         * If 'changePopOut' isn't set, we can just return all 'popOutList' fields
         *
         * @returns { Array of Objects }
         */
        changePopOutFields () {
            if (this.headerField.changePopOut && this.selectedRow.id) {
                const selectedRowChangePopOutValue = this.selectedRow[this.headerField.changePopOut]; //eg: for 'Role Type', could be 'Staff' or 'Student' 
                const changePopOutOptions = this.headerField.changePopOutOptions[selectedRowChangePopOutValue]; //eg: only the options linked to the selected 'Role Type'
                return this.fields.filter((field) => changePopOutOptions.includes(field.id)); //now only those fields linked to the value
            } else {
                return this.fields; //otherwise when there's no 'changePopOut' just return as normal
            }
        },

        /**
         * Retrieve all checkboxes in the pop out
         *
         * @returns { Array }
         */
        checkboxFieldList () {
            return this.changePopOutFields.filter((field) => field.checkbox);
        },

        /**
         * If there's a few checkboxes in the list, we should show a 'Select All' checkbox
         * for easier selection
         *
         * @returns { Boolean }
         */
        canShowSelectAllCheckbox () {
            return this.checkboxFieldList.length > 2;
        },

        /**
         * Retrieve all selected check boxes
         *
         * @returns { Array }
         */
        selectedCheckboxFieldList () {
            return this.checkboxFieldList.filter((field) => this.selectedRow[field.id]); //will return those checkboxes with a true value
        },

        /**
         * To determine the status of the Select All checkbox
         *
         * @returns { Boolean }
         */
        isAllCheckboxesSelected () {
            return this.selectedCheckboxFieldList.length === this.checkboxFieldList.length; //whether all checkboxes are selected (set as true) or not
        }

    },

    watch: {

        /**
         * To open or close depending on boolean value
         */
        isOpen (newValue) {
            if (newValue === true) {
                this.open();
            } else {
                this.close();
            }
        }

    },

    mounted () {
        this.$emit('on-pop-out-mounted', this.$refs.innerContainer.clientHeight); //to ensure the parent container is tall enough to display the entire pop out
        this.isActive = false;
    },

    methods: {

        /**
         * Animate the view on
         */
        open () {
            this.isActive = true;
            gsap.to(this.$refs.innerContainer, { right: 0, duration: 0.4 } );
        },

        /**
         * Close the view
         */
        close () {
            gsap.to(this.$refs.innerContainer, { right: '-100%', duration: 0.3, onComplete: () => {
                this.isActive = false;
            } } );
        },

        /**
         * check / uncheck all checkboxes
         */
        onSelectAllSelected (event) {
            this.checkboxFieldList.forEach((field) => {
                this.selectedRow[field.id] = event.target.checked;
            });
        },

        /**
         * Close the pop out
         */
        onCloseSelected () {
            this.$store.commit(CLOSE_POP_UP);
        },

    }

}
</script>

<template>
    <div class="outer-container" :style="`display: ${ isActive ? 'block' : 'none' };`">
        <div class="inner-container checklist" ref="innerContainer">
            <h4>{{ selectedRow.name }} {{ headerField.caption }}</h4>
            <a href="#close-pop-out" class="close-button icon-close" @click.prevent="onCloseSelected" :disabled="saveRows_isProcessing"></a>
            <ul class="option-list" data-option-list>
                <li v-if="canShowSelectAllCheckbox" style="margin-bottom: 5px;">
                    <input
                        id="select-all"
                        type="checkbox"
                        :checked="isAllCheckboxesSelected"
                        @change="onSelectAllSelected"
                    />
                    <label for="select-all">Select All</label>
                </li>
                <li v-for="(field, index) in changePopOutFields" :key="index">
                    <input
                        :id="field.caption"
                        type="checkbox"
                        v-model="selectedRow[field.id]"
                    />
                    <label :for="field.caption">{{ field.caption }}<span v-if="field.tooltip" :class="`hint has-tip tip-${ field.tooltipPosition ? field.tooltipPosition : 'top' }`" data-tooltip :title="field.tooltip">?</span></label>
                </li>
            </ul>
            <button @click.prevent="saveRows_onSaveSelected" class="save-button">{{ saveRows_saveStatus }} {{ headerField.caption }}</button>
        </div>
        <toast ref="toast"></toast>
    </div>
</template>

<style lang="scss" scoped>

@import 'www/static/css/foundation/utils/_variables.scss';

.outer-container {
    position: absolute;
    z-index: 1;
    right: 0px;
    top: 0px;
    width: 320px;
    min-height: 100%;
}

.inner-container {
    position: absolute;
    right: -100%;
    top: 0px;
    width: 100%;
    border: 2px solid $form-border-color;
    padding: 1rem;
    background: white;
}

.close-button {
    position: absolute;
    top: 0;
    right: 0;
    padding: 2px 2px 2px 10px;
    font-size: 1.5rem;
}

.save-button {
    width: 100%;
}

</style>
