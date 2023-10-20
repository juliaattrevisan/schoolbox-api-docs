<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import { useItemsStore } from "./store/hmItemsStore";
import { useStudentsStore } from "./store/hmStudentsStore";
import { loadReports } from "./util/loadReports";
import type { Item } from "./types/Item";
import HmSelectItem from "./items/hm-select-item.vue";
import HmStudents from "./students/hm-students.vue";
import HmPreviousItem from "./items/hm-previous-item.vue";
import HmNextItem from "./items/hm-next-item.vue";

interface Props {
    title: string; //
    items: Item[]; // the items linked to quiz
    responseScores: object; //
    responseComments: string; //
    urls: object; //
    reportPayloads: string[]; //
    attemptComments: object; //
    students: object; //
}
const props = defineProps<Props>();

const hasStudents = window.Vue.computed(() => {
    return props.students && Object.keys(props.students).length > 0;
});

/**
 * Initialise the stores, adding the students first, then the reports as
 * when the reports are loaded they look for the students in the store/DOM tree
 */
window.Vue.onMounted(async () => {
    useItemsStore().init(props.items);
    useStudentsStore().init(props.students);
    await window.Vue.nextTick();
    loadReports(props.reportPayloads);
});
</script>

<template>
    <div class="l-flex-col l-gap-m f-no-margins">
        <!-- Title. But add the metadata etc. when you get up to that bit -->
        <h1>Horizontal Marking for {{ props.title }}</h1>
        <template v-if="hasStudents">
            <div>
                <h2 class="subheader">Cards</h2>
                <hm-select-item />
            </div>
            <hm-students />
            <div class="l-flex-row l-flex--no-wrap l-gap-m">
                <hm-previous-item />
                <hm-next-item />
            </div>
        </template>
        <template v-else>
            <div class="empty-state-flex-centered" data-test="sp-results-prompt">
                <i class="icon-submissions"></i>
                <p>No attempts have been submitted yet.</p>
            </div>
        </template>
    </div>
</template>

<style scoped></style>
