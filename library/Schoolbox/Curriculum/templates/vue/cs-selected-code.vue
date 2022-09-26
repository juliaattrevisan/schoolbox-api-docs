<script setup>

/**
 * A selected Curriculum code, it can be deleted and
 * further details revealed via a modal
 */

import { computed } from 'vue';
import { useStore } from './store/curriculumSelectorStore';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';


const props = defineProps({
    /**
     * the selected curriculum code
     */
    code: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                code: '',
                name: '',
                breadcrumb: '',
            }
        }
    },
});

const store = useStore();
const selectedCodesStore = useSelectedCodesStore();

/**
 * Prevent rapid fire while loading the the selected node
 *
 * @returns { boolean }
 */
const isDisabled = computed(() => selectedCodesStore.isLoading);

/**
 * To open a modal that shows further info on the selected code
 */
function onTitleSelected () {
    selectedCodesStore.setActiveCode(props.code);
}

/**
 * To remove from the selected list
 */
function onRemoveSelected () {
    selectedCodesStore.remove(props.code);
}

</script>

<template>
   <div class="selected-code">
        <button
            class="title-button"
            :title="`View ${props.code.name}`"
            :disabled="isDisabled"
            @click.prevent="onTitleSelected"
            @keyup.enter="onTitleSelected"
            data-test-title-button>
            {{ props.code.code }}
        </button>
        <button
            v-if="!store.isReadOnly"
            class="remove-button icon-close"
            :title="`Remove ${props.code.name}`"
            @click.prevent="onRemoveSelected"
            @keyup.enter="onRemoveSelected"
            data-test-remove-button>
        </button>
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