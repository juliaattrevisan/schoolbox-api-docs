<script setup lang="ts">
/**
 * The list of students to display
 */

import { useStudentsStore } from "./store/hmStudentsStore";
import HmStudentsStudent from "./hmStudentsStudent.vue";

const studentsStore = useStudentsStore();
</script>

<template>
    <hm-students-student
        v-for="student in studentsStore.students"
        :key="student.id"
        :student="student"
    ></hm-students-student>
</template>

<style scoped></style>
