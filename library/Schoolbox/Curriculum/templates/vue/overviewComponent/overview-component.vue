<script setup>
/**
 * The curriculum overview component that can be added to a home page
 * to display all or a single curriculum node (and all its children)
 *
 * @NOTE - currently we are only handling the opening of the details-modal when a link is selected
 * however this whole component is ripe for a re-do, so more could be added in the near future
 */

import CurriculumDetailsModal from "@schoolboxCurriculum/vue/curriculumDetailsModal/curriculum-details-modal.vue";
</script>

<template>
    <curriculum-details-modal />
</template>
