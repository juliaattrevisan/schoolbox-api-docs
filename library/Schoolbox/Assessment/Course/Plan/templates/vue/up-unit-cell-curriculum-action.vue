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
    curriculumStore.getComponent(props.unit.curriculum.components, props.column),
);
const isCreating = window.Vue.ref(false);

/**
 * To respond to a new curriculum component being created
 */
window.Vue.watch(
    () => props.unit.curriculum.components,
    () => {
        component.value = curriculumStore.getComponent(props.unit.curriculum.components, props.column);
    },
    { deep: true },
);

/**
 * To display the appropriate text for the create button
 * @returns {string} The text to display on the create button
 */
const createText = window.Vue.computed(() => {
    return isCreating.value ? "Creating..." : "Create Curriculum";
});

/**
 * To create a new curriculum component, adding it to the relevant units homepage
 */
async function onCreateSelected() {
    if (isCreating.value) {
        return;
    }
    isCreating.value = true;
    await curriculumStore.createComponent(props.unit, props.column);
    await window.Vue.nextTick();
    isCreating.value = false;
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
            <button @click.prevent="onCreateSelected">{{ createText }}</button>
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
