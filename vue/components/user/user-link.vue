<script setup lang="ts">
/**
 * A simple convenience wrapper around a user object to render a link to their profile
 * depending on the ._links that are available
 *
 * Currently has a slot to be more flexible, but could extend this to make it also deal with
 * rendering the name and/or photo too at some point
 */

import type { User, UserAuthor, UserCC } from "@schoolboxLibrary/types/User";

interface Props {
    user: UserAuthor | UserCC | User | null;
    /** override the title, defaults to "View Profile" */
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: "View Profile",
});

const profileLink = window.Vue.computed(() => {
    if (props.user === null) {
        return null;
    }

    /* Use the actual _links if available, even if the profile link would be null (as that would mean the user is deleted) */
    if (props.user._links !== undefined) {
        return props.user._links.profile || null;
    }

    /* otherwise, assume we can link to the standard user profile page if a meaningful ID is present (treat 0 as invalid) */
    if ("id" in props.user && !!props.user.id) {
        return `/search/user/${props.user.id}`;
    }

    return null;
});
</script>

<template>
    <a v-if="profileLink" :href="profileLink" :title="props.title">
        <slot />
    </a>
    <span v-else>
        <slot />
    </span>
</template>

<style scoped></style>
