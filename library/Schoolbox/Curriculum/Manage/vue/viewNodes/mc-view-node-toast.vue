<script setup>

/**
 * Inline toast messages that appear when a node is added or updated
 */

import { ref, onMounted, computed } from 'vue';
import { timeout } from '@js/util/jsUtils';
import { useActiveNodeStore } from '../activeNode/store/mcActiveNodeStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import { useUpdateNodeStore } from '../activeNode/store/mcUpdateNodeStore';


// const activeNodeStore = useActiveNodeStore();
const addSubsectionStore = useAddSubsectionStore();
// const updateNodeStore = useUpdateNodeStore();


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

const showToast = ref(false); //to show hide the toast
const action = ref(''); //to help describe what action took place, ie 'updated' or 'added'


/**
 * this node could have been just added, so we check
 * if we should display the toast for just added
 */
onMounted(() => {
    if (props.node.id === addSubsectionStore.id) {
        display('added');
        addSubsectionStore.unsetId();
    }
});

/**
 * we listen for the actions we want to respond with
 * https://pinia.vuejs.org/core-concepts/actions.html#without-setup
 */
useUpdateNodeStore().$onAction(({ name, after, onError }) => onAction({ name, after, onError }));

const actionsList = ['update']; //the list of actions that can occur that we are listening for

/**
 * one of the stores actions has been run, so check if it's one
 * we are listening for and then respond
 *
 * @param { object }
 */
function onAction ({ name, after, onError }) {
    if (actionsList.includes(name)) {
        after((nodeId) => {
            if (props.node.id === nodeId) {
                display('updated');
            }
        });
        onError((error) => {
            console.warn(error);
        });
    }
}

/**
 * display the toast for five seconds
 *
 * @param { string }
 */
async function display ($action) {
    action.value = $action;
    showToast.value = true;
    await timeout(5000);
    showToast.value = false;
}

/**
 * the current message to display
 *
 * @returns { string }
 */
const message = computed(() => `${props.title} successfully ${action.value}`);

/**
 * close the toast prior to it dissapearing on its own
 */
function onCloseSelected () {
    showToast.value = false;
}


</script>

<template>
    <Transition>
        <div v-if="showToast" class="alert-box success toast-container">
            <em>{{ message }}</em>
            <button class="close" title="Close" @click.prevent="onCloseSelected" @keyup.enter="onCloseSelected">×</button>
        </div>
    </Transition>
</template>

<style lang="scss" scoped>

.toast-container {
    font-size: 1rem;
    height: 100%;
    left: 0;
    margin: 0;
    position: absolute;
    top: 0;
    width: 100%;
    .close {
        right: 0.25rem;
    }
}

.v-enter-active,
.v-leave-active {
    transition: transform 0.3s ease-in-out;
}
.v-enter-from,
.v-leave-to  {
    transform: translateY(100%);
}


</style>