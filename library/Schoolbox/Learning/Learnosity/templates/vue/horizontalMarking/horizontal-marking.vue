<script setup lang="ts">
/**
 * To use horizontal marking when assessing a quiz
 */

interface Props {
    responseScores: object; //
    responseComments: string; //
    urls: object; //
    reportPayloads: object; //
    attemptComments: object; //
    users: object; //
}
const props = defineProps<Props>();
</script>

<template>
    <div>HM</div>
</template>

<style scoped></style>
