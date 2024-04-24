<script setup lang="ts">
/**
 * To further modify the linked curriculum
 */

const emit = defineEmits<{
    modifySelected: [];
}>();

/**
 * To open the curriculum code mapping modal
 */
function onModifySelected() {
    emit("modifySelected");
}
</script>

<template>
    <div class="component-action">
        <section>
            <button @click.prevent="onModifySelected">Modify Curriculum</button>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all; /* to allow the button to sit above the overlay */
}
</style>
