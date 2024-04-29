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
    if (addUnitStore.isOpen) {
        addUnitStore.cancel();
    } else {
        addUnitStore.prompt();
    }
}
</script>

<template>
    <div class="row actions-small-1 actions-medium-2">
        <div class="list-item">
            <div class="small-12 column">
                <h1>Unit Planner - {{ props.courseTitle }}</h1>
            </div>
        </div>
        <!-- <template v-if="store.hasLoadedOk && !store.isReadOnly"> -->
        <nav>
            <a
                href="#create-curriculum"
                data-interaction="create-curriculum"
                data-test-toggle-create-curriculum
                :class="`icon ${addUnitStore.isOpen ? 'icon-close' : 'icon-add'}`"
                title="Create New Unit"
                @click.prevent="onCreateNewSelected"
            >
            </a>
        </nav>
        <!-- </template> -->
    </div>
</template>

<style scoped></style>
