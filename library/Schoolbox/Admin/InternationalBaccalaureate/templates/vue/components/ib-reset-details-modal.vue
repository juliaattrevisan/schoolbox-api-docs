<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { StudentRow } from "../../js/types/ibReportTypes";

interface Props {
    students: Pick<StudentRow, "id" | "userId" | "name">[];
    isLoading: boolean;
}
defineProps<Props>();

const isOpen = defineModel<boolean>("isOpen", { default: false });

defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <basic-modal v-model:is-open="isOpen" size="s" :is-close-disabled="isLoading">
        <div class="l-flex-col l-gap-m f-no-margins">
            <h2>Please confirm</h2>
            <p class="f-no-margins">
                Resetting will clear all registration details and move the selected student(s) back to Preparation.
            </p>

            <div class="sbx-vert-scroll-container l-flex-col l-gap-xs">
                <div
                    v-for="student in students"
                    :key="student.id"
                    class="l-flex l-flex--align-center l-gap-xs f-no-margins"
                >
                    <img
                        :src="`/portrait.php?id=${student.userId}&size=square32`"
                        :alt="student.name"
                        width="32"
                        height="32"
                        class="ib-reset-modal__portrait"
                    />
                    <span>{{ student.name }}</span>
                </div>
            </div>

            <div class="l-flex l-gap-xs f-no-margins">
                <button type="button" class="button l-flex__item--1" :disabled="isLoading" @click="$emit('cancel')">
                    Cancel
                </button>
                <button
                    type="button"
                    class="button submit l-flex__item--1"
                    :disabled="isLoading"
                    @click="$emit('confirm')"
                >
                    Reset all details
                </button>
            </div>
        </div>
    </basic-modal>
</template>

<style scoped>
.ib-reset-modal__portrait {
    border-radius: 50%;
    flex-shrink: 0;
}
</style>
