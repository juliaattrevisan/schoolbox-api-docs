<script setup>

/**
 * The modal header, containing the title, select nodes and search input
 */

import { useModalStore } from '../store/csModalStore';


const modalStore = useModalStore();

</script>

<template>
   <h2>Select Curriculum Codes</h2>
   <div class="node-options">
        <select>
            <option>1</option>
        </select>
        <select>
            <option>1</option>
        </select>
        <select>
            <option>1</option>
        </select>
   </div>
   <input
        v-if="true"
        v-model="modalStore.query"
        ref="queryInputEl"
        type="text"
        class="search-input"
        :readonly="false"
        placeholder="Search for codes or keywords..."
        @input="$event.target.composing = false"
    />
</template>

<style scoped>

.node-options {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(3, 1fr);
}

</style>