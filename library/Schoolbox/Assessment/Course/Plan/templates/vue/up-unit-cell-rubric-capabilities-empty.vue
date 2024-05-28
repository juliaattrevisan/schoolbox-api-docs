<script setup lang="ts">
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
}

const props = defineProps<Props>();
</script>

<template>
    <i class="icon-criteria"></i>
    <p>
        No Rubric Capabilities have been added to this unit yet,
        <a :href="getReturnUrl(`/rubric/manager/${props.unit.folderId}`)"> start by adding rubric capabilities</a>
    </p>
</template>

<style scoped></style>
