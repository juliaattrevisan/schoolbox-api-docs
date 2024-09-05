<script setup lang="ts">
/**
 * To input the search query
 */

interface Props {
    placeholder: string; // the placeholder text
}
const props = defineProps<Props>();

const emit = defineEmits<{
    input: [value: string];
}>();

const currentInput = window.Vue.ref<string>("");

/**
 * Input updated, update internaly and dispatch emit
 * @param event - The event object
 */
function onInput(event: Event): void {
    currentInput.value = (event.target as HTMLInputElement).value;
    emit("input", currentInput.value);
}
</script>

<template>
    <input
        ref="queryInputEl"
        type="text"
        class="search-input f-no-margins"
        :placeholder="props.placeholder"
        data-test="search-select-input"
        @input="onInput"
    />
</template>

<style scoped></style>
