<script setup>

/**
 * To list all the currently selected Curriculum codes
 */

import { useSelectedCodesStore } from './store/csSelectedCodesStore';


const selectedCodesStore = useSelectedCodesStore();

</script>

<template>
    <h3>Codes</h3>
    <ul>
        <li
            v-for="code in selectedCodesStore.codes"
            :key="code.id"
        >
        {{ code.name }}
        </li>
    </ul>
</template>

<style scoped>

</style>