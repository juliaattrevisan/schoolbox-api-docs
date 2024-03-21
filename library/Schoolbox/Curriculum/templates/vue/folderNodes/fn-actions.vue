<script setup lang="ts">
/**
 * To further modify the linked curriculum
 */

const emit = defineEmits<{
    updateSelected: [];
}>();

/**
 * To open the curriculum code mapping modal
 */
function onModifySelected() {
    emit("updateSelected");
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
