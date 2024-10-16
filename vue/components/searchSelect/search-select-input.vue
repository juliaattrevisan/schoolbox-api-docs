<script setup lang="ts">
/**
 * To input the search query
 */

import { onClickOutside, useFocus } from "@vueuse/core";

interface Props {
    labelId: string; // the label id
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

/**
 * To close the search results when the input is not focused
 */
const { focused } = useFocus(props.parentEl);
window.Vue.watch(focused, ($focused) => {
    if (!$focused) {
        query.value = "";
    }
});
</script>

<template>
    <input
        :id="props.labelId"
        v-model="query"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="search-select-input"
    />
</template>

<style scoped></style>
