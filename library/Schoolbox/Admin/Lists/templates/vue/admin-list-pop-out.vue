<script>

/**
 * Update an individual rows pop out options
 * Currently only checkboxes are being considered, can be extended as required
 */

import { gsap } from 'gsap/all';

export default {

    props: {
        headerField: { //the header field object
            type: Object,
            required: true
        },
        fields: { //the fields to display within the pop out
            type: Array,
            required: true
        },
        selectedRow: { //to know which row to display
            type: Object,
            required: true
        },
        isOpen: { //to open and close the list
            type: Boolean,
            required: true
        },
        saveStatus: { //to update the button text when saving
            type: String,
            required: true
        },
        isProcessing: { //to prevent multiple rapid-fire submissions
            type: Boolean,
            required: true
        }
    },

    computed: {

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
        this.$emit('on-pop-out-mounted', this.$refs.innerContainer.clientHeight);
        this.$refs.outerContainer.style.display = 'none';
    },

    methods: {

        /**
         * Animate the view on
         */
        open () {
            this.$refs.outerContainer.style.display = 'block';
            gsap.to(this.$refs.innerContainer, { right: 0, duration: 0.4 } );
        },

        /**
         * Close the view
         */
        close () {
            gsap.to(this.$refs.innerContainer, { right: '-100%', duration: 0.3, onComplete: () => {
                this.$refs.outerContainer.style.display = 'none'
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

    }

}
</script>

<template>
    <div class="outer-container" ref="outerContainer">
        <div class="inner-container checklist" ref="innerContainer">
            <h4>{{ selectedRow.name }} {{ headerField.caption }}</h4>
            <a href="#close-pop-out" class="close-button icon-close" @click.prevent="$emit('on-pop-out-closed')"></a>
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
            <button @click.prevent="$emit('on-pop-out-saved')" class="save-button">{{ saveStatus }}</button>
        </div>
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
