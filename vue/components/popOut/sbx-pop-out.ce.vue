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

/**
 * Temporary Sonar UI check to update styles as this component is within
 * the Shadow DOM so can't access the .sbx-body class on the parent document
 */
const hasSBXBody = window.Vue.computed(() => {
    return document.body.classList.contains("sbx-body");
});

// To help acceptance tests know when the component is ready and can be interacted with
// keep the data-test attribute off until it is mounted
const isReady = window.Vue.ref(false);
window.Vue.onMounted(() => {
    isReady.value = true;
});
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
            'sbx-body': hasSBXBody,
        }"
        :title="currentLabel"
        :aria-label="currentLabel"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        :aria-controls="props.contentId"
        :aria-pressed="isOpen"
        :data-test="isReady ? `sbx-pop-out-${props.contentId}-button` : undefined"
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

<style scoped>
.sbx-button {
    align-items: center;
    align-self: start; /* https://moderncss.dev/css-button-styling-guide/#gotcha-child-of-flex-columns */
    background: none;
    border: none;
    border-radius: var(--sbx-config-field-roundness, 0);
    color: var(--sbx-config-color-accent, var(--content-ui-foreground));
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
}

.sbx-button:hover:not([disabled]),
.sbx-button:active:not([disabled]) {
    background-color: var(--sbx-config-color-active, var(--content-ui-hover));
    filter: brightness(1.05);
}

.sbx-button:focus:not([disabled]) {
    background-color: var(--sbx-config-color-active, var(--content-ui-background));
}

.sbx-button:disabled {
    opacity: 0.7;
}

.sbx-button:disabled:hover {
    cursor: default;
}

.sbx-button--icon-only {
    padding: var(--spacing-s);
}

.sbx-button--block {
    width: 100%;
}

.sbx-button__icon {
    fill: currentColor;
}

.sbx-button__icon--dropdown {
    transform: rotate(180deg);
}

.sbx-button--component-action {
    background-color: transparent;
    font-weight: 600;
    font-size: calc((1rem) - 2px);
    height: auto;
    line-height: 1.5rem;
    margin: 0;
    padding: var(--spacing-s);
    text-transform: uppercase;
    width: auto;
}

.sbx-button--component-action:hover {
    background-color: var(--sbx-config-color-active, var(--content-ui-hover));
}

.sbx-button--component-action:disabled:hover,
.sbx-button--component-action.disabled:hover {
    /* don't show a hover if it's disabled */
    background-color: transparent;
}

/**
* Override styles for Sonar UI
*/
.sbx-button--component-action.sbx-body {
    background: var(--sbx-color-foreground-primary);
    border: var(--sbx-border-width-normal) solid var(--sbx-color-accent);
    border-radius: var(--sbx-config-field-roundness);
    color: var(--sbx-color-accent);
    font-size: var(--sbx-font-size-s);
    font-weight: var(--sbx-font-weight-normal);
    text-transform: capitalize;
}

.sbx-button--component-action.sbx-body:hover {
    background-color: var(
        --sbx-color-foreground-primary
    ); /* ^foundation-fixes^ - remove the use of --content-ui-hover */
    color: var(--sbx-color-accent); /* ^foundation-fixes^ - remove the change of text colour on hover */
    filter: brightness(1.1);
}

/* To match the typical background colour of the dropdown content
   and better visually connect the button to the content */
.sbx-button[aria-expanded="true"] .sbx-button__icon--dropdown {
    /* rotate when open */
    transform: rotate(0deg);
}

/* Styles for printing */
@media print {
    button.sbx-button.sbx-button--icon-only {
        display: none !important;
    }
}
</style>
