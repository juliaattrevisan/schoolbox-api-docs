<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { useLessonPlannerStore } from "./store/lessonPlannerStore";
import type { LessonPlanField } from "./lessonPlannerTypes";
import LpModalGenerate from "./lp-modal-generate.vue";
import LpModalReview from "./lp-modal-review.vue";

interface Props {
    fields: LessonPlanField[];
}

const props = defineProps<Props>();
const lpStore = useLessonPlannerStore();

window.Vue.onMounted(() => {
    lpStore.init(props.fields);
});
</script>

<template>
    <button type="button" class="button expand icon-chat" @click="lpStore.openModal">Generate lesson with AI</button>

    <basic-modal v-model:is-open="lpStore.isModalOpen" :has-background-click="true">
        <section>
            <lp-modal-generate v-if="lpStore.modalStep === 'generate'" />
            <div v-else-if="lpStore.modalStep === 'generating'" class="loader"></div>
            <lp-modal-review v-else-if="lpStore.modalStep === 'review'" />
        </section>
    </basic-modal>
</template>

<style scoped></style>
