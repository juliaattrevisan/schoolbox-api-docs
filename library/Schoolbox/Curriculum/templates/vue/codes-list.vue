<script setup>
/**
 * Used to display an inline list of curriculum codes that can be selected
 * and emit an event when a node is selected
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

const emit = defineEmits(["selected"]);

/**
 * dispatch an emit to notify that a node has been selected
 *
 * @pararm { object }
 */
function onCodeSelected(node) {
    emit("selected", node);
}
</script>

<template>
    <template v-for="(node, index) in props.nodes">
        <button
            class="meta-button"
            title="View details"
            @click.prevent="onCodeSelected(node)"
            @keyup.enter="onCodeSelected(node)"
        >
            {{ node.code }}</button
        ><template v-if="index + 1 < props.nodes.length">, </template>
    </template>
</template>
