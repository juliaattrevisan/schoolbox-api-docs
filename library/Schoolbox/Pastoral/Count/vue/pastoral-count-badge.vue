<script>

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
            default: '',
            type: String
        },
        /**
         * The icon used to display and represent this pastoral event
         */
        icon: {
            default: '',
            type: String
        },
        /**
         * the primary color used to display the font and icon
         */
        fgColor: {
            default: '',
            type: String
        },
        /**
         * the secondary color used to fill in the badges background
         */
        bgColor: {
            default: '',
            type: String
        },
        /**
         * the number of pastoral events recorded for this type & subtype combo
         */
        count: {
            default: '',
            type: String
        },
        /**
         * the human readable description of the pastoral type & subtype combo
         */
        description: {
            default: '',
            type: String
        }
    },
}
</script>
<template>
    <a :href="destinationUrl">
        <span :class="`label neutral icon-${icon}`"
              :title="description"
              :style="{
                  'background-color': bgColor,
                  'color': fgColor,
              }"
        >
            {{ count }}
        </span>
    </a>
</template>

<style scoped>
    span {
        font-style: normal;
    }
</style>
