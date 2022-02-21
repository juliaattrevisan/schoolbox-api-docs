<script>

/**
 * Quicker saving of admin settings on some pages with faster save action display 
 * as the save button is presented as soon as any form data changes, so there's 
 * no need to scroll down to the bottom of the page
 */

import BottomDrawer from '@schoolboxVue/components/bottomDrawer/bottom-drawer';
import debounce from 'lodash/debounce';


export default {

    components: {
        BottomDrawer
    },

    props: {
        formId: { //the id of the form to listen to changes to
            type: String,
            required: true,
            default: 'settings',
        },
        /* Decide if we need special polling mechanism for dropzones and color pickers
         * since they don't trigger onchange normally, probably because jquery
         */
        polling: {
            type: Number,
            required: true,
            default: 0,
        }
    },

    data () {
        return { 
            hasFormDataChange: false,
            isAtBottomOfPage: false,
            initialFormDataValues: '',
            currentFormDataValues: '',
        }
    },

    computed: {
        
        /**
         * Only show the save button if there's a form data change 
         * or the bottom of the page is visible
         */
        showSaveButton () {
            return this.hasFormDataChange || this.isAtBottomOfPage;
        },

    },

    mounted () {
        this.initIntersectionObserver();
        this.initFormData();
        this.initFormChangeListeners();
    },

    methods: {

        /**
         * To track whether we are at the bottom of the page, to check if we can show the bottom drawer
         * https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
         */
        initIntersectionObserver () {
            const intersectionObserver = new IntersectionObserver((elements) => {
                this.isAtBottomOfPage = elements[0].intersectionRatio !== 0;
            });
            intersectionObserver.observe(this.$el);
        },

        /**
         * Save the initial form data state for later comparision
         */
        initFormData () {
            const formData = new FormData(document.getElementById(this.formId));
            formData.forEach((value) => this.initialFormDataValues += value);
            this.currentFormDataValues = this.initialFormDataValues;
        },

        /**
         * Listen for form data changes to show the save button straight away on a change
         */
        initFormChangeListeners () {
            CKEDITOR.on('instanceReady', (instanceReadyEvent) => { //need to wait until the ckEditor is loaded prior to listening for chnages
                instanceReadyEvent.editor.on('change', debounce((changeEvent) => { //update the linked <textarea>, which doesn't update until form save otherwise
                    document.getElementById(changeEvent.editor.name).textContent = changeEvent.editor.getData();
                    this.onFormDataChange()
                }, 350));
            });

            /* Polling is required for dropzones and colorpickers since they don't
             * trigger onchange events normally (probably because jquery plugins)
             */
            if (this.polling) {
                setInterval(this.onFormDataChange, 500);
            } else {
                document.getElementById(this.formId).addEventListener('change', () => this.onFormDataChange());
                document.getElementById(this.formId).addEventListener('keyup', debounce(() => {
                    this.onFormDataChange();
                }, 350));
            }
        },

        /**
         * A form change has occured, so check if the data is different so we can show
         * the save button
         */
        onFormDataChange () {
            this.currentFormDataValues = '';
            const formData = new FormData(document.getElementById(this.formId));
            formData.forEach((value) => this.currentFormDataValues += value); //loop through and grab all the form values, creating a single string
            this.hasFormDataChange = this.initialFormDataValues !== this.currentFormDataValues; //and now compare the old value to the new
        },

    },

}

</script>

<template>
   <div class="save-container">
        <bottom-drawer :showDrawer="showSaveButton">
            <input type="submit" value="Save" />
        </bottom-drawer>
   </div>
</template>

<style scoped>

.save-container {
    min-height: 30px; /*to provide a bit of space for the bottom-drawer */
}

</style>