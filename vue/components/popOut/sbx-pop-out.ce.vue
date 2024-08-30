<script setup lang="ts">
/**
 * Used to display a list of actions in a dropdown menu upon clicking a button (typically the jellytot)
 */
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";

interface Props {
    /**
     * The id of the content to display
     */
    contentId: string;
    /**
     * The text to display on the button
     */
    buttonText?: string;
    /**
     * Whether the button is to be disabled
     */
    disabled?: boolean;
    /**
     * Whether the button should be full width, stretching to the width of the parent container
     */
    isFullWidth?: boolean;
    /**
     * Whether to use an arrow icon instead of the default icon, which will switch the icon rotation
     * depending on whether the button is opened or closed
     */
    isArrowIcon?: boolean;
    /**
     * Whether the dropdown is in a component action and requires specific styling
     */
    isComponentAction?: boolean;
    /**
     * Close the dropdown when the content is clicked
     */
    isCloseOnContentClick?: boolean;
    /**
     * An optional element to move the pop out content to.
     * If a string is provided (rather than an element), it will be treated as a querySelector
     *
     * See moveContentToNewParent in usePopOut.ts for more details
     */
    moveTo?: HTMLElement | string;
}
const props = defineProps<Props>();

const button = window.Vue.ref<HTMLButtonElement | null>(null); // The button that triggers the dropdown display
const { currentLabel, isOpen, onButtonSelected } = usePopOut(
    button,
    props.contentId,
    props.buttonText ? props.buttonText : "Actions",
    props.isFullWidth ? "bottom-start" : "bottom-end",
    props.isFullWidth,
    props.isCloseOnContentClick,
    props.moveTo,
);
</script>

<template>
    <button
        ref="button"
        type="button"
        class="sbx-button"
        :class="{
            'sbx-button--icon-only': !props.buttonText,
            'sbx-button--block': props.isFullWidth,
            'sbx-button--component-action': props.isComponentAction,
        }"
        :title="currentLabel"
        :aria-label="currentLabel"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        :aria-controls="props.contentId"
        :aria-pressed="isOpen"
        :data-test="`sbx-pop-out-${props.contentId}-button`"
        :disabled="props.disabled"
        @click.prevent="onButtonSelected"
    >
        <!-- when an alternative icon should be used -->
        <span v-if="props.buttonText">{{ props.buttonText }}</span>
        <!-- To use the arrow icon -->
        <template v-if="props.isArrowIcon">
            <svg
                version="1.2"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 32 32"
                width="16"
                height="16"
                aria-hidden="true"
                focusable="false"
                class="sbx-button__icon sbx-button__icon--dropdown"
            >
                <path d="M0 24h32l-16-16z"></path>
            </svg>
        </template>
        <template v-else>
            <slot name="icon">
                <svg
                    version="1.2"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 16 16"
                    width="16"
                    height="16"
                    aria-hidden="true"
                    focusable="false"
                    class="sbx-button__icon"
                >
                    <path d="m6 0h4v4h-4z" />
                    <path d="m6 6h4v4h-4z" />
                    <path d="m6 12h4v4h-4z" />
                </svg>
            </slot>
        </template>
    </button>
</template>

<style lang="scss" scoped>
.sbx-button {
    align-items: center;
    align-self: start; // https://moderncss.dev/css-button-styling-guide/#gotcha-child-of-flex-columns
    background: none;
    border: none;
    // border-radius: var(--roundness-form-elements); // ^foundation-fixes^ - will round in a future release
    border-radius: 0;
    color: var(--content-ui-foreground);
    cursor: pointer;
    display: inline-flex;
    gap: var(--spacing-s);
    justify-content: center;
    line-height: 1;
    font-family: inherit;
    font-size: var(--font-size-m);
    min-height: 2.5rem;
    min-width: 2.5rem;
    padding: var(--spacing-s) var(--spacing-m);
    text-align: center;
    &:hover,
    &:active {
        &:not([disabled]) {
            background-color: var(--content-ui-hover);
        }
    }
    &:focus {
        &:not([disabled]) {
            background-color: var(
                --content-ui-background
            ); // ^foundation-fixes^ - don't lighten on focus, just reduces contrast
        }
    }
    &:disabled {
        opacity: 0.7;
        &:hover {
            cursor: default;
        }
    }
    &--icon-only {
        padding: var(--spacing-s);
    }
    &--block {
        width: 100%;
    }
    &__icon {
        fill: currentColor;
    }
    &__icon--dropdown {
        transform: rotate(180deg);
    }

    &--component-action {
        background-color: transparent;
        font-weight: 600;
        font-size: rem-calc((var(--base-font-size) - 2));
        height: auto;
        line-height: 1.5rem;
        margin: 0;
        padding: var(--spacing-s);
        text-transform: uppercase;
        width: auto;

        &:hover {
            background-color: var(--content-ui-hover);
        }
        &:disabled,
        &.disabled {
            &:hover {
                // don't show a hover if it's disabled
                background-color: transparent;
            }
        }
    }
}

// To match the typical background colour of the dropdown content
// and better visually connect the button to the content
.sbx-button[aria-expanded="true"] {
    // rotate when open
    .sbx-button__icon--dropdown {
        transform: rotate(0deg);
    }
}

/* Styles for printing */
@media print {
    button.sbx-button.sbx-button--icon-only {
        display: none !important;
    }
}
</style>
