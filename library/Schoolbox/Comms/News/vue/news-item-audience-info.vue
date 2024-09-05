<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/*
 * To display the audience information for a news item, which when is a Composite Audience is summarised and then further detailed in a popup modal
 * or otherwise as an inline list, with those that have links provided
 *
 * ATTENTION - There's a few global vars from library/Schoolbox/Comms/News/templates/layout/banner.twig being referenced here
 *             When you can't find the origin of a var, just search in the banner.twig file
 */

export default {
    data() {
        return {
            audienceBricks: [],
            selectedCompositeBrick: {},
        };
    },

    mounted() {
        this.addAudienceSegments();
    },

    methods: {
        /**
         * Open the modal, populating it with the selected audienceBrick
         *
         */
        addAudienceSegments() {
            const brickManager = new BrickManager(codec, entityTypeLabelMap);
            for (var key in audienceSegments) {
                var segment = audienceSegments[key];
                brickManager.onAddSegment(
                    null,
                    this.audienceBricks,
                    segment.key,
                    segment.name,
                    segment,
                    segment.labels,
                );
            }
        },

        /**
         * Open the modal, populating it with the selected audienceBrick
         *
         * @param { Object }
         */
        showCompositeInfo(audienceBrick) {
            this.selectedCompositeBrick = audienceBrick;
            $("#audience-composite").foundation("reveal", "open"); // Show modal
        },

        /**
         * Return a link to the audience homepage when available
         *
         * @param { Object }
         *
         * @returns { String }
         */
        brickHref(audienceBrick) {
            if (audienceBrick.getDimensions().length !== 1) {
                // Only bricks with a single folder dimensions, and a single value, have a href
                return "";
            }
            const soleDimension = audienceBrick.getDimensions()[0];
            if (soleDimension.getEntityType() === "folder" && soleDimension.getEntityValues().length === 1) {
                const decodedValue = codec.decodeEncodedValue(soleDimension.getEntityValues()[0]);
                return typeof decodedValue == "string" ? "/homepage/" + decodedValue : "";
            }
            return "";
        },

        /**
         * To return the name of the audience
         *
         * @param { Object }
         *
         * @returns { String }
         */
        brickName(audienceBrick) {
            return audienceBrick.modifierLabel.length > 0
                ? audienceBrick.nameLabel + " " + audienceBrick.modifierLabel
                : audienceBrick.nameLabel;
        },
    },
};
</script>

<template>
    <span v-if="audienceBricks.length > 0">
        <span v-for="(audienceBrick, index) in audienceBricks" class="pipe meta" :key="index">
            <span v-if="audienceBrick.isComposite()" class="meta">
                <a
                    href="#"
                    title="Click to view audience information"
                    v-if="audienceBrick.isComposite()"
                    @click.prevent="showCompositeInfo(audienceBrick)"
                    >{{ audienceBrick.nameLabel }}</a
                >
            </span>
            <span v-else-if="brickHref(audienceBrick)"
                ><a :href="brickHref(audienceBrick)">{{ brickName(audienceBrick) }}</a></span
            >
            <span v-else>{{ brickName(audienceBrick) }}</span>
        </span>
        <div data-reveal id="audience-composite" class="reveal-modal">
            <a class="close-reveal-modal" aria-label="Close">×</a>
            <p v-if="selectedCompositeBrick.modalData">
                This portion of the audience consists of users who are in <strong>both</strong> one or more of these
                <strong>{{ selectedCompositeBrick.modalData[0].typeLabel.plural.toLowerCase() }}</strong> and one or
                more of these <strong>{{ selectedCompositeBrick.modalData[1].typeLabel.plural.toLowerCase() }}</strong
                >.
            </p>
            <hr />
            <div class="row">
                <div
                    class="small-12 medium-6 columns no-pad audience-composite-row"
                    v-for="(dimensionData, index) in selectedCompositeBrick.modalData"
                    :key="index"
                >
                    <p>
                        <strong>{{ dimensionData.typeLabel.plural }}</strong>
                    </p>
                    <ul>
                        <li v-for="(label, index) in dimensionData.valueLabels" :key="index">{{ label }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </span>
</template>
