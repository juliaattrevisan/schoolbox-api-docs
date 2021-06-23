<script>
import BadgeImage from './badge-image';

import {translateAchievementExpiryToLabel} from './badgeAchievementExpiries';

export default {
    components: {
        BadgeImage
    },
    props: {
        /**
         * The badge to display in this detail panel.
         */
        badge: {
            type: Object,
            default: null,
        },
    },
    computed: {
        badgeTagNames() {
            if (this.badge?.tags?.length) {
                return this.badge.tags.map(v => v.name).join(', ');
            }
            return '';
        },
        curriculumNodesWithCode() {
            if (this.badge?.curriculumNodes?.length) {
                return this.badge.curriculumNodes.filter(v => v.code.length);
            }
            return [];
        },
        achievementExpiryLabel() {
            return translateAchievementExpiryToLabel(this.badge?.achievementExpiry);
        }
    },
    methods: {},
}
</script>

<template>
    <div class="badge-modal">
        <badge-image :badge="badge" class="badge-img-container"></badge-image>
        <h2>{{ badge.name }}</h2>
        <p class="meta" v-if="badge.category">Category: {{ badge.category.name }}</p>
        <p class="meta" v-if="achievementExpiryLabel">Expires: {{ achievementExpiryLabel }} after awarding</p>
        <p class="meta" v-if="badgeTagNames.length > 0">Tags: {{ badgeTagNames }}</p>
        <article v-html="badge.body"></article>
        <div v-if="badge.curriculumNodes.length">
            <hr />

            <p class="meta">
                Curriculum Codes:
                <template v-for="(curriculumNode, index) in curriculumNodesWithCode">
                    <span :title="curriculumNode.name">{{ curriculumNode.code }}</span><template v-if="(index + 1) < curriculumNodesWithCode.length">, </template>
                </template>
            </p>
        </div>
    </div>
</template>
