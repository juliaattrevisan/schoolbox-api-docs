<script lang="ts" setup>
import { useCohortReportStore } from "./store/cohortReportStore";

const crStore = useCohortReportStore();
</script>

<template>
    <div class="l-flex-col l-gap-m">
        <div class="small-12 c-island">
            <section class="content">
                <div v-if="crStore.isLoading" class="loader"></div>
                <div v-else-if="crStore.loadError" class="alert-box alert">
                    {{ crStore.loadError }}
                </div>
                <template v-else>
                    {{ crStore.results }}
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped></style>
