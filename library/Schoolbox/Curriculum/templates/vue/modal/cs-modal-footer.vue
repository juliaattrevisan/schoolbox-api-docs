<script setup>

/**
 * To cancel or confirm the current code selection
 */

import { useModalStore } from '../store/csModalStore';


const modalStore = useModalStore();

/**
 * Close the modal
 */
function onCancelled () {
    modalStore.close();
}

/**
 * The selection has been confirmed, so close the modal 
 * and insert into the current codes  
 */
function onSelected () {
   
}

</script>

<template>
   <ul class="flex-list buttons">
        <li><button @click.prevent="onCancelled" class="button" :disabled="false">Cancel</button></li>
        <li><button @click.prevent="onSelected" class="button submit" :disabled="true">Select</button></li>
    </ul>
</template>

<style scoped>

</style>