<script setup>
/**
 * This diplays a single or list of badges linked to a number of users
 * while evidence is being added or updated to a badge
 *
 */

import BadgeDetail from "../../vue/badgeDetail.vue";
import BadgeImage from "../../vue/badge-image.vue";
import BadgeModal from "../../vue/badge-modal.vue";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import NodeDetails from "@schoolboxCurriculum/vue/node-details";
import { getEmptyNode } from "@schoolboxCurriculum/js/nodeUtils";

const props = defineProps({
    /**
     * the list of badges to display
     */
    badges: {
        type: Object,
        required: true,
        default: {},
    },
    /**
     * whether the awards are currently goals or if they're being awarded
     */
    isGoal: {
        type: Boolean,
        required: true,
    },
});

const selectedNodeId = window.Vue.ref(null); // number|null
const nodeModalEl = window.Vue.ref(null);

/**
 * whether only a single badge is selected, in which case we can
 * expand out the badge details out of the modal
 *
 * @returns { boolean }
 */
const isSingleBadge = window.Vue.computed(() => {
    return Object.values(props.badges).length == 1;
});

/**
 * all the details of the single badge
 *
 * @returns { object }
 */
const singleBadge = window.Vue.computed(() => {
    return Object.values(props.badges)[0];
});

/**
 * to open up the curriculum node details modal
 *
 * @pararm { object }
 */
function onCodeSelected(node) {
    selectedNodeId.value = node.id;
    nodeModalEl.value.open();
}

const currentBadge = window.Vue.ref(null);
const isBadgeModalOpen = window.Vue.ref(false);

/**
 * Opens a modal window for viewing this badge's full details.
 *
 * @param {Object} badge
 */
function openBadgeModal(badge) {
    currentBadge.value = badge;
    isBadgeModalOpen.value = true;
}

/**
 * the detail modal is closed, so we should set the boolean to
 * false so we can open it again
 */
function onDetailModalClosed() {
    isBadgeModalOpen.value = false;
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <section>
                <div v-if="isSingleBadge" class="small-12 card">
                    <badge-detail
                        :badge="singleBadge"
                        parent-css="evidence-badge-container"
                        :img-css="`evidence-badge-img-container ${isGoal ? 'goal' : ''}`"
                        @code-selected="onCodeSelected"
                    />
                </div>
                <div v-else class="badge-grid v-card-list">
                    <div class="badge v-card" v-for="badge in badges" :key="badge.id">
                        <div class="card-feature-image">
                            <a @click.prevent="openBadgeModal(badge)" :title="badge.name">
                                <badge-image :badge="badge"></badge-image>
                            </a>
                        </div>
                        <div class="actions-small-1 card-content">
                            <div class="list-item">
                                <h3>
                                    <a @click.prevent="openBadgeModal(badge)" :title="badge.name">{{ badge.name }}</a>
                                </h3>
                            </div>
                            <p class="meta" v-if="badge.category" :title="'Category: ' + badge.category.name">
                                {{ badge.category.name }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <reveal-modal ref="nodeModalEl">
        <node-details v-if="selectedNodeId !== null" :node-id="selectedNodeId" />
    </reveal-modal>
    <badge-modal :isOpen="isBadgeModalOpen" :badge="currentBadge" @closed="onDetailModalClosed" />
</template>

<style scoped>
.badge-grid {
    padding: 1rem;
}
</style>
