<script setup lang="ts">
/**
 * To display the actions available for the curriculum overview component,
 * which is to modify the selected nodes via the curriculum selector modal
 */

import FnActions from "@schoolboxCurriculum/vue/folderNodes/fn-actions.vue";
import { useUnitPlannerCurriculumStore } from "./store/unitPlannerCurriculumStore";
import type { CurriculumOverviewComponent, Unit } from "./unitPlannerTypes";
import type { Column } from "./unitPlannerConfig";

interface Props {
    unit: Unit; // the unit linked to the curriculum cell
    column: Column; // the column linked to the curriculum cell
}
const props = defineProps<Props>();

const curriculumStore = useUnitPlannerCurriculumStore();
const component = window.Vue.ref<CurriculumOverviewComponent | null>(
    curriculumStore.getCurriculumComponent(props.unit.curriculum.components, props.column),
);

/**
 * To create a new curriculum component, adding it to the relevant units homepage
 */
async function onCreateSelected() {
    await curriculumStore.createCurriculumComponent(props.unit, props.column);
    component.value = curriculumStore.getCurriculumComponent(props.unit.curriculum.components, props.column);
}

/**
 * To open the curriculum code mapping modal,
 * ensuring the relevant selected nodes are displayed by
 * passing in the folderId and componentId to target the appropriate folderNodesStore
 */
function onModifySelected() {
    curriculumStore.openCurriculumSelectorModal(
        props.unit.folderId,
        (component.value as CurriculumOverviewComponent).componentId,
    );
}
</script>

<template>
    <div v-if="component === null" class="component-action">
        <section>
            <button @click.prevent="onCreateSelected">Create Curriculum</button>
        </section>
    </div>
    <fn-actions
        v-else
        :folder-id="props.unit.folderId"
        :component-id="component.componentId"
        @modify-selected="onModifySelected"
    />
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all; /* to allow the button to sit above the overlay */
}
</style>
