<script setup lang="ts">
/**
 * To further modify the linked curriculum
 */

import { useModalStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csModalStore";

const modalStore = useModalStore();

/**
 * Open the curriculum code mapping modal
 */
function onModifySelected() {
    modalStore.open();
}
</script>

<template>
    <div class="component-action">
        <section>
            <button @click.prevent="onModifySelected">Update Curriculum</button>
        </section>
    </div>
</template>

<style scoped></style>
