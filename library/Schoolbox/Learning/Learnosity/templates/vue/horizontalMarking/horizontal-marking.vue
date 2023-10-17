<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import { useCardsStore } from "./store/hmCardsStore";
import { useStudentsStore } from "./store/hmStudentsStore";
import { useReportsStore } from "./store/hmReportsStore";
import type { Card } from "./types/Card";
import HmStudents from "./hm-students.vue";

interface Props {
    title: string; //
    cards: Card[]; // the cards linked to quiz
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
    useCardsStore().cards = props.cards;
    useStudentsStore().init(props.students);
    await window.Vue.nextTick();
    useReportsStore().init(props.reportPayloads);
});
</script>

<template>
    <div class="l-flex-col">
        <!-- Title. But add the metadata etc. when you get up to that bit -->
        <h1>Horizontal Marking for {{ props.title }}</h1>
        <hm-students />
    </div>
</template>

<style scoped></style>
