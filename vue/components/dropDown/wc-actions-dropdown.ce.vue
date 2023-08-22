<script setup lang="ts">
/**
 * Used to display a list of actions in a dropdown menu upon clicking a button (the jellytot!)
 */
import { useDropdown } from "./composable/useDropdown";

interface Props {
    contentId: string; // the id of the content to display
    buttonLabel?: string; // the label to display on the button
}
const props = withDefaults(defineProps<Props>(), {
    buttonLabel: "Actions Dropdown",
});

const button = window.Vue.ref<HTMLButtonElement | null>(null); // The button that triggers the dropdown display
const { isOpen, onButtonSelected } = useDropdown(button, props.contentId);

/**
 * The label to display on the button
 */
const currentLabel = window.Vue.computed(() => {
    return isOpen.value ? `Close ${props.buttonLabel}` : `Open ${props.buttonLabel}`;
});
</script>

<template>
    <button
        ref="button"
        type="button"
        class="c-button c-button--icon-only c-button--no-bkg f-match-existing-styles"
        :title="currentLabel"
        :aria-label="currentLabel"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        :aria-controls="props.contentId"
        :aria-pressed="isOpen"
        :data-test="`dropdown-button-${props.contentId}`"
        @click.prevent="onButtonSelected"
    >
        <!-- when an alternative icon should be used -->
        <slot name="icon">
            <svg
                version="1.2"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 16 16"
                width="16"
                height="16"
                aria-hidden="true"
                focusable="false"
                class="c-icon"
            >
                <path d="m6 0h4v4h-4z" />
                <path d="m6 6h4v4h-4z" />
                <path d="m6 12h4v4h-4z" />
            </svg>
        </slot>
    </button>
</template>

<style lang="scss" scoped>
// One current complication of using Vue and Web components is the inability to avoid the shadow DOM
// This means that we cannot use the global styles to style the component
// It looks like the ability to opt out of the shadow DOM is hopefully coming soon however
@import "../../../www/static/css/core/ui/button/_c-button.scss";

// To match the typical background colour of the dropdown content
// and better visually connect the button to the content
button.c-button[aria-expanded="true"] {
    background-color: var(--content-ui-background);
    opacity: 1;
    &:hover,
    &:active {
        background-color: var(--content-ui-background);
    }
    &:focus {
        box-shadow: none;
    }
}

// As we aren't quite ready to introduce the new button styles completely, we add these to match the existing styles as a stop gap
button.f-match-existing-styles {
    height: 42px !important;
    min-height: 42px !important;
    width: 40px !important;
    opacity: 0.7;
    &:hover,
    &:active {
        opacity: 1;
    }
    &:focus {
        box-shadow: 0 0 0 2px currentColor;
    }
}
</style>
