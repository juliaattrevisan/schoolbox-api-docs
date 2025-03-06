<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";

/**
 * An ordered list of each field of the lesson plan
 */
const fields = window.Vue.ref([
    {
        id: "new1",
        label: "Learning Intention",
        meta: null,
    },
    {
        id: "new2",
        label: "Success Criteria",
        meta: "Some meta about this field",
    },
    {
        id: "new3",
        label: "Learning Sequence",
        meta: "Some meta about this field",
    },
    {
        id: "new4",
        label: "Differentiation",
        meta: "Some meta about this field",
    },
    {
        id: "new5",
        label: "Resources",
        meta: null,
    },
    {
        id: "new6",
        label: "Teacher Notes",
        meta: null,
    },
]);

const isModalOpen = window.Vue.ref(false);

/**
 * Open the modal to the initial page
 */
function openModal(): void {
    isModalOpen.value = true;
}
</script>

<template>
    <button type="button" class="button expand icon-chat" @click="openModal">Generate lesson with AI</button>

    <basic-modal v-model:is-open="isModalOpen" :has-background-click="true"> random modal content </basic-modal>
</template>

<style scoped></style>
