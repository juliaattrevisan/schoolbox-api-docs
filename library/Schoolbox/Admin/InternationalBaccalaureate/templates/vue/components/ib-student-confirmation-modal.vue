<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    students: Pick<StudentRow, "id" | "userId" | "name">[];
    title?: string;
    description: string;
    confirmLabel: string;
    isLoading: boolean;
}

withDefaults(defineProps<Props>(), {
    title: "Please confirm",
});

const isOpen = defineModel<boolean>("isOpen", { default: false });

defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <basic-modal v-model:is-open="isOpen" size="s" :is-close-disabled="isLoading">
        <div class="u-flex-col u-gap-m f-no-margins">
            <h2>{{ title }}</h2>
            <p class="f-no-margins">
                {{ description }}
            </p>

            <div class="sbx-vert-scroll-container u-flex-col u-gap-xs">
                <div
                    v-for="student in students"
                    :key="student.id"
                    class="u-flex u-flex--align-center u-gap-xs f-no-margins"
                >
                    <img
                        :src="`/portrait.php?id=${student.userId}&size=square32`"
                        :alt="student.name"
                        width="32"
                        height="32"
                        class="ib-student-confirmation-modal__portrait"
                    />
                    <span>{{ student.name }}</span>
                </div>
            </div>

            <div class="u-flex u-gap-xs f-no-margins">
                <button type="button" class="button u-flex__item--1" :disabled="isLoading" @click="$emit('cancel')">
                    Cancel
                </button>
                <button
                    type="button"
                    class="button submit u-flex__item--1"
                    :disabled="isLoading"
                    @click="$emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped>
.ib-student-confirmation-modal__portrait {
    border-radius: 50%;
    flex-shrink: 0;
}
</style>
