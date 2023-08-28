<script setup lang="ts">
/**
 * Displays a single selected result that can be removed
 */

import type { Result } from "./types/Result";

interface Props {
    result: Result; // the selected result
}
const props = defineProps<Props>();

const emit = defineEmits<{
    remove: [result: Result];
}>();

/**
 * Prevent rapid fire while loading the selected node
 * @returns {boolean}
 */
const isDisabled = window.Vue.computed(() => false);

/**
 * To trigger an action of the search result, such as opening a modal
 * containing further info on the selected search result
 */
function onTitleSelected() {
    //
}

/**
 * To remove from the selected list
 */
function onRemoveSelected() {
    emit("remove", props.result);
}
</script>

<template>
    <div class="selected-node">
        <button
            type="button"
            class="title-button"
            :title="`View ${props.result.name}`"
            :aria-label="`View ${props.result.name}`"
            :disabled="isDisabled"
            @click.prevent="onTitleSelected"
        >
            {{ props.result.name }}
        </button>
        <button
            type="button"
            class="remove-button icon-close"
            :title="`Remove ${props.result.name}`"
            :aria-label="`Remove ${props.result.name}`"
            @click.prevent="onRemoveSelected"
        ></button>
    </div>
</template>

<style lang="scss" scoped>
.selected-node {
    background: var(--body-background);
    color: #000;
    display: flex;
    font-size: 0.8125rem;
    gap: 0.5rem;
    min-height: auto;
    overflow: hidden;
    padding: 5px 10px;
    text-align: left;
    transition: all 0.3s;
    white-space: nowrap;
}

.title-button {
    background: var(--body-background);
    margin: 0;
    min-height: 0;
    padding: 0;
    text-overflow: ellipsis;
}

.remove-button {
    background: var(--body-background);
    border-radius: 1rem;
    flex: 0 0 1rem;
    height: 1rem;
    min-height: 0;
    margin-top: 3px;
    padding: 0;
}
</style>
