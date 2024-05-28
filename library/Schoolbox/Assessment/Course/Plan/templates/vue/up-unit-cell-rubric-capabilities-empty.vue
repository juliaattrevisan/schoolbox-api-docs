<script setup lang="ts">
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { useUnitPlannerStore } from "./store/unitPlannerStore";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();
</script>

<template>
    <i class="icon-criteria"></i>
    <p>
        No rubric {{ store.rubricCapabilitiesLocale?.plural ?? "capabilities" }} have been added to this unit yet,
        <a :href="getReturnUrl(`/rubric/manager/${props.unit.folderId}`)">
            start by adding rubric {{ store.rubricCapabilitiesLocale?.plural ?? "capabilities" }}</a
        >
    </p>
</template>

<style scoped></style>
