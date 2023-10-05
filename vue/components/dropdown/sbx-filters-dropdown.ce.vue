<script setup lang="ts">
/**
 * Used to display a list of filter options availble
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
    /**
     * Whether the button is to be disabled
     */
    isDisabled?: boolean;
}
const props = defineProps<Props>();

const button = window.Vue.ref<HTMLButtonElement | null>(null); // The button that triggers the dropdown display
const { currentLabel, isOpen, onButtonSelected } = usePopOut(
    button as Ref<HTMLButtonElement>,
    "Actions Dropdown",
    props.contentId,
    "bottom-start",
    true
);
</script>

<template>
    <button
        ref="button"
        type="button"
        class="c-button c-button--block c-button--no-bkg c-filters-dropdown"
        :title="currentLabel"
        :aria-label="currentLabel"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        :aria-controls="props.contentId"
        :aria-pressed="isOpen"
        :data-test="`sbx-filters-dropdown-${props.contentId}-button`"
        :disabled="props.isDisabled"
        @click.prevent="onButtonSelected"
    >
        <!-- when an alternative icon should be used -->
        <span v-if="buttonText">{{ buttonText }}</span>
        <slot name="icon">
            <svg
                version="1.2"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 32 32"
                width="16"
                height="16"
                aria-hidden="true"
                focusable="false"
                class="c-icon"
            >
                <path d="M0 24h32l-16-16z"></path>
            </svg>
        </slot>
    </button>
</template>

<style lang="scss" scoped>
// One current complication of using Vue and Web components is the inability to avoid the shadow DOM
// This means that we cannot use the global styles to style the component
// It looks like the ability to opt out of the shadow DOM is hopefully coming soon however
@import "../../../www/static/css/core/ui/button/_c-button.scss";
@import "../../../www/static/css/core/components/dropdowns/_filters-dropdown.scss";
</style>
