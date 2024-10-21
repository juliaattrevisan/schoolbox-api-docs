<script setup lang="ts">
/**
 *
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useStore } from "./store/manageCurriculumStore";
import { useAddCurriculumStore } from "./store/mcAddCurriculumStore";
import McAddCurriculum from "./mc-add-curriculum.vue";

const store = useStore();
const addCurriculumStore = useAddCurriculumStore();

/**
 * To show / hide the add curriculum display
 */
function onCreateNewSelected(): void {
    if (addCurriculumStore.isOpen) {
        addCurriculumStore.cancel();
    } else {
        addCurriculumStore.prompt();
    }
}
</script>

<template>
    <div :class="`row ${store.isReadOnly ? '' : 'actions-small-2 actions-medium-2'}`">
        <div class="list-item">
            <div class="small-12 column">
                <h1>Manage Curriculum</h1>
            </div>
        </div>
        <template v-if="store.hasLoadedOk && !store.isReadOnly">
            <nav>
                <a href="/curriculum/reports/coverage" class="icon-report" title="View Curriculum Coverage Report"> </a>
                <a
                    href="#create-curriculum"
                    data-interaction="create-curriculum"
                    data-test-toggle-create-curriculum
                    :class="`icon ${addCurriculumStore.isOpen ? 'icon-close' : 'icon-add'}`"
                    title="Create New Curriculum"
                    @click.prevent="onCreateNewSelected"
                >
                </a>
            </nav>
        </template>
    </div>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <mc-add-curriculum v-if="addCurriculumStore.isOpen" />
    </Transition>
</template>

<style scoped></style>
