<script setup lang="ts">
/**
 * To display the search input field, accompanied by a search button
 */

interface Props {
    initialInput?: string; // to prefill the input
}
const props = defineProps<Props>();

const emit = defineEmits<{
    input: [event: string];
    keyDown: [];
    keyUp: [];
    keyEnter: [];
    click: [];
    focusOver: [event: FocusEvent];
    focusOut: [event: FocusEvent];
}>();

const currentInput = window.Vue.ref<string>((props.initialInput as string) ?? "");
const inputElement = window.Vue.ref<HTMLInputElement | null>(null);

/**
 * Input updated, update internaly and dispatch emit
 * @param {Event} event - The event object
 */
function onInput(event: Event) {
    currentInput.value = (event.target as HTMLInputElement).value;
    emit("input", currentInput.value);
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
 * If the input is empty, focus over input, otherwise emit click
 */
function onClicked() {
    if (currentInput.value.length > 0) {
        emit("click");
    } else {
        inputElement.value?.focus();
    }
}

/**
 * Focus over input
 * @param {Event} event - The event object
 */
function onFocusOver(event: FocusEvent) {
    emit("focusOver", event);
}

/**
 * Focus out input
 * @param {Event} event - The event object
 */
function onFocusOut(event: FocusEvent) {
    emit("focusOut", event);
}
</script>

<template>
    <input
        id="search-field"
        ref="inputElement"
        type="text"
        name="keyword"
        placeholder="Search"
        maxlength="2048"
        autocomplete="off"
        :value="currentInput"
        @input="onInput"
        @keydown.down="onKeyDown"
        @keydown.up="onKeyUp"
        @keydown.enter.prevent="onKeyEnter"
        @focus="onFocusOver"
        @mouseover="onFocusOver"
        @blur="onFocusOut"
        @focusout="onFocusOut"
    />
    <button
        for="search-field"
        class="icon-search no-text"
        title="Search"
        @mouseover="onFocusOver"
        @click.prevent="onClicked"
    ></button>
</template>

<style scoped></style>
