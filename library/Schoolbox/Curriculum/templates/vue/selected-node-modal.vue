<script setup>

/**
 * displays further info in a modal on a curriculm node that's been selected
 */

import { ref, computed, watch } from 'vue';
import RevealModal from '@schoolboxVue/components/reveal-modal.vue';


const props = defineProps({
    /**
     * the selected curriculum node
     */
    node: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                code: '',
                name: '',
                breadcrumb: '',
            }
        }
    },
});

const emit = defineEmits(['closed']);
const modalEl = ref(null);

/**
 * to open the modal when requested
 */
watch(() => props.node, (newNode) => {
    if (newNode.id !== 0) {
        modalEl.value.open()
    }
});

/**
 * If the node is one that can be displayed
 *
 * @returns { boolean }
 */
const isValidNode = computed(() => props.node.id !== 0 ? true : false);

/**
 * Add in the code if it's available otherwise just show the title
 *
 * @returns { String }
 */
const title = computed(() => {
    if (props.node?.code && props.node.code !== '') {
        return `[${props.node.code}] ${props.node.name}`;
    } else {
        return props.node?.name;
    }
});

/**
 * Display the list of breadcrumbs to provide context on where the selected node comes from
 *
 * @returns { String }
 */
const breadcrumbs = computed(() => {
    if (props.node?.breadcrumbs.length > 0) {
        const justNames = props.node.breadcrumbs.map((breadcrumb) => breadcrumb.name);
        return justNames.join(' > ');
    } else {
        return '';
    }
});

/**
 * close the modal, setting the activeNode to null so we can allow another selection
 */
function onModalClosed () {
    emit('closed');
}

</script>

<template>
    <reveal-modal
        ref="modalEl"
        size="large"
        @closed="onModalClosed"
    >
        <template v-if="isValidNode">
            <h2>{{ title }}</h2>
            <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
            <div v-html="props.node.description"></div>
        </template>
    </reveal-modal>
</template>