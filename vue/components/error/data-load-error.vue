<script setup lang="ts">
/**
 * To display an error that we cant currently load the relevant data
 */

interface Props {
    dataTitle?: string;
    furtherInfo?: string;
}
const props = withDefaults(defineProps<Props>(), {
    dataTitle: "",
    furtherInfo: "",
});

/**
 * Reload the page to try loading again
 */
function onRefreshSelected(): void {
    window.location.reload();
}
</script>

<template>
    <section class="content">
        <div class="alert-box alert">
            Sorry, we are currently unable to load the {{ props.dataTitle }} data. Please
            <a href="#refresh-page" @click.prevent="onRefreshSelected">refresh the page</a>
            to try again and contact your support if the problem persists.
            <em v-if="props.furtherInfo">
                <br />
                <strong>Further information:</strong> {{ props.furtherInfo }}
            </em>
        </div>
    </section>
</template>

<style scoped></style>
