<script setup>

/**
 * To select Curriculum codes
 */

const isLoading = ref(false);
const hasLoadError = ref(false);
const isClassSelectorActive = ref(true);
const query = ref('');
const inputPlaceholder = ref('Search');

</script>

<template>
    <input name="curriculumCodes[]" type="hidden" tabindex="-1" value="" />
    <input
        v-if="isClassSelectorActive"
        v-model="query"
        ref="queryInputEl"
        type="text"
        class="search-input"
        :readonly="isLoading || hasLoadError"
        :placeholder="inputPlaceholder"
        @input="$event.target.composing = false"
    />
    <button class="button icon-curriculum" title="Browse and select from the Curriculum">Browse</button>
</template>

<style scoped>

</style>