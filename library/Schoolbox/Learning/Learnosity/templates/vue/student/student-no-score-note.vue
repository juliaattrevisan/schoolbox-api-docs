<script setup lang="ts">
/**
 * To notify that the current item contains questions with no score, so adding a comment will mark the item
 */

import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";

interface Props {
    item: StudentItem; // the item that's displayed
}
const props = defineProps<Props>();

const hasQuestionsWithNoScore = props.item.questions.some(
    (question) => question.response && question.response.maxScore === null,
);
</script>

<template>
    <div v-if="hasQuestionsWithNoScore">
        <div class="alert-box f-no-margins">
            Any questions within an item that don't have a score are considered marked when a comment is added to the
            item.
        </div>
    </div>
</template>

<style scoped></style>
