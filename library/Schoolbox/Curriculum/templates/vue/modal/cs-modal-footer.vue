<script setup>

/**
 * To cancel or confirm the current code selection
 */

import { computed } from 'vue';
import { useModalStore } from '../store/csModalStore';
import { useSelectedCodesStore } from '../store/csSelectedCodesStore';


const modalStore = useModalStore();
const selectedCodesStore = useSelectedCodesStore();

/**
 * Close the modal
 */
function onCancelled () {
    modalStore.cancel();
}

/**
 * The selection has been confirmed, so close the modal 
 * and insert into the current codes  
 */
function onSelected () {
    modalStore.select();
}

/**
 * If there's currently no selected codes, we are Selecting codes, 
 * otherwise we are Updating
 *
 * @returns { String }
 */
const saveText = computed(() => {
    if (selectedCodesStore.hasNoCodes) {
        return 'Select';
    } else {
        return 'Update';
    }
});

/**
 * If there's no codes currently selected and we haven't selected any
 * yet, there's no need to have the save enabled
 *
 * @returns { String }
 */
const isSaveDisabled = computed(() => {
    return selectedCodesStore.hasNoCodes && modalStore.hasNoCodes;
});

</script>

<template>
   <ul class="flex-list buttons">
        <li><button @click.prevent="onCancelled" class="button" :disabled="false">Cancel</button></li>
        <li><button @click.prevent="onSelected" class="button submit" :disabled="isSaveDisabled">{{ saveText }}</button></li>
    </ul>
</template>

<style scoped>

</style>