<script setup>

/**
 * This is a list of badges that will currently be awarded to the selected users coupled with evidence
 */

import { ref } from 'vue';
import BadgeImage from '../../vue/badge-image.vue';
import BadgeModal from '../../vue/badge-modal.vue';


const props = defineProps({
    /**
     * the list of badges to display
     */
    badges: {
        type: Object,
        required: true,
        default: {},
    },
});

const currentBadge = ref(null);
const isDetailModalOpen = ref(false);

/**
 * Opens a modal window for viewing this badge's full details.
 *
 * @param {Object} badge
 */
function openDetailModal (badge) {
    currentBadge.value = badge;
    isDetailModalOpen.value = true;
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
        <div class="small-12 island">
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
        </div>
    </div>
    <badge-modal
        :isOpen="isDetailModalOpen"
        :badge="currentBadge"
        @closed="onDetailModalClosed"
    />
</template>

<style scoped>

</style>
