<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Displays various usage statistics via bar charts and tables display for the entire system
 * Can be accessed at /adminv2/usage
 */

import UsageGlobalTotal from "./usage-global-total";
import UsageGlobalSystem from "./usage-global-system";
import UsageGlobalFiletype from "./usage-global-filetype";
import UsageGlobalRole from "./usage-global-role";
import UsageGlobalUser from "./usage-global-user";

export default {
    components: {
        UsageGlobalTotal,
        UsageGlobalSystem,
        UsageGlobalFiletype,
        UsageGlobalRole,
        UsageGlobalUser,
    },

    props: {
        endpointTotalPayload: {
            type: String,
            required: true,
        },
        endpointSystemPayload: {
            type: String,
            required: true,
        },
        endpointFileTypePayload: {
            type: String,
            required: true,
        },
        endpointRolePayload: {
            type: String,
            required: true,
        },
        endpointUserPayload: {
            type: String,
            required: true,
        },
        isS3Adapter: {
            type: Boolean,
            required: true,
        },
    },
};
</script>

<template>
    <div>
        <!-- This chart doesn't display anything if you're using S3 adapter because
            you've technically got a scalable and unrestricted storage space that
            you can expand on demand - though you might want to change how that
            is represented visually inside the vue component itself

            Also it's a kind of obscure notion so this brings it front and center
        -->
        <div class="small-12 island" v-if="isS3Adapter">
            <div id="usage-global-total-container" class="usage-chart">
                <usage-global-total :endpoint="endpointTotalPayload" :is-s3-adapter="isS3Adapter"></usage-global-total>
            </div>
        </div>

        <div class="small-12 large-6 island">
            <div id="usage-global-system-container" class="usage-chart">
                <usage-global-system
                    :endpoint="endpointSystemPayload"
                    :is-s3-adapter="isS3Adapter"
                ></usage-global-system>
            </div>
        </div>

        <div class="small-12 large-6 island">
            <div id="usage-global-filetype-container" class="usage-chart">
                <usage-global-filetype
                    :endpoint="endpointFileTypePayload"
                    :is-s3-adapter="isS3Adapter"
                ></usage-global-filetype>
            </div>
        </div>

        <div class="small-12 island">
            <div id="usage-global-role-container" class="usage-chart">
                <usage-global-role :endpoint="endpointRolePayload" :is-s3-adapter="isS3Adapter"></usage-global-role>
            </div>
        </div>

        <div class="small-12 columns">
            <div id="usage-global-user-container">
                <h2 class="subheader">CMS Usage by User</h2>
                <usage-global-user :endpoint="endpointUserPayload"></usage-global-user>
            </div>
        </div>
    </div>
</template>
