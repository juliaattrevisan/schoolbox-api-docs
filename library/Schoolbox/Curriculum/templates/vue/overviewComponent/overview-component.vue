<script setup lang="ts">
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
import OcEmptyState from "./oc-empty-state.vue";

interface Props {
    // nodes: Node[] | null; // The curriculum nodes to display
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();
</script>

<template>
    <oc-empty-state
        :is-super-user="props.isSuperUser"
        :has-tagging="props.hasTagging"
        :locale-unit="props.localeUnit"
    />
    <curriculum-details-modal />
</template>
