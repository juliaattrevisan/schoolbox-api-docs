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
        <ul class="option-list l-mar--0">
            <li class="f-no-margins">
                <input
                    id="show-marked-attempts"
                    v-model="studentsStore.showMarkedAttempts"
                    type="checkbox"
                    :disabled="!store.isLoaded"
                /><label for="show-marked-attempts">Show marked attempts</label>
            </li>
        </ul>
    </div>
</template>
