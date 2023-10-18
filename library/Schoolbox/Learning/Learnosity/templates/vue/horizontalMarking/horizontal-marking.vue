<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import { useItemsStore } from "./store/hmItemsStore";
import { useStudentsStore } from "./store/hmStudentsStore";
import { useReportsStore } from "./store/hmReportsStore";
import type { Item } from "./types/Item";
import HmSelectItem from "./items/hm-select-item.vue";
import HmStudents from "./students/hm-students.vue";
import HmPreviousItem from "./items/hm-previous-item.vue";
import HmNextItem from "./items/hm-next-item.vue";

interface Props {
    title: string; //
    cards: Item[]; // the cards linked to quiz
    responseScores: object; //
    responseComments: string; //
    urls: object; //
    reportPayloads: string[]; //
    attemptComments: object; //
    students: object; //
}
const props = defineProps<Props>();

/**
 * Initialise the stores, adding the students first, then the reports as
 * when the reports are loaded they look for the students in the store/DOM tree
 */
window.Vue.onMounted(async () => {
    useItemsStore().init(props.cards);
    useStudentsStore().init(props.students);
    await window.Vue.nextTick();
    useReportsStore().init(props.reportPayloads);
});
</script>

<template>
    <div class="l-flex-col l-gap-m f-no-margins">
        <!-- Title. But add the metadata etc. when you get up to that bit -->
        <h1>Horizontal Marking for {{ props.title }}</h1>
        <div>
            <h2 class="subheader">Cards</h2>
            <hm-select-item />
        </div>
        <hm-students />
        <div class="l-flex-row l-flex--no-wrap l-gap-m">
            <hm-previous-item />
            <hm-next-item />
        </div>
    </div>
</template>

<style scoped></style>
