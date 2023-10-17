<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

import { useStudentsStore } from "./store/hmStudentsStore";
import { useReportsStore } from "./store/hmReportsStore";
import HmStudents from "./hmStudents.vue";

interface Props {
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
    <hm-students />
</template>

<style scoped></style>
