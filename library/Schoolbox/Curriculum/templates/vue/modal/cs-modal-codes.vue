<script setup>

/**
 * The filtered results from using the <selects> or search <input>
 * From here curriclum codes can be selected 
 */

import { ref } from 'vue';
import { useModalActiveCodesStore } from '../store/csModalActiveCodesStore';
import CsModalCode from './cs-modal-code.vue'

const modalActiveCodesStore = useModalActiveCodesStore();
const depth = ref(0); //to increment each time we go down the node tree to visually indicate children

</script>

<template>
   <ul>
        <cs-modal-code
            v-for="code in modalActiveCodesStore.codes"
            :key="code.id"
            :code="code"
            :depth="depth"
        />
   </ul>
</template>

<style scoped>

ul {
    list-style: none;
    margin: 0;
    max-height: 500px; /*restrict the size of the modal, it'll scroll when larger */
    min-height: 500px;
    overflow-y: scroll;
}

</style>