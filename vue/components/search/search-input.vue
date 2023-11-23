<script setup lang="ts">
/**
 * To display the search input field, accompanied by a search button.
 * As the input can be part of a larger component, the prop isActive is used to
 * add the active styles as the input may not always be active if it is
 * within a larger component. In this instance (header-search.vue), and
 * the search-input is only revealed when the user moves over the input in the header.
 */

interface Props {
    initialInput?: string; // to prefill the input
    isActiveWhenEmpty?: boolean; // can be selected while the input is empty
}
const props = withDefaults(defineProps<Props>(), {
    initialInput: "",
    isActiveWhenEmpty: true,
});

const emit = defineEmits<{
    input: [value: string];
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
const hasInputFocus = window.Vue.ref(false);

/**
 * Input updated, update internally and dispatch emit
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
    if (props.isActiveWhenEmpty) {
        emit("click");
        return;
    }
    if (currentInput.value.length > 0) {
        emit("click");
        return;
    }
    if (hasInputFocus.value) {
        emit("click");
        return;
    }
    inputElement.value?.focus();
}

/**
 * Focus over input
 * @param {FocusEvent} event - The event object
 */
function onFocusOver(event: FocusEvent) {
    hasInputFocus.value = true;
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
    <div class="c-search-input">
        <input
            id="search-field"
            ref="inputElement"
            class="c-search-input__field"
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
            class="c-search-input__button icon-search no-text"
            title="Search"
            @mouseover="onMouseOver"
            @mouseout="onMouseOut"
            @click.prevent="onClicked"
        ></button>
    </div>
</template>

<style lang="scss">
.c-search-input {
    display: grid;
    grid-template-columns: 1fr 5rem;

    input[type="text"].c-search-input__field {
        border: 2px solid var(--navigation-background);
        background-color: var(--foreground-colour);
        color: var(--primary-text-colour);
        font-size: var(--base-font-size);
        height: var(--tabbar-height);
        padding: var(--base-font-size);
        margin: 0;
        line-height: calc(var(--tabbar-height) / 2);
    }

    &__button {
        align-items: center;
        background-color: var(--content-ui-submit-background);
        display: flex;
        height: var(--tabbar-height);
        justify-content: center;
        margin: 0;
        padding: 0;
        &:before {
            transition: all 0.2s ease-in;
            color: var(--content-ui-submit-foreground);
        }
        &:hover {
            background-color: rgba(
                calc(var(--content-ui-submit-background-r) + (255 - var(--content-ui-submit-background-r)) * 0.2),
                calc(var(--content-ui-submit-background-g) + (255 - var(--content-ui-submit-background-g)) * 0.2),
                calc(var(--content-ui-submit-background-b) + (255 - var(--content-ui-submit-background-b)) * 0.2),
                var(--content-ui-submit-background-a)
            );
        }
    }
}
</style>
