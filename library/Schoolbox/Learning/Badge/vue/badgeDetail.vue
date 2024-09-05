<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
import BadgeImage from "./badge-image";
import CodesList from "@schoolboxCurriculum/vue/codes-list";
import MediaArticle from "@schoolboxVue/components/media-article";

import { translateDaysToExpiryLabel } from "./expiry-helper";

export default {
    components: {
        BadgeImage,
        CodesList,
        MediaArticle,
    },
    props: {
        /**
         * The badge to display in this detail panel.
         */
        badge: {
            type: Object,
            default: null,
        },
        /**
         * Whether these details are being displayed in a modal
         */
        parentCss: {
            type: String,
            default: "badge-modal",
        },
        /**
         * To customise how the image displays
         */
        imgCss: {
            type: String,
            default: "badge-img-container",
        },
    },
    emits: ["code-selected"], //dispatch to notify of curriculum code selection
    computed: {
        badgeTagNames() {
            if (this.badge?.tags?.length) {
                return this.badge.tags.map((v) => v.name).join(", ");
            }
            return "";
        },
        curriculumNodesWithCode() {
            if (this.badge?.curriculumNodes?.length) {
                return this.badge.curriculumNodes.filter((v) => v.code.length);
            }
            return [];
        },
        achievementExpiryLabel() {
            return translateDaysToExpiryLabel(this.badge?.achievementExpiry);
        },
        goalExpiryLabel() {
            return translateDaysToExpiryLabel(this.badge?.goalExpiry);
        },
    },
    methods: {
        /**
         * to open up the details modal
         *
         * @pararm { object }
         */
        onCodeSelected(node) {
            this.$emit("code-selected", node);
        },
    },
};
</script>

<template>
    <div :class="parentCss">
        <badge-image :badge="badge" :class="imgCss"></badge-image>
        <h2>{{ badge.name }}</h2>
        <p class="meta" v-if="badge.category">Category: {{ badge.category.name }}</p>
        <p class="meta" v-if="badge.goalExpiry">Goal expires: {{ goalExpiryLabel }} after setting</p>
        <p class="meta" v-if="badge.achievementExpiry">
            Achievement expires: {{ achievementExpiryLabel }} after awarding
        </p>
        <p class="meta" v-if="badgeTagNames.length > 0">Tags: {{ badgeTagNames }}</p>
        <p v-if="badge.curriculumNodes.length" class="meta">
            Curriculum Codes:
            <codes-list :nodes="curriculumNodesWithCode" @selected="onCodeSelected" />
        </p>
        <media-article v-if="badge.body" :content="badge.body"></media-article>
    </div>
</template>
