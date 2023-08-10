<script setup lang="ts">
/**
 * To display the search input field, accompanied by a search button.
 * As the input can be part of a larger component, the prop isActive is used to
 * add the active styles as the input may not always be active if it is
 * within a larger component. In this instance (header-search.vue), and
 * the search-input is only revealed when the user moves over the input in the header.
 */

interface Props {
    isActive: boolean; // to add the active styles
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
 * Mouse over either the input or the search button
 * @param {MouseEvent} event - The event object
 */
function onMouseOver(event: MouseEvent) {
    emit("mouseOver", event);
}

/**
 * Mouse out either the input or the search button
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
        :class="props.isActive ? 'search-input search-input-active' : 'search-input'"
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

<style lang="scss" scoped>
/*
* Styles to suggest the input is 'active'
* As the input can be part of a larger component (header-search.vue)
* we pass the isActive prop to the component to style it accordingly
*/
.search-input-active {
    background-color: white;
    border: 2px solid var(--navigation-hover);
    color: black;
    font-size: var(--base-font-size);
    width: 100%;
    opacity: 1;
    .icon-search {
        width: 4rem;
        /* Search icon when Search feature is activated
        */
        &:before {
            color: var(--navigation-background);
        }
    }
}

// Search icon in its idle state, it sits on the right of the input field
.icon-search {
    align-items: center;
    background: none;
    display: flex;
    justify-content: center;
    line-height: inherit;
    margin: 0;
    padding: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: 5rem;
    z-index: 1;
    &:before {
        transition: color 0.2s ease-in;
        color: var(--navigation-foreground);
    }
    &:hover {
        &:before {
            color: black;
        }
    }
}
</style>
