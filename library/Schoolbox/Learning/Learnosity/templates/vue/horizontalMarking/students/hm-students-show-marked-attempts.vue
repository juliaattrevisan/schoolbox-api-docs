<script setup lang="ts">
/**
 * Show/hide only those students that have been marked on the current item
 */

import { useStore } from "../store/horizontalMarkingStore";
import { useItemsStore } from "../store/hmItemsStore";

const store = useStore();
const itemsStore = useItemsStore();
</script>

<template>
    <div class="checklist">
        <ul class="option-list l-mar--0">
            <li>
                <input id="show-marked-attempts" v-model="itemsStore.showMarkedAttempts" type="checkbox" /><label
                    for="show-marked-attempts"
                    >Show marked attempts</label
                >
            </li>
        </ul>
    </div>
</template>

<style scoped></style>
