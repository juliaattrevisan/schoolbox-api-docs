<script setup lang="ts">
/**
 * The list of students to display
 */

import { useStudentsStore } from "./store/hmStudentsStore";
import HmStudentsStudent from "./hm-students-student.vue";

const studentsStore = useStudentsStore();
</script>

<template>
    <div>
        <hm-students-student
            v-for="student in studentsStore.students"
            :key="student.id"
            :student="student"
        ></hm-students-student>
    </div>
</template>

<style scoped></style>
