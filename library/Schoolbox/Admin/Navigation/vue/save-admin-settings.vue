<script>

/**
 * Quicker saving of admin settings on some pages with faster save action display 
 * as the save button is presented as soon as any form data changes, so there's 
 * no need to scroll down to the bottom of the page
 */

import BottomDrawer from '@vue/components/bottomDrawer/bottom-drawer';


export default {

    components: {
        BottomDrawer
    },

    props: {
        formId: { //the id of the form to listen to changes to
            type: String,
            required: true,
            default: 'settings',
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
         * Listen for form data changes to show the save button
         */
        initFormChangeListeners () {
            document.getElementById(this.formId).addEventListener('change', () => this.onFormDataChange());
            CKEDITOR.on('instanceReady', (instanceReadyEvent) => { //need to wait until the ckEditor is loaded prior to listening for chnages
                instanceReadyEvent.editor.on('blur', (changeEvent) => { //update the linked <textarea>, which doesn't update until form save otherwise
                    document.getElementById(changeEvent.editor.name).textContent = changeEvent.editor.getData();
                    this.onFormDataChange()
                });
            });
        },

        /**
         * A form change has occured, so check if the data is different so we can show
         * the save button
         */
        onFormDataChange () {
            this.currentFormDataValues = '';
            const formData = new FormData(document.getElementById(this.formId));
            formData.forEach((value) => this.currentFormDataValues += value);
            this.hasFormDataChange = this.initialFormDataValues !== this.currentFormDataValues;
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