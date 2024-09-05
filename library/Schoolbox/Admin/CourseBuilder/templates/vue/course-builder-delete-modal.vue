<script setup lang="ts">
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import { useCourseBuilderStore } from "./store/courseBuilderStore";

const store = useCourseBuilderStore();

/**
 * Trigger the deletetion of the selected course template
 */
async function triggerDelete(): Promise<void> {
    if (store.courseTemplateToDelete !== null) {
        await store.deleteCourseTemplate(store.courseTemplateToDelete);
    }
}
</script>

<template>
    <sequential-reveal-modal
        size="medium"
        :is-open="store.courseTemplateToDelete !== null"
        @closed="store.courseTemplateToDelete = null"
    >
        <template #sequence-1>
            <h2>Please Confirm</h2>
            <p>Are you sure you want to delete this template?</p>
            <ul class="flex-list buttons">
                <li>
                    <a href="#" class="button submit" @click.prevent="triggerDelete()">Delete</a>
                </li>
                <li>
                    <a href="#" class="button" @click.prevent="store.courseTemplateToDelete = null">Cancel</a>
                </li>
            </ul>
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
