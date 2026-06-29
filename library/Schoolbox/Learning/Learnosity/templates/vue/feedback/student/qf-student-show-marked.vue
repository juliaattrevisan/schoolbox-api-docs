<script setup lang="ts">
/**
 * Show/hide only those items that have been marked
 */

import { useStore } from "../store/quizFeedbackStore";
import { useStudentStore } from "../store/qfStudentStore";

const store = useStore();
const studentStore = useStudentStore();
</script>

<template>
    <div class="checklist f-no-margins">
        <ul class="option-list u-mar--0">
            <li class="f-no-margins">
                <input
                    id="show-marked-attempts"
                    v-model="studentStore.student.showMarked"
                    type="checkbox"
                    :disabled="!store.isLoaded"
                /><label for="show-marked-attempts">Show marked items</label>
            </li>
        </ul>
    </div>
</template>
