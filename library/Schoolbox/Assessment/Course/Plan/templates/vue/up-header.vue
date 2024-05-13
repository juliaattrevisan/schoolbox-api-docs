<script setup lang="ts">
/**
 * The Unit planner header, containing the title and functionality to add a new unit
 * NOTE: We initially had planned to create a new unit directly from the unit planner,
 * but for now this functionality has been removed as we would just be duplicating
 * functionality provided elsewhere without also provided the ability to rename/deleting units as well.
 * The relevant code has been commented out for now as it may be added in the future however.
 */

// import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
// import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";
// import UpAddUnit from "./up-add-unit.vue";

interface Props {
    courseTitle: string;
}
const props = defineProps<Props>();
// const addUnitStore = useAddUnitStore();

/**
 * To show the add unit display
 */
// function onCreateSelected() {
//     if (!addUnitStore.isOpen) {
//         addUnitStore.prompt();
//     }
// }
</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="button-action small-12 columns">
            <h1>Unit Planner - {{ props.courseTitle }}</h1>
            <!--
            <button
                class="button icon-add"
                data-interaction="add-unit"
                data-test-add-unit
                @click.prevent="onCreateSelected"
            >
                Create unit
            </button>
            -->
        </div>
    </div>
    <!--
    <Transition!-- @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <up-add-unit v-if="addUnitStore.isOpen" />
    </Transition!-->
</template>
