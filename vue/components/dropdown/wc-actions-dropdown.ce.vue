<script setup lang="ts">
/**
 * Used to display a list of actions in a dropdown menu upon clicking a button (the jellytot!)
 */
import type { Ref } from "vue";
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
}
const props = defineProps<Props>();

const button = window.Vue.ref<HTMLButtonElement | null>(null); // The button that triggers the dropdown display
const { currentLabel, isOpen, onButtonSelected } = usePopOut(
    button as Ref<HTMLButtonElement>,
    "Actions Dropdown",
    props.contentId
);
</script>

<template>
    <button
        ref="button"
        type="button"
        :class="`c-button c-button--no-bkg ${props.buttonText ? `` : `c-button--icon-only f-match-existing-styles`}`"
        :title="currentLabel"
        :aria-label="currentLabel"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        :aria-controls="props.contentId"
        :aria-pressed="isOpen"
        data-test="wc-actions-dropdown-button"
        @click.prevent="onButtonSelected"
    >
        <!-- when an alternative icon should be used -->
        <span v-if="buttonText">{{ buttonText }}</span>
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
@import "../../../www/static/css/core/components/dropdowns/_actions-dropdown.scss";
</style>
