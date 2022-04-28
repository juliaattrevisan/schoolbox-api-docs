<script setup>

/**
 * Quicker saving of admin settings on some pages with faster save action display 
 * as the save button is presented as soon as any form data changes, so there's 
 * no need to scroll down to the bottom of the page
 */

import BottomDrawer from '@schoolboxVue/components/drawer/bottom-drawer';
import debounce from 'lodash/debounce';
import { ref, computed, onMounted } from 'vue';


const props = defineProps({
    formId: { //the id of the form to listen to changes to
        type: String,
        required: true,
        default: 'settings',
    },
    /** 
     * Decide if we need special usePolling mechanism for dropzones and color pickers
     * since they don't trigger onchange normally, probably because jquery
     */
    usePolling: {
        type: Boolean,
        required: true,
        default: 0,
    },
});

const saveContainer = ref(null)
const hasFormDataChange = ref(false);
const isAtBottomOfPage = ref(false);
const initialFormDataValues = ref('');
const currentFormDataValues = ref('');

/**
 * Only show the save button if there's a form data change 
 * or the bottom of the page is visible
 */
const showSaveButton = computed (() => hasFormDataChange.value || isAtBottomOfPage.value);

onMounted (() => {
    initIntersectionObserver();
    initFormData();
    initFormChangeListeners();
});

/**
 * To track whether we are at the bottom of the page, to check if we can show the bottom drawer
 * https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 */
function initIntersectionObserver () {
    const intersectionObserver = new IntersectionObserver((elements) => {
        isAtBottomOfPage.value = elements[0].intersectionRatio !== 0;
    });
    intersectionObserver.observe(saveContainer.value);
}

/**
 * Save the initial form data state for later comparision
 */
function initFormData () {
    const formData = new FormData(document.getElementById(props.formId));
    formData.forEach((value) => initialFormDataValues.value += value);
    currentFormDataValues.value = initialFormDataValues.value;
}

/**
 * Listen for form data changes to show the save button straight away on a change
 */
function initFormChangeListeners () {
    CKEDITOR.on('instanceReady', (instanceReadyEvent) => { //need to wait until the ckEditor is loaded prior to listening for chnages
        instanceReadyEvent.editor.on('change', debounce((changeEvent) => { //update the linked <textarea>, which doesn't update until form save otherwise
            document.getElementById(changeEvent.editor.name).textContent = changeEvent.editor.getData();
            onFormDataChange()
        }, 350));
    });
    /**
     * Polling is required for dropzones and colorpickers since they don't
     * trigger onchange events normally (probably because jquery plugins)
     */ 
    if (props.usePolling) {
        setInterval(onFormDataChange, 500);
    } else {
        document.getElementById(props.formId).addEventListener('change', () => onFormDataChange());
        document.getElementById(props.formId).addEventListener('keyup', debounce(() => {
            onFormDataChange();
        }, 350));
    }
}

/**
 * A form change has occured, so check if the data is different so we can show
 * the save button
 */
function onFormDataChange () {
    currentFormDataValues.value = '';
    const formData = new FormData(document.getElementById(props.formId));
    formData.forEach((value) => currentFormDataValues.value += value); //loop through and grab all the form values, creating a single string
    hasFormDataChange.value = initialFormDataValues.value !== currentFormDataValues.value; //and now compare the old value to the new
}

</script>

<template>
   <div class="save-container" ref="saveContainer">
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