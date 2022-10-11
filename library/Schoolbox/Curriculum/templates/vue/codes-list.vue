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
        <span :title="node.name">{{ node.code }}</span><template v-if="(index + 1) < props.nodes.length">, </template>
    </template>
</template>

<style scoped>

</style>