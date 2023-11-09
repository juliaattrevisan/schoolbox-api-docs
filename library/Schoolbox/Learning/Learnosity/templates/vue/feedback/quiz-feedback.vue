<script setup lang="ts">
/**
 *
 */

interface Props {
    title: string; // the title of the quiz
    itemResponses: object[]; // StudentItemQuestionResponse[] all the item responses for the quiz, which can include multiple questions within the same item
    attempt: object; // StudentAttemptComment[] all the overall attempt comments for the quiz
    // urls: StudentUrls[]; // the urls to use for the students to update their scores and comments
    reportInitOptions: object; // the report payloads to load from the Learnosity Reports API
    statusPage: string; // the status page to link to once user has finished marking
}
const props = defineProps<Props>();
</script>

<template>
    <div></div>
</template>

<style scoped></style>
