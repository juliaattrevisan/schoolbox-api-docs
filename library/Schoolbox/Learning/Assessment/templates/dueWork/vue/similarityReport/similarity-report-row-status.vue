<script>

/**
 * The status of the submission and a link to the inline grader
 *
 */

export default {

    props: {
        data: { //the row data
            type: Object,
            required: true
        },
    },

    methods: {

        /**
         * Format the submission status display label
         *
         * @param { String }
         *
         * @return { String }
         */
        getFormattedStatus (status) {
            const statusOptions = {
                'failed': '<span class="progress-tick no-text tiny failed" title="Resubmission required"></span><span>Resubmission required</span>',
                'pending': '<span class="progress-tick no-text tiny pending" title="Mark required"></span><span>Marking required</span>',
                'reviewed': '<span class="progress-tick no-text tiny reviewed" title="Reviewed"></span><span>Reviewed</span>',
            }
            return statusOptions[status];
        }

    },

}
</script>

<template>
    <div class="status title-on-single-column" data-title="Status">
        <div class="status__column">
            <p class="meta" v-html="getFormattedStatus(data.submission.status)"></p>
        </div>
        <div class="status__column">
            <a class="button icon-approve" :href="data.submission._links.inline_grader" title="Review Submission">Review Submission</a>
        </div>
    </div>
</template>

<style lang="scss" style="scoped">

@import './similarity-report';

.status {
    display: flex;
    justify-content: space-between; //the status and review buttons on either ends of column
    @media (max-width: $tableToSingleColumn) {
        flex-direction: column;
    }
}

    .status__column {
        @media (max-width: $tableToSingleColumn) {
            margin-bottom: 0.5rem; //so there's a gap to .button
        }
    }

.meta .progress-tick {
    margin-right: 0.5rem; //bit of extra gap
}

.button {
    min-height: 0; //no need for min height in this instance
    padding: 0.5rem 1rem; //add a bit more space left and right of the button
}

</style>
