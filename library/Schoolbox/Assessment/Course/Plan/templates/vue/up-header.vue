<script setup lang="ts">
/**
 * The Unit planner header, containing the title and functionality to add a new unit
 */

import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";

interface Props {
    courseTitle: string;
}
const props = defineProps<Props>();
const addUnitStore = useAddUnitStore();

/**
 * To show / hide the add unit display
 */
function onCreateNewSelected() {
    if (!addUnitStore.isOpen) {
        addUnitStore.prompt();
    }
}
</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="button-action small-12 columns">
            <h1>Unit Planner - {{ props.courseTitle }}</h1>
            <button
                class="button icon-add"
                data-interaction="add-unit"
                data-test-add-unit
                @click.prevent="onCreateNewSelected"
            >
                Add Unit
            </button>
        </div>
    </div>
</template>

<style scoped></style>
