<script>
export default {
    components: {},
    props: {
        badge: {
            type: Object,
            default: {},
        },
    },
    computed: {},
    methods: {},
}
</script>

<template>

    <div>
        <img :src="badge.image._links.image" :alt="badge.name"/>
        <h2>{{ badge.name }}</h2>
        <article>{{ badge.body }}</article>


    </div>
</template>
