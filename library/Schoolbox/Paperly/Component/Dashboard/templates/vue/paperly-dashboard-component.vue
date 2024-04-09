<script setup lang="ts">
/**
 * Paperly Dashboard Component
 * Allows users to access forms relevant to their role and
 * view upcoming tasks that require action
 */

import { payload } from "./tempPayload";
import type { PaperlyPayload } from "../js/types/PaperlyPayload";
import PdcUpcomingItem from "./pdc-upcoming-item.vue";

const tempPayload = window.Vue.ref<PaperlyPayload>(payload);
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <div class="l-flex-col">
                <pdc-upcoming-item v-for="item in tempPayload.upcoming" :key="item.url" :item="item" />
            </div>
        </div>
    </div>
</template>

<style scoped></style>
