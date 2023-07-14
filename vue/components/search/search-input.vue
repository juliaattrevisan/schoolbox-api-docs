<script setup lang="ts">
/**
 * The search input display, currently used in both the header search and on the search page
 */

interface Props {
    input: string; // to prefill the input with something or keep it updated with any modications
}
const props = defineProps<Props>();

// const emit = defineEmits(["input", "keyDown", "keyUp", "keyEnter", "click", "focusOver", "focusOut"]);
const emit = defineEmits<{
    input: [event: string];
    keyDown: [];
    keyUp: [];
    keyEnter: [];
    click: [];
    focusOver: [event: Event];
    focusOut: [];
}>();

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
 * Search button clicked
 */
function onClicked() {
    emit("click");
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
    <button for="search-field" class="icon-search no-text" title="Search" @click.prevent="onClicked"></button>
</template>

<style scoped></style>
