<script setup lang="ts">
import MetaItem from "@schoolboxVue/components/meta/item.vue";

interface Participant {
    id: number | string;
    name: string;
    url?: string | null;
}

const props = defineProps<{
    icon: string;
    label: string;
    participants: Participant[];
}>();
</script>

<template>
    <meta-item v-if="props.participants.length" :icon="props.icon">
        {{ props.label }}:
        <template v-for="(person, index) in props.participants" :key="person.id">
            <template v-if="person.url">
                <a :href="person.url">{{ person.name }}</a>
            </template>
            <template v-else>{{ person.name }}</template
            ><span v-if="index !== props.participants.length - 1">, </span>
        </template>
    </meta-item>
</template>
