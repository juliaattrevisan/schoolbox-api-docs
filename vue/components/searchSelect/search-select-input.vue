<script setup lang="ts">
/**
 * To input the search query
 */

import { onClickOutside } from "@vueuse/core";

interface Props {
    parentEl: HTMLDivElement | null; // the parent element to close the search results
    placeholder: string; // the placeholder text
}
const props = defineProps<Props>();

const emit = defineEmits<{
    input: [value: string];
}>();

const query = window.Vue.ref<string>("");

/**
 * To emit the search query whenever it changes
 */
window.Vue.watch(query, () => {
    emit("input", query.value);
});

/**
 * To close the search results when clicking outside
 */
window.Vue.onMounted(async () => {
    await window.Vue.nextTick();
    onClickOutside(props.parentEl, () => {
        query.value = "";
    });
});
</script>

<template>
    <input
        ref="queryInputEl"
        v-model="query"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="search-select-input"
    />
</template>

<style scoped></style>
