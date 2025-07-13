<script setup lang="ts">
/**
 * To display a users profile photo,
 * This is pretty much just templates/core/include/portrait.twig, but for use in Vue
 */

interface Props {
    /**
     * User takes precedence over userId and externalId
     * If set to false, it will render the default portrait image
     */
    user?: { id?: number | null; externalId?: string | number | null } | false;
    userId?: number;
    externalId?: string;
    /**
     * The size of the portrait to render, defaults to "small"
     */
    render?: "tiny" | "xsmall" | "small" | "menuMobile" | "profile";
    /**
     * A description for the image, used as the alt text and title attribute
     * Defaults to "View Profile", use null to disable it
     */
    description?: string | null;
    roundedCorners?: boolean;
    appendQueryParam?: string;
    class?: string;
    dimensions?: { width: number; height: number };
}

const props = withDefaults(defineProps<Props>(), {
    user: undefined,
    userId: undefined,
    externalId: undefined,
    render: "small",
    description: "View Profile",
    roundedCorners: false,
    appendQueryParam: "",
    class: "",
    dimensions: undefined,
});

const resolvedUserId = window.Vue.computed(() => {
    // Prefer to take the userId from the `user` object
    if (props.user === undefined) {
        return props.userId;
    }

    // Are we seeking the default portrait image?
    if (props.user === false) return 0;

    // We always prefer to use the user.id
    if (props.user.id != null) return props.user.id;

    // But failing that, we should try the user.externalId next
    return undefined;
});

const resolvedExternalId = window.Vue.computed(() => {
    // If we already decided on a user, we don't need to resolve externalId
    if (resolvedUserId.value !== undefined) {
        return undefined;
    }

    // Prefer to take the externalId from the `user` object
    if (props.user && props.user.externalId) {
        return props.user.externalId;
    }

    return props.externalId;
});

const userParameter = window.Vue.computed(() => {
    if (resolvedUserId.value !== undefined) {
        return `id=${resolvedUserId.value}`;
    }

    if (resolvedExternalId.value) {
        return `userId=${resolvedExternalId.value}`;
    }

    // In the worst case, make sure we provide a blank avatar
    return "id=0";
});

const renderParameterMap = {
    tiny: "&size=square32",
    xsmall: "&size=square48",
    small: "&size=square64",
    menuMobile: "&size=square192",
    profile: "&size=constrain200",
} as const;

const renderRoundClassMap = {
    tiny: "c-img--xxs",
    xsmall: "c-img--xs",
    small: "c-img--s",
    menuMobile: "c-img--s",
    profile: "c-img--s",
};

const dimensions = window.Vue.computed(() => {
    if (props.dimensions !== undefined) {
        return props.dimensions;
    }
    if (props.render === "menuMobile") {
        return { width: 72, height: 72 };
    }
    return {};
});

const srcBase = window.Vue.computed(() => `/portrait.php?${userParameter.value}`);
</script>

<template>
    <img
        v-if="props.render === 'profile'"
        :src="`${srcBase}&size=constrain200${props.appendQueryParam}`"
        :srcset="`${srcBase}&size=constrain200 200w, ${srcBase}&size=constrain500 500w`"
        sizes="(max-width: 1300px) 200px, 500px"
        :class="props.class"
        :alt="props.description ?? undefined"
        :title="props.description ?? undefined"
    />

    <div v-else-if="props.roundedCorners" class="c-img c-img--round" :class="renderRoundClassMap[props.render]">
        <div class="c-img__wrapper">
            <img
                :src="`${srcBase}${renderParameterMap[props.render]}${props.appendQueryParam}`"
                :class="props.class"
                :width="dimensions.width"
                :height="dimensions.height"
                :alt="props.description ?? undefined"
                :title="props.description ?? undefined"
            />
        </div>
    </div>

    <img
        v-else
        :src="`${srcBase}${renderParameterMap[props.render]}${props.appendQueryParam}`"
        :class="props.class"
        :width="dimensions.width"
        :height="dimensions.height"
        :alt="props.description ?? undefined"
        :title="props.description ?? undefined"
    />
</template>

<style scoped lang="scss">
/**
 * Some overrides to mimic the old <user-photo> component usage on the markbook popout..
 * I expect we can remove this in the future and tweak the global styles instead, but quick fix for now
 */
.c-img--round.c-img--xxs {
    overflow: visible;

    > .c-img__wrapper {
        max-width: 32px;
        max-height: 32px;
    }
}
</style>
