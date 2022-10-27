<script setup>

/**
 * This diplays a single or list of badges linked to a number of users
 * while evidence is being added or updated to a badge
 *
 */

import { ref, computed } from 'vue';
import BadgeDetail from '../../vue/badgeDetail.vue';
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
 * whether only a single badge is selected, in which case we can
 * expand out the badge details out of the modal
 *
 * @returns { boolean }
 */
const isSingleBadge = computed(() => {
    return Object.values(props.badges).length;
});

/**
 * all the details of the single badge
 *
 * @returns { object }
 */
const singleBadge = computed(() => {
    return Object.values(props.badges)[0];
});

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
                <div v-if="isSingleBadge" class="small-12 card padding-none">
                    <badge-detail
                        :badge="singleBadge"
                        parent-css="evidence-badge-container"
                        img-css="evidence-badge-img-container"
                    />
                </div>
                <div v-else class="badge-grid v-card-list">
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