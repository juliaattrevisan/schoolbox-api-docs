<template>
    <meta-item v-if="participants.length" :icon="icon">
        {{ label }}:
        <template v-for="(person, index) in participants" :key="person.id">
            <template v-if="person.url">
                <a :href="person.url">{{ person.name }}</a>
            </template>
            <template v-else>{{ person.name }}</template
            ><span v-if="index !== participants.length - 1">, </span>
        </template>
    </meta-item>
</template>

<script>
import MetaItem from "../meta/item.vue";

export default {
    name: "SocsMusicParticipantMetaItem",

    components: {
        MetaItem,
    },

    props: {
        icon: {
            type: String,
            required: true,
        },
        label: {
            type: String,
            required: true,
        },
        participants: {
            type: Array,
            required: true,
        },
    },
};
</script>
