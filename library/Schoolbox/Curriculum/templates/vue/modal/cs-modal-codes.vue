<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected 
 */

import { useCodesStore } from '../store/csCodesStore';
import CsModalCode from './cs-modal-code.vue'

const codesStore = useCodesStore();

</script>

<template>
   <ul>
       <cs-modal-code
            v-for="code in codesStore.codes"
            :key="code.id"
            :code="code"
        />
   </ul>
</template>

<style scoped>

ul {
    list-style: none;
    max-height: 500px; /*restrict the size of the modal, it'll scroll when larger */
    min-height: 500px;
    overflow-y: scroll;
}

</style>