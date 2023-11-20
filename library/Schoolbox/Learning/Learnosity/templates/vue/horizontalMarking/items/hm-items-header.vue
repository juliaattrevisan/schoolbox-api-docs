<script setup lang="ts">
/**
 * Items heading display, containing the select item dropdown
 */

import { useItemsStore } from "../store/hmItemsStore";
import HmItemsHeaderSelect from "./hm-items-header-select.vue";

const itemsStore = useItemsStore();
</script>

<template>
    <div class="l-flex-col l-gap-s">
        <h2 class="subheader f-no-margins">Items</h2>
        <div v-if="itemsStore.hasVariedAttempts" class="alert-box f-no-margins">
            This quiz has been modified after attempts have already been submitted. As such the scores for each attempt
            may not match.
        </div>
        <hm-items-header-select />
    </div>
</template>

<style scoped></style>
