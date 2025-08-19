<script setup lang="ts">
/**
 * To display a list of pastoral records in a list format
 */

import type { PastoralRecord } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import PrRecord from "./pr-record.vue";
import PrEmpty from "./pr-empty.vue";

const props = defineProps<{
    results: PastoralRecord[];
}>();
</script>

<template>
    <div v-if="props.results.length > 0" class="l-flex-col l-gap-s">
        <div v-for="record in props.results" :key="record.id" class="c-island">
            <ul class="information-list threaded">
                <pr-record :record="record" />
            </ul>
        </div>
    </div>
    <pr-empty v-else />
</template>

<style scoped></style>
