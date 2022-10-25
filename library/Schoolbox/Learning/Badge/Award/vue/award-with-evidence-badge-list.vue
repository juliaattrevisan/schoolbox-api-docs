<script setup>

/**
 * This is a list of badges that will currently be awarded to the selected users coupled with evidence
 */

import { ref } from 'vue';
import BadgeImage from '../../vue/badge-image.vue';
import SequentialRevealModal from '@schoolboxVue/components/sequential-reveal-modal';
import NodeDetails from '@schoolboxCurriculum/vue/node-details';
import { getEmptyNode } from '@schoolboxCurriculum/js/nodeUtils';


const props = defineProps({
    /**
     * the list of badges to display
     */
    badges: {
        type: Object,
        required: true,
        default: [],
    },
});

const currentBadge = ref()
const isDetailModalOpen = ref(false);
const selectedNodeDetails = ref(getEmptyNode());
const detailModalIndex = ref(1);

/**
 * Opens a modal window for viewing this badge's full details.
 *
 * @param {Object} badge
 */
function openDetailModal (badge) {
    currentBadge.value = badge;
    isDetailModalOpen.vaue = true;
}

/**
 * a code has been selected in the detail modal
 *
 * @pararm { object }
 */
function onCodeSelected (node) {
    selectedNodeDetails.value = node;
    detailModalIndex.value++;
}

/**
 * the detail modal is closed, so we should set the boolean to
 * false so we can open it again
 */
function onDetailModalClosed () {
    isDetailModalOpen.value = false;
}

</script>

<template>
    <div class="row">
        <!-- <div class="small-12 island">
            <section class="content">
                <div class="badge-grid v-card-list">
                    <div class="badge v-card" v-for="badge in badges" :key="badge.id">
                        <div class="card-feature-image">
                            <a @click.prevent="openDetailModal(badge)" :title="badge.name">
                                <badge-image :badge="badge"></badge-image>
                            </a>
                        </div>
                        <div class=" actions-small-1 card-content">
                            <div class="list-item">
                                <h3>
                                    <a @click.prevent="openDetailModal(badge)" :title="badge.name">{{ badge.name }}</a>
                                </h3>
                            </div>
                            <p class="meta" v-if="badge.category" :title="'Category: ' + badge.category.name">{{ badge.category.name }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div> -->
    </div>
    <sequential-reveal-modal
        size="medium"
        :isOpen="isDetailModalOpen"
        :sequence-index="detailModalIndex"
        @closed="onDetailModalClosed"
    >
        <template v-slot:sequence-1>
            <badge-detail
                v-if="currentBadge"
                :badge="currentBadge"
                @code-selected="onCodeSelected"
            />
        </template>
        <template v-slot:sequence-2>
            <node-details
                :node="selectedNodeDetails"
            />
        </template>
    </sequential-reveal-modal>
</template>

<style scoped>

</style>