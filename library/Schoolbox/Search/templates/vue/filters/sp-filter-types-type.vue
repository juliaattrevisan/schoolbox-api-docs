<script setup lang="ts">
/**
 * A filter type
 */

import { useFilterTypesStore, FilterType } from "./store/spFilterTypesStore";

interface Props {
    type: FilterType; // the filter type to display
}
const props = defineProps<Props>();

const filterTypesStore = useFilterTypesStore();

/**
 * To add/remove the sub types when the parent type is selected/deselected
 * And to also add the parent type when all the sub types are selected
 */
window.Vue.watch(
    () => filterTypesStore.activeTypes,
    (newActiveTypes, oldActiveTypes) => {
        if (!props.type.subTypes) {
            return;
        }
        // if the parent type has just been added, add all the sub types
        if (newActiveTypes.includes(props.type.value) && !oldActiveTypes.includes(props.type.value)) {
            filterTypesStore.addSubTypes(props.type);
            return;
        }
        // if the parent type has just been removed, remove all the sub types
        if (!newActiveTypes.includes(props.type.value) && oldActiveTypes.includes(props.type.value)) {
            filterTypesStore.removeSubTypes(props.type);
            return;
        }
        // if all of the sub types are selected, select the parent type
        if (props.type.subTypes.every((subType) => newActiveTypes.includes(subType.value))) {
            filterTypesStore.addType(props.type);
        }
    }
);

/**
 * To add the 'indeterminate' class to the parent type
 * checkbox if some but not all sub types are selected
 * @returns {boolean} - whether some but not all sub types are selected
 */
const isSomeSubTypesSelected = window.Vue.computed(() => {
    if (!props.type.subTypes) {
        return false;
    }
    // return the sub types that are selected
    const activeSubTypes = filterTypesStore.activeTypes.filter((activeType) => {
        return props.type.subTypes?.some((subType) => activeType === subType.value);
    });
    // if some but not all sub types are selected, return true
    return activeSubTypes.length >= 1 && activeSubTypes.length !== props.type.subTypes.length;
});
</script>

<template>
    <li>
        <input
            :id="props.type.value"
            v-model="filterTypesStore.activeTypes"
            type="checkbox"
            :value="props.type.value"
            :class="{ indeterminate: isSomeSubTypesSelected }"
        />
        <label :for="props.type.value">{{ props.type.title }}</label>
        <ul v-if="props.type.subTypes">
            <li v-for="subType in props.type.subTypes" :key="subType.value">
                <input
                    :id="subType.value"
                    v-model="filterTypesStore.activeTypes"
                    type="checkbox"
                    :value="subType.value"
                />
                <label :for="subType.value">{{ subType.title }}</label>
            </li>
        </ul>
    </li>
</template>

<style scoped></style>
