<script setup>

/**
 * Used to display an inline list of curriculum codes that can be selected
 */

 const props = defineProps({
    /**
     * the nodes containing the codes to display
     */
    nodes: {
        type: Array,
        required: true,
    },
});

</script>

<template>
    <template v-for="(node, index) in props.nodes">
        <button class="meta-button" title="View details">{{ node.code }}</button><template v-if="(index + 1) < props.nodes.length">, </template>
    </template>
</template>

<style scoped>

</style>