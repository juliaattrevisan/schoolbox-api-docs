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

interface Props {
    routeCurriculumCoverage: string | null;
}
const props = defineProps<Props>();
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

const buttonCount = window.Vue.computed(() => {
    let count = 0;
    if (props.routeCurriculumCoverage) count += 1;
    if (!store.isReadOnly) count += 1;
    return count;
});
</script>

<template>
    <div :class="`row ${buttonCount > 1 ? '' : `actions-small-${buttonCount} actions-medium-${buttonCount}`}`">
        <div class="list-item">
            <div class="small-12 column">
                <h1>Manage Curriculum</h1>
            </div>
        </div>
        <template v-if="store.hasLoadedOk && !store.isReadOnly">
            <nav>
                <a
                    v-if="props.routeCurriculumCoverage"
                    :href="props.routeCurriculumCoverage"
                    class="icon-report"
                    title="View Curriculum Coverage Report"
                ></a>
                <a
                    href="#create-curriculum"
                    data-interaction="create-curriculum"
                    data-test="toggle-create-curriculum"
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
