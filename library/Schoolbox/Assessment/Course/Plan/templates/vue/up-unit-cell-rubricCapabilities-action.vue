<script setup lang="ts">
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="component-action">
        <section>
            <a class="button" :href="getReturnUrl(`/rubric/manager/${props.unit.folderId}`)">
                Manage Rubric Capabilities</a
            >
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
