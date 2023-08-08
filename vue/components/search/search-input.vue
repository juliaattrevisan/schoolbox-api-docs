<script setup lang="ts">
/**
 * To display the search input field, accompanied by a search button
 */

interface Props {
    isActive: boolean; //
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
    mouseOver: [event: MouseEvent];
    focusOut: [event: FocusEvent];
    mouseOut: [event: MouseEvent];
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
 * @param {FocusEvent} event - The event object
 */
function onFocusOver(event: FocusEvent) {
    emit("focusOver", event);
}

/**
 * Focus out input
 * @param {FocusEvent} event - The event object
 */
function onFocusOut(event: FocusEvent) {
    emit("focusOut", event);
}

/**
 *
 * @param {MouseEvent} event - The event object
 */
function onMouseOver(event: MouseEvent) {
    emit("mouseOver", event);
}

/**
 *
 * @param {Event} event - The event object
 */
function onMouseOut(event: MouseEvent) {
    emit("mouseOut", event);
}
</script>

<template>
    <input
        id="search-field"
        ref="inputElement"
        :class="props.isActive ? 'search-input-active' : ''"
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
        @mouseover="onMouseOver"
        @mouseout="onMouseOut"
        @blur="onFocusOut"
        @focusout="onFocusOut"
    />
    <button
        for="search-field"
        class="icon-search no-text"
        title="Search"
        @mouseover="onMouseOver"
        @mouseout="onMouseOut"
        @click.prevent="onClicked"
    ></button>
</template>

<style scoped></style>
