<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * To view a members profile image, name and meta info
 */

const props = defineProps({
    /**
     * Contains all the member data
     */
    member: {
        type: Object,
        required: true,
    },
});

/**
 * To avoid dumping a whole lot of errors in the console,
 * use the dummy grey image when in StoryBook
 */
function getMemberPortraitSrc() {
    if (typeof STORYBOOK_ENV !== "undefined") {
        return `https://demo.schoolbox.com.au/images/fail.php?transform_type=portrait&transform=square64&code=500`;
    } else {
        return props.member._links.avatar;
    }
}
</script>

<template>
    <div class="card small-12">
        <a v-if="member.id" :href="member._links.profile" title="View profile" class="name">
            <img :src="getMemberPortraitSrc(member.id)" :alt="`${member.givenName} portrait`" />
            {{ member.fullName }}
        </a>
        <div v-else>
            <img
                src="/images/fail.php?transform_type=portrait&transform=square64&code=500"
                :alt="`${member.givenName} portrait`"
            />
            {{ member.fullName }}
        </div>
        <p class="meta">
            {{ member.role }}, {{ member.campus }}{{ member.yearLevel ? ", " + member.yearLevel : ""
            }}{{ member.house ? ", " + member.house : "" }}
        </p>
    </div>
</template>

<style scoped></style>
