<script setup lang="ts">
/**
 * To view the DP modal, currently only used on the user profile page,
 * hence why we are using the alert-box style
 */

import locale from "@js/terminology/rosettaStone";
import type { User } from "@schoolboxLibrary/types/User";
import ViewDpModal from "@schoolboxLibrary/DifferentiationProfile/templates/vue/view-dp-modal.vue";

const props = defineProps<{
    student: User;
    dpRoute: string;
    customisationsRoute: string;
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
    <dt class="full-width">
        <div
            class="alert-box highlight u-cursor-pointer"
            role="button"
            tabindex="0"
            @click="onViewDP"
            @keydown.enter="onViewDP"
            @keydown.space.prevent="onViewDP"
        >
            <sbx-icon name="learning-support"></sbx-icon>
            View {{ locale.differentiationProfile.translate(false, true) }}
        </div>
    </dt>
    <view-dp-modal
        v-model:student="studentWithDP"
        :dp-route="props.dpRoute"
        :customisations-route="props.customisationsRoute"
    />
</template>

<style scoped></style>
