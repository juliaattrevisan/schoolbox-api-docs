<script setup>

/**
 * to select a code within the current modal session
 */

import { computed } from 'vue';
import { generateUUID } from '@js/util/jsUtils';
import { UNSET_ID } from '../store/csCodesStore';
import { useModalSelectedCodesStore } from '../store/csModalSelectedCodesStore';


const modalSelectedCodesStore = useModalSelectedCodesStore();
const extraId = generateUUID(); //to ensure the input/label id is unique

const props = defineProps({
    /**
     * the assigned node to display
     */
    code: {
        type: Object,
        required: true,
        default() {
            return {
                id: '0',
                name: '-',
                children: [],
            }
        }
    },
});

/**
 * Dont add a checklist on the very first level node
 *
 * @returns { Boolean }
 */
const isIdSet = computed(() => props.code.id !== UNSET_ID);

/**
 * If there's a code available, which means it can be selected
 *
 * @returns { Boolean }
 */
const hasCode = computed(() => props.code.code && props.code.code !== '');

/**
 * Only show the checkbox if the node has an ide and a code
 *
 * @returns { Boolean }
 */
const isVisible = computed(() => isIdSet.value && hasCode.value);

/**
 * Update the status of the checkbox based on whether it's selected or not
 *
 * @returns { Boolean }
 */
const isChecked = computed(() => modalSelectedCodesStore.isSelected(props.code));

/**
 * add or remove based on the current status
 *
 * @param { boolean }
 */
function onChange (isChecked) {
    modalSelectedCodesStore.switch(props.code, isChecked);
}

</script>

<template>
    <div v-if="isVisible" class="checklist">
        <input
            :id="`checkbox-${props.code.id}__${extraId}`"
            type="checkbox"
            :value="props.code.id"
            :checked="isChecked"
            @change="onChange($event.target.checked)"
        />
        <label :for="`checkbox-${props.code.id}__${extraId}`"></label>
    </div>
</template>

<style lang="scss" scoped>

.checklist {
    flex: 2rem 0 0; //ensure a long name doesn't impact the checkbox width, which may add dodgy scroll bars on it
    padding-top: 0.5rem;
}

</style>