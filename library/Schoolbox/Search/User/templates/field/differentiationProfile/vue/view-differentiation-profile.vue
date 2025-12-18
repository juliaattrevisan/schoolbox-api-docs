<script setup lang="ts">
/**
 * To view the DP modal
 */

import locale from "@js/terminology/rosettaStone";
import type { User } from "@schoolboxLibrary/types/User";
import ViewDpModal from "@schoolboxLibrary/DifferentiationProfile/templates/vue/view-dp-modal.vue";

const props = defineProps<{
    student: User;
    route: string;
}>();

const studentWithDP = window.Vue.ref<User | null>(null);

/**
 * Display the differentiation profile for a student
 */
function onViewDP(): void {
    studentWithDP.value = props.student;
}
</script>

<template>
    <dt class="full-width l-flex-row">
        <button class="button l-flex__item--1-auto icon-learning-support" @click="onViewDP">
            View {{ locale.differentiationProfile.translate(false, true) }}
        </button>
    </dt>
    <view-dp-modal v-model:student="studentWithDP" :route="props.route" />
</template>

<style scoped></style>
