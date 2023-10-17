<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import { useStudentsStore } from "./store/hmStudentsStore";
import { useReportsStore } from "./store/hmReportsStore";
import HmStudents from "./hm-students.vue";

interface Props {
    title: string; //
    responseScores: object; //
    responseComments: string; //
    urls: object; //
    reportPayloads: string[]; //
    attemptComments: object; //
    students: object; //
}
const props = defineProps<Props>();

useStudentsStore().init(props.students);
useReportsStore().init(props.reportPayloads);
</script>

<template>
    <div class="l-flex-col">
        <!-- Title. But add the metadata etc. when you get up to that bit -->
        <h1>Horizontal Marking for {{ props.title }}</h1>
        <hm-students />
    </div>
</template>

<style scoped></style>
