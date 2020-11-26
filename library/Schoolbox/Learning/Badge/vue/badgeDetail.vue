<script>
export default {
    components: {},
    props: {
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
        }
    },
    methods: {},
}
</script>

<template>

    <div>
        <img :src="badge.image._links.image" :alt="badge.name"/>
        <h2>{{ badge.name }}</h2>
        <p class="meta" v-if="badge.category">Category: {{ badge.category.name }}</p>
        <p class="meta" v-if="badge.curriculumNodes.length">
            Curriculum Codes:
            <template v-for="(curriculumNode, index) in curriculumNodesWithCode">
                <span :title="curriculumNode.name">{{ curriculumNode.code }}</span><template v-if="(index + 1) < curriculumNodesWithCode.length">, </template>
            </template>
        </p>
        <p class="meta" v-if="badgeTagNames.length > 0">Tags: {{ badgeTagNames }}</p>
        <article v-html="badge.body"></article>
    </div>
</template>
