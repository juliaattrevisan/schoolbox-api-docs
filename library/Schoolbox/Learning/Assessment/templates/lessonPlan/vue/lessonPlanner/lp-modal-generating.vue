<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import LoadingCards from "@schoolboxVue/components/loading/loading-cards.vue";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";

const lpStore = useLessonPlannerStore();

const selectedFields = window.Vue.computed(() => {
    return lpStore.fields.filter((field) => field.selected);
});
</script>

<template>
    <h2>
        Generating
        {{ lpStore.generateForField === null ? locale.lessonPlan.singular() : lpStore.generateForField.title }}...
    </h2>

    <template v-for="(field, i) in selectedFields" :key="field.id">
        <hr v-if="i > 0" />

        <loading-cards :rows="1" :show-image="false">
            <h3></h3>
            <p></p>
            <p></p>
            <p></p>
        </loading-cards>
    </template>
</template>

<style scoped></style>
