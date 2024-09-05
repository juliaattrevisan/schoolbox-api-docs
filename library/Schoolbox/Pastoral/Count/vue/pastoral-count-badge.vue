<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * render a single pastoral badge with a number that represents the number of occurrences that have been recorded
 */
export default {
    name: "pastoral-count-badge",

    props: {
        /**
         * The url which leads to the students pastoral page
         */
        destinationUrl: {
            default: "/pastoral/student/25?type=1&subtype=1",
            type: String,
        },
        /**
         * The icon used to display and represent this pastoral event
         */
        icon: {
            default: "achievement",
            type: String,
        },
        /**
         * the primary color used to display the font and icon
         */
        fgColor: {
            default: "rgb(86, 172, 37)",
            type: String,
        },
        /**
         * the secondary color used to fill in the badges background
         */
        bgColor: {
            default: "rgb(215, 243, 199)",
            type: String,
        },
        /**
         * the number of pastoral events recorded for this type & subtype combo
         */
        count: {
            default: "2",
            type: String,
        },
        /**
         * the human readable description of the pastoral type & subtype combo
         */
        title: {
            default: "Achievement",
            type: String,
        },
    },

    mounted() {
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        $(document).foundation("tooltip", "reflow");
    },
};
</script>
<template>
    <a :href="destinationUrl">
        <span
            :class="`label neutral icon-${icon}`"
            data-tooltip
            :title="title"
            :style="{
                'background-color': bgColor,
                color: fgColor,
            }"
        >
            {{ count }}
        </span>
    </a>
</template>

<style scoped>
span {
    margin-top: 5px;
    font-style: normal;
}

span[class*="icon-"]::before {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 0.8rem;
}

.label {
    padding: 0.6rem 0.7rem 0.6rem 2.6rem;
}
</style>
