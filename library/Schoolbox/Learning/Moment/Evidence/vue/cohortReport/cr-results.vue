<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";

const crStore = useCohortReportStore();
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <div v-if="crStore.loadError" class="alert-box alert">
            {{ crStore.loadError }}
        </div>
        <div v-else-if="crStore.isLoading" class="small-12 c-island">
            <section class="content">
                <div class="loader"></div>
            </section>
        </div>

        <div class="small-12 c-island">
            <section class="content">
                {{ crStore.results }}
            </section>
        </div>
    </div>
</template>

<style scoped></style>
