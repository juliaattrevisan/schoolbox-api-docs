<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * A selected Curriculum code, it can be deleted and
 * further details revealed via a modal
 */

import { useSearchStore } from "../store/mgSearchStore";

const props = defineProps({
    /**
     * the selected search result
     */
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: "",
                fromType: "",
            };
        },
    },
});

const searchStore = useSearchStore();

/**
 * To remove from the selected list
 */
function onRemoveSelected() {
    searchStore.removeResult(props.result);
}
</script>

<template>
    <div class="selected-code">
        <button class="title-button" :title="`View ${props.result.name}`">
            {{ props.result.name }}
        </button>
        <button
            class="remove-button icon-close"
            :title="`Remove ${props.result.name}`"
            @click.prevent="onRemoveSelected"
        ></button>
    </div>
</template>

<style lang="scss" scoped>
.selected-code {
    background: var(--body-background);
    color: #000;
    display: flex;
    font-size: 0.8125rem;
    gap: 0.5rem;
    min-height: auto;
    padding: 5px 10px;
    text-align: left;
    transition: all 0.3s;
}

.title-button {
    background: var(--body-background);
    margin: 0;
    min-height: 0;
    padding: 0;
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
