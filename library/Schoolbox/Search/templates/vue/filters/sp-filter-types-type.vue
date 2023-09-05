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
</script>

<template>
    <li>
        <input
            :id="props.type.value"
            v-model="filterTypesStore.activeTypes"
            type="checkbox"
            :value="props.type.value"
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
