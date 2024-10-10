<script setup lang="ts">
/**
 * A filter type
 */

import type { FilterType } from "./store/spFilterTypesStore";
import { useFilterTypesStore } from "./store/spFilterTypesStore";

interface Props {
    type: FilterType; // the filter type to display
}
const props = defineProps<Props>();

const filterTypesStore = useFilterTypesStore();
const isOpen = window.Vue.ref(false); // whether the sub type are shown

/**
 * To show/hide the sub types when selected
 */
function onShowSubTypesSelected(): void {
    isOpen.value = !isOpen.value;
}

/**
 * To show/hide the sub types when the all sub types button is clicked
 */
window.Vue.watch(
    () => filterTypesStore.isExpanded,
    (newValue) => {
        isOpen.value = newValue;
    },
);

/**
 * To show/hide the sub types container
 * @returns {string} - the height of the sub types container
 */
const subTypeHeight = window.Vue.computed(() => {
    return isOpen.value ? "1fr" : "0fr";
});

/**
 * To add/remove the sub types when the parent type is selected/deselected
 * And to also add the parent type when all the sub types are selected
 */
window.Vue.watch(
    () => filterTypesStore.selectedTypes,
    (newSelectedTypes, oldSelectedTypes) => {
        if (!props.type.subTypes) {
            return;
        }
        // if the parent type has just been added, add all the sub types
        if (newSelectedTypes.includes(props.type.value) && !oldSelectedTypes.includes(props.type.value)) {
            filterTypesStore.addSubTypes(props.type);
            return;
        }
        // if the parent type has just been removed, remove all the sub types
        if (!newSelectedTypes.includes(props.type.value) && oldSelectedTypes.includes(props.type.value)) {
            filterTypesStore.removeSubTypes(props.type);
            return;
        }
        // if all of the sub types are selected, select the parent type
        if (props.type.subTypes.every((subType) => newSelectedTypes.includes(subType.value))) {
            filterTypesStore.addType(props.type);
            return;
        }
        // if the parent is selected, and none of the sub types are selected, deselect the parent
        if (
            newSelectedTypes.includes(props.type.value) &&
            props.type.subTypes.every((subType) => !newSelectedTypes.includes(subType.value))
        ) {
            filterTypesStore.removeType(props.type);
        }
    },
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
    const activeSubTypes = filterTypesStore.selectedTypes.filter((activeType) => {
        return props.type.subTypes?.some((subType) => activeType === subType.value);
    });
    // if some but not all sub types are selected, return true
    return activeSubTypes.length >= 1 && activeSubTypes.length !== props.type.subTypes.length;
});
</script>

<template>
    <li class="l-flex-col f-min-btn-height">
        <div class="l-flex-row l-flex--align-center f-min-btn-height f-no-margins">
            <div class="l-flex__item--1">
                <input
                    :id="props.type.value"
                    v-model="filterTypesStore.selectedTypes"
                    type="checkbox"
                    :value="props.type.value"
                    :class="{ indeterminate: isSomeSubTypesSelected }"
                />
                <label
                    :for="props.type.value"
                    class="f-no-margins"
                    :data-test="`sp-filter-type-label-${props.type.value}`"
                    >{{ props.type.title }}</label
                >
            </div>
            <button
                v-if="props.type.subTypes"
                :class="`button ${isOpen ? 'icon-up-arrow' : 'icon-down-arrow'} f-center-icon f-no-bkg`"
                title="Expand/collapse content"
                @click.prevent="onShowSubTypesSelected"
            ></button>
        </div>
        <div v-if="props.type.subTypes" class="sub-types" :style="{ 'grid-template-rows': subTypeHeight }">
            <ul>
                <li v-for="subType in props.type.subTypes" :key="subType.value" class="f-min-btn-height">
                    <div class="l-flex-row l-flex--align-center f-min-btn-height f-no-margins">
                        <input
                            :id="subType.value"
                            v-model="filterTypesStore.selectedTypes"
                            type="checkbox"
                            :value="subType.value"
                        />
                        <label :for="subType.value">{{ subType.title }}</label>
                    </div>
                </li>
            </ul>
        </div>
    </li>
</template>

<style scoped>
.sub-types {
    display: grid;
    transition: grid-template-rows 300ms;
}
.sub-types ul {
    overflow: hidden;
}
</style>
