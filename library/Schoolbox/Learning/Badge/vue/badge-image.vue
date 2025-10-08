<script lang="ts" setup>
import type { Badge } from "@schoolboxBadge/templates/types/Badge";

interface Props {
    badge: Pick<Badge, "image" | "name" | "_links">;
    css: string;
}

const props = withDefaults(defineProps<Props>(), {
    css: "",
});
</script>
<template>
    <div>
        <!-- Images representing badges can be a direct path to an svg
          -- or an uploaded image into storage; decide which one based
          -- on which fields were null
          -->
        <template v-if="props.badge.image">
            <img
                :src="props.badge.image._links.image"
                :alt="props.badge.name"
                :title="props.badge.name"
                class="badge-image"
                :class="props.css"
            />
        </template>
        <template v-else>
            <img
                :src="props.badge._links.icon"
                :alt="props.badge.name"
                :title="props.badge.name"
                class="badge-icon"
                :class="props.css"
            />
        </template>
    </div>
</template>

<style lang="scss" scoped>
.selected-badge-image {
    //shrink down the image, used by #badge-list-form
    height: auto;
    width: 30px;
}
</style>
