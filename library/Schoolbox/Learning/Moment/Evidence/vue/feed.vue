<script>

import axios from 'axios';

const URL_LEARNING_MOMENT_SOURCE = '/learning/evidence/user/';

export default {
    components: {
    },
    data() {
        return {
            data:     [],
            metadata: {
                count:  null,
                cursor: null,
            }
        }
    },
    computed: {
        config: () => window.learningMomentsFeedConfig,
    },
    created() {
        this.load();
    },
    methods: {
        /**
         * Load the list of learning moments.
         *
         * Once received, group them by date.
         */
        load() {
            axios.get(URL_LEARNING_MOMENT_SOURCE + String(this.config.studentId));
        },
    }
}
</script>

<template>

</template>
