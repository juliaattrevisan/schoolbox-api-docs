<script setup>

/**
 * Inline toast messages that appear when a node is added or updated
 */


const props = defineProps({
    /**
     * the assigned node to display
     */
    node: {
        type: Object,
        required: true,
        default() {
            return {
                id: '0',
                name: '-',
                children: [],
            }
        }
    },
    /**
     * the already computed node title
     */
     title: {
        type: String,
        required: true,
    },
});

/**
 * close the toast prior to it dissapearing on its own
 */
function onCloseSelected () {
    console.warn('onCloseSelected');
}


</script>

<template>
    <div class="alert-box success toast-container">
        {{ props.title }} successfully updated
        <button class="close" title="Close" @click.prevent="onCloseSelected" @keyup.enter="onCloseSelected">×</button>
    </div>
</template>

<style lang="scss" scoped>


.toast-container {
    height: 100%;
    left: 0;
    margin: 0;
    top: 0;
    position: absolute;
    width: 100%;
}

</style>