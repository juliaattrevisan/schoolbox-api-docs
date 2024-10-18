<script setup lang="ts">
/**
 * Displays a single selected result that can be removed
 */

import type { Result } from "./types/Result";

interface Props {
    result: Result; // the selected result
    isSingleSelect: boolean; // whether only one result can be selected
}
const props = defineProps<Props>();

const emit = defineEmits<{
    select: [result: Result];
    remove: [result: Result];
}>();

/**
 * Prevent rapid fire while loading the selected node
 * @returns - whether the button is disabled
 */
const isDisabled = window.Vue.computed(() => false);

/**
 * To trigger an action of the search result, such as opening a modal
 * containing further info on the selected search result
 */
function onTitleSelected(): void {
    emit("select", props.result);
}

/**
 * To remove from the selected list
 */
function onRemoveSelected(): void {
    emit("remove", props.result);
}
</script>

<template>
    <div
        :class="`selected-item l-flex-row l-flex--align-center l-pad-s l-gap-s ${
            props.isSingleSelect ? 'l-flex__item--1' : ''
        }`"
        :data-test="`search-select-selected-item-container-${props.result.id}`"
    >
        <button
            type="button"
            :class="`selected-item__name-button ${props.isSingleSelect ? 'l-flex__item--1' : ''}`"
            :title="`View ${props.result.name}`"
            :aria-label="`View ${props.result.name}`"
            :disabled="isDisabled"
            @click.prevent="onTitleSelected"
        >
            {{ props.result.name }}
        </button>
        <button
            type="button"
            class="selected-item__remove-button icon-close"
            :title="`Remove ${props.result.name}`"
            :aria-label="`Remove ${props.result.name}`"
            :data-test="`search-select-selected-item-remove-${props.result.id}`"
            @click.prevent="onRemoveSelected"
        ></button>
    </div>
</template>

<style lang="scss" scoped>
.selected-item {
    background: var(--body-background);
    color: #000;
    font-size: 0.8125rem;
    min-height: auto;
    overflow: hidden;
    text-align: left;
    white-space: nowrap;
    &__name-button {
        background: none;
        margin: 0;
        min-height: 0;
        padding: 0;
        text-align: left;
        text-overflow: ellipsis;
    }
    &__remove-button {
        background: none;
        height: 1rem;
        min-height: 0;
        padding: 0;
    }
}
</style>
