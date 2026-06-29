<script setup lang="ts">
/**
 * Items heading display, containing the select item dropdown
 */

import { useItemsStore } from "../store/hmItemsStore";
import HmItemsHeaderSelect from "./hm-items-header-select.vue";

const itemsStore = useItemsStore();
</script>

<template>
    <div class="u-flex-col l-gap-s">
        <h2 class="subheader f-no-margins">Items</h2>
        <div v-if="itemsStore.hasVariedAttempts" class="alert-box warning f-no-margins">
            This quiz has been modified after attempts have already been submitted. As such the scores for each attempt
            may not match. You may wish to notify those students who submitted an attempt prior to the quiz modification
            to resubmit.
        </div>
        <hm-items-header-select />
    </div>
</template>

<style scoped></style>
