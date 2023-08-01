<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 * currently uses the exisitng jquery selectize plugin
 */

import { useFilterCreatorStore } from "./store/spFilterCreatorStore";

const filterCreatorStore = useFilterCreatorStore();
</script>

<template>
    <div class="small-12 columns">
        <label>Creator</label>
        <input v-model="filterCreatorStore.creator" />
    </div>
</template>

<style scoped></style>
