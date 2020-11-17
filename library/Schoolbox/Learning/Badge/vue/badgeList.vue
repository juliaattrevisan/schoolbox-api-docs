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
            <ul class="information-list">
                <li v-for="badge in data">
                    <div class="card small-12">
                        <img :src="badge.image._links.image" :alt="badge.name"/>
                        <a @click.prevent="openDetailModal(badge)">
                            <p>{{ badge.name }}</p>
                        </a>
                    </div>
                </li>
            </ul>
        </section>

        <reveal-modal ref="detailModal">
            <badge-detail :badge="currentBadge"></badge-detail>
        </reveal-modal>

        <toast ref="toast"></toast>
    </div>
</template>
