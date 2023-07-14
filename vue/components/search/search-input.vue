<script setup lang="ts">
/**
 * The search input display, currently used in both the header search and on the search page
 */

// interface Props {
//     endpoint: string; // the search api endpoint
// }
// const props = defineProps<Props>();

const emit = defineEmits(["input", "keyDown", "keyUp", "keyEnter", "focusOver", "focusOut"]);

/**
 * Input added
 * @param {Event} event - The event object
 */
function onInput(event: Event) {
    emit("input", (event.target as HTMLInputElement).value);
}

/**
 * Down key pressed
 */
function onKeyDown() {
    emit("keyDown");
}

/**
 * Up key pressed
 */
function onKeyUp() {
    emit("keyUp");
}

/**
 * Enter key pressed
 */
function onKeyEnter() {
    emit("keyEnter");
}

/**
 * Focus over input
 * @param {Event} event - The event object
 */
function onFocusOver(event: Event) {
    emit("focusOver", event);
}

/**
 * Focus over input
 */
function onFocusOut() {
    emit("focusOut");
}
</script>

<template>
    <input
        id="search-field"
        type="text"
        name="keyword"
        placeholder="Search"
        maxlength="2048"
        autocomplete="off"
        @input="onInput"
        @keydown.down="onKeyDown"
        @keydown.up="onKeyUp"
        @keydown.enter.prevent="onKeyEnter"
        @focus="onFocusOver"
        @mouseover="onFocusOver"
        @blur="onFocusOut"
        @focusout="onFocusOut"
    />
</template>

<style scoped></style>
