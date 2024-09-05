<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Displays various usage statistics via bar charts and tables display for a specific user
 * Can be accessed for example at adminv2/usage/specific/1
 */

import UsageSpecificTotal from "./usage-specific-total";
import UsageSpecificSystem from "./usage-specific-system";
import UsageSpecificFiletype from "./usage-specific-filetype";
import UsageSpecificFile from "./usage-specific-file";

export default {
    components: {
        UsageSpecificTotal,
        UsageSpecificSystem,
        UsageSpecificFiletype,
        UsageSpecificFile,
    },

    props: {
        fullName: {
            type: String,
            required: true,
        },
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
        endpointFilePayload: {
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
        <div class="small-12 columns">
            <h2 class="subheader">Usage for {{ fullName }}</h2>
        </div>

        <div class="small-12 island">
            <section class="content">
                <div id="usage-specific-total-container">
                    <usage-specific-total
                        :endpoint="endpointTotalPayload"
                        :is-s3-adapter="isS3Adapter"
                    ></usage-specific-total>
                </div>
            </section>
        </div>

        <div class="small-12 medium-6 island">
            <section class="content">
                <div id="usage-specific-system-container">
                    <usage-specific-system
                        :endpoint="endpointSystemPayload"
                        :is-s3-adapter="isS3Adapter"
                    ></usage-specific-system>
                </div>
            </section>
        </div>

        <div class="small-12 medium-6 island">
            <section class="content">
                <div id="usage-specific-filetype-container">
                    <usage-specific-filetype
                        :endpoint="endpointFileTypePayload"
                        :is-s3-adapter="isS3Adapter"
                    ></usage-specific-filetype>
                </div>
            </section>
        </div>

        <div class="small-12 columns">
            <div id="usage-specific-file-container">
                <usage-specific-file :endpoint="endpointFilePayload" :is-s3-adapter="isS3Adapter"></usage-specific-file>
            </div>
        </div>
    </div>
</template>
