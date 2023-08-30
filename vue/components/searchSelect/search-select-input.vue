<script setup lang="ts">
/**
 * To input the search query
 */

const emit = defineEmits<{
    input: [value: string];
}>();

const currentInput = window.Vue.ref<string>("");

/**
 * Input updated, update internaly and dispatch emit
 * @param {Event} event - The event object
 */
function onInput(event: Event) {
    currentInput.value = (event.target as HTMLInputElement).value;
    emit("input", currentInput.value);
}
</script>

<template>
    <input ref="queryInputEl" type="text" class="search-input" data-test="search-select-input" @input="onInput" />
</template>

<style scoped></style>
