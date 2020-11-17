<script>
export default {
    components: {},
    props: {
        badge: {
            type: Object,
            default: {},
        },
    },
    computed: {
        badgeTagNames() {
            if (this.badge?.tags?.length) {
                return this.badge.tags.map(v => v.name).join(', ');
            }
            return '';
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
        <p class="meta" v-if="badgeTagNames.length > 0">Tags: {{ badgeTagNames }}</p>
        <article>{{ badge.body }}</article>
    </div>
</template>
