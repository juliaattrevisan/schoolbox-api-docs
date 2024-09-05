<script setup lang="ts">
/**
 * To move between the items
 */

import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";

const store = useStore();
const itemsStore = useItemsStore();

const button = window.Vue.ref<HTMLButtonElement | null>(null); // The button that triggers the dropdown display
const { currentLabel, isOpen, onButtonSelected, close } = usePopOut(
    button,
    "item-list",
    "Actions",
    "bottom-start",
    true,
);

/**
 * The text to display on the button
 * @returns {string} The text to display on the button
 */
const buttonText = window.Vue.computed(() => {
    if (!store.isLoaded) {
        return "Loading items...";
    }
    return itemsStore.activeItem.title;
});

/**
 * To change the active card
 * @param reference The reference of the card to change to
 */
function onItemSelected(reference: string): void {
    close();
    itemsStore.setActiveItem(reference);
}
</script>

<template>
    <button
        ref="button"
        type="button"
        class="select-button"
        :title="currentLabel"
        :aria-label="currentLabel"
        aria-haspopup="menu"
        :aria-expanded="isOpen"
        aria-controls="item-list"
        :aria-pressed="isOpen"
        :disabled="!store.isLoaded || store.isProcessing"
        @click.prevent="onButtonSelected"
    >
        <span>{{ buttonText }}</span>
        <svg
            version="1.2"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 32"
            width="16"
            height="16"
            aria-hidden="true"
            focusable="false"
            class="c-icon c-icon--dropdown"
        >
            <path d="M0 24h32l-16-16z"></path>
        </svg>
    </button>
    <div id="item-list" class="item-list l-flex-col l-flex--no-wrap f-no-margins" data-dropdown-content>
        <button
            v-for="item in itemsStore.items"
            :key="item.id"
            :value="item.reference"
            :class="`item-list__button ${
                itemsStore.activeItem.reference === item.reference ? 'item-list__button--active' : ''
            }`"
            @click.prevent="onItemSelected(item.reference)"
        >
            <span v-if="!item.isMarked && !item.isAttemptComment" class="unsubmitted label"
                >Contains unmarked attempts</span
            >
            <span>
                {{ item.title }}
            </span>
        </button>
    </div>
</template>

<style lang="scss" scoped>
.select-button {
    align-items: center;
    align-self: start; // https://moderncss.dev/css-button-styling-guide/#gotcha-child-of-flex-columns
    background: var(--color-foreground-primary);
    border: 1px solid var(--form-border-color);
    color: var(--color-buttons-text);
    display: inline-flex;
    justify-content: space-between;
    padding: var(--spacing-xs) var(--spacing-m);
    text-align: left;
    width: 100%;
    &:hover,
    &:active {
        &:not([disabled]) {
            background-color: var(--form-bg-hover-color);
        }
    }
    .c-icon--dropdown {
        transform: rotate(180deg);
    }
    &[aria-expanded="true"] {
        // rotate when open
        .c-icon--dropdown {
            transform: rotate(0deg);
        }
    }
}

.item-list {
    background: var(--color-foreground-primary);
    border-left: 1px solid var(--form-border-color);
    border-right: 1px solid var(--form-border-color);
    border-bottom: 1px solid var(--form-border-color);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
    display: none;
    max-height: 250px;
    overflow-y: scroll;
    position: absolute;
    z-index: var(--sbx-depth-pop-over);
    &__button {
        background: var(--color-foreground-primary);
        display: inline-flex;
        justify-content: start;
        gap: var(--spacing-s);
        padding: var(--spacing-s) var(--spacing-m);
        text-align: left;
        width: 100%;
        &:last-child {
            font-weight: bold;
        }
        &:hover {
            background-color: var(--form-bg-hover-color);
        }
        &--active {
            background-color: var(--content-ui-selected);
        }
    }
}
</style>
