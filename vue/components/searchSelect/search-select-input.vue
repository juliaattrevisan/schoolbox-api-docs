<script setup lang="ts">
/**
 * To input the search query
 */

import { useSearchStore } from "./store/searchSelectStore";

interface Props {
    placeholder: string; // the placeholder text
}
const props = defineProps<Props>();

const store = useSearchStore();
</script>

<template>
    <input
        ref="queryInputEl"
        v-model="store.query"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="search-select-input"
    />
</template>

<style scoped></style>
