<script>

import axios from 'axios';
import Toast from "@schoolbox/toast";
import RevealModal from "@schoolbox/reveal-modal";
import BadgeDetail from "./badgeDetail";

export default {
    components: {BadgeDetail, RevealModal, Toast},
    props: {
        getBadgesRoute: {
            type: String,
            default: '',
        },
        postBadgeRoute: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            currentBadge: {},
            data: [],
            search: {},
            metadata: {
                count: null,
                cursor: null,
            },
            showSkeletonLoader: true,
        }
    },
    computed: {
    },
    mounted() {
        this.load();
    },
    methods: {
        /**
         * Load badges.
         */
        load() {
            this.data = [];
            this.append(null);
        },

        /**
         * Appends badges to the data list.
         *
         * @param {String|null} cursor
         */
        append(cursor) {
            // TODO this is very generic, a candidate to be factored into its own place
            let params = {};
            if (cursor) {
                params.cursor = cursor;
            }
            axios
                .get(this.getBadgesRoute, {params})
                .then(response => {
                    this.data            = this.data.concat(response?.data?.data ? response.data.data : [])
                    this.metadata.count  = response?.data?.metadata?.count  ? response.data.metadata.count  : 0;
                    this.metadata.cursor = response?.data?.metadata?.cursor ? response.data.metadata.cursor : null;
                })
                .finally(() => this.showSkeletonLoader = false);
        },

        /**
         * Opens a modal window for viewing this badge's full details.
         *
         * @param {Object} badge
         */
        openDetailModal(badge) {
            this.currentBadge = badge;
            this.$refs.detailModal.open();
        },
    },
}
</script>

<template>
    <div>
        <section v-if="showSkeletonLoader">
            <div v-for="n in 3" class="list-item">
                <div class="small-12 card skeleton">
                    <div class="img"></div>
                    <h3></h3>
                    <p class="meta"></p>
                </div>
            </div>
        </section>
        <section v-else class="content">
            <div v-if="data.length === 0" class="empty-state">
                <i class="icon-schoolbox"></i>
                <p>No badges have been created yet.</p>
            </div>
            <template v-else>

                <!-- Search panel. Should this use the filters system? -->
                <ul class="flex-list buttons">
                    <li><input type="text" placeholder="Badge Name" v-model="search.badgeName" /></li>
                    <li><input type="text" placeholder="Creator" v-model="search.creator" /></li>
                    <li><input type="text" placeholder="Category" v-model="search.category" /></li>
                    <li><a href="#">Reset to defaults</a></li>
                    <li><a href="#" class="submit">Apply filters</a></li>
                </ul>

                <ul class="grid small-block-grid-2 medium-block-grid-3 large-block-grid-4">
                    <li v-for="badge in data" @click.prevent="openDetailModal(badge)">
                        <div class="card small-12">
                            <img class="no-margin" :src="badge.image._links.image" :alt="badge.name" />

                            <!-- actions go here -->
                            <p><a>{{ badge.name }}</a></p>
                            <p class="meta" v-if="badge.category">{{ badge.category.name }}</p>
                        </div>
                    </li>
                </ul>
            </template>
        </section>

        <reveal-modal ref="detailModal">
            <badge-detail :badge="currentBadge"></badge-detail>
        </reveal-modal>

        <toast ref="toast"></toast>
    </div>
</template>
