<script setup>

/**
 * A selected Curriculum code, it that can be deleted and further details 
 * revealed via a tooltip
 */

import { computed } from 'vue';
import { useSelectedCodesStore } from './store/csSelectedCodesStore';


const props = defineProps({
    /**
     * the curriculum code
     */
    code: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: 'name',
            }
        }
    },
});

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    return `${props.code.breadcrumb}>${props.code.name}`;
});

/**
 * To remove from the selected list
 */
function onRemoveSelected () {
    useSelectedCodesStore().remove(props.code);
}

</script>

<template>
   <div class="selected-code sb-tooltip sb-tooltip--bottom" :title="title">
        <span>{{ props.code.code }}</span>
        <button class="remove-button icon-close" :aria-label="`Remove ${props.code.name}`" @click.prevent="onRemoveSelected" @keyup.enter="onRemoveSelected"></button>
    </div>
</template>

<style lang="scss" scoped>

.selected-code {
    background: var(--body-background);
    color: #000;
    display: flex;
    font-size: 0.8125rem;
    min-height: auto;
    padding: 5px 10px;
    text-align: left;
    transition: all 0.3s;
}

.remove-button {
    background: var(--body-background);
    border-radius: 1rem;
    flex: 0 0 1rem;
    height: 1rem;
    margin-left: 0.5rem;
    min-height: 0;
    margin-top: 3px;
    padding: 0;
}

</style>