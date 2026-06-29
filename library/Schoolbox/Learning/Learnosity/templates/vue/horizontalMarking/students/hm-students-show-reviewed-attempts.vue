<script setup lang="ts">
/**
 * Show/hide only those students that have been marked on the current item
 */

import { useStore } from "../store/horizontalMarkingStore";
import { useStudentsStore } from "../store/hmStudentsStore";

const store = useStore();
const studentsStore = useStudentsStore();
</script>

<template>
    <div class="checklist f-no-margins">
        <ul class="option-list u-mar--0">
            <li class="f-no-margins">
                <input
                    id="show-reviewed-attempts"
                    v-model="studentsStore.showReviewedAttempts"
                    type="checkbox"
                    :disabled="!store.isLoaded"
                /><label for="show-reviewed-attempts">Show reviewed attempts</label>
            </li>
        </ul>
    </div>
</template>
