<script>

import axios from 'axios';

export default {
    name: 'textbox-list',
    props: {
        /**
         * Endpoint for fetching textbox usages.
         */
        textboxUsageEndpoint: {
            type: String,
            required: true,
        },

        /**
         * Endpoint for posting textbox changes.
         */
        postChangesEndpoint: {
            type: String,
            required: true,
        },

        /**
         * Which folder's textboxes are being shown in this component?
         */
        folderId: {
            type: Number,
            required: true,
        },
    },
    data() {
        return {
            textboxes: [],
            loading: true,
            jQuery: window.jQuery,
        }
    },
    mounted() {
        let self = this;
        // Apply the validator to the form
        self.jQuery(self.$refs.form).alaressValidate({
            submitHandler(form, event) {
                event.preventDefault();
                self.postChanges();
            }
        });
        axios
            .get(self.textboxUsageEndpoint)
            .then(function (response) {
                self.textboxes = response.data;

                // Apply the collapser
                self.$nextTick(() => self.jQuery('[data-collapser^="collapser-textbox"]').collapsable());
            });
    },
    updated() {
        let self = this;
        // Apply the validation rule to each input
        self.jQuery(self.$refs.form).find('input[type=text]').each(function(index, element) {
            $(element).rules('add', {
                requiredNotBlank: true
            });
        });
    },
    methods: {
        /**
         * Does this textbox usage record contain a usage for this component's
         * folder?
         *
         * Show it as "this folder" in the list of pages instead.
         *
         * @param {Object} usage
         * @returns {Boolean}
         */
        inUseOnThisFolder(usage) {
            return usage.folders.some((folder) => folder.id === this.folderId);
        },

        /**
         * The folders other than the this component which this textbox is
         * being used on.
         *
         * @param {Object} usage
         * @returns {Array}
         */
        otherFolders(usage) {
            return usage.folders.filter((folder) => folder.id !== this.folderId);
        },

        /**
         * Post changes to the list of textboxes.
         */
        postChanges() {
            // Post changes here?
        },
    },
    computed: {
    },
    filters: {}
}

</script>
<template>
    <form method="post" ref="form" :action="postChangesEndpoint">
        <section>
            <ul class="information-list unsortable expandable-card">
                <li v-for="(usage, index) in textboxes">
                    <div class="actions-small-1">
                        <div class="expander" :data-collapser="'collapser-textbox-' + String(index)"></div>
                        <div class="list-item">
                            <div class="small-12 column">
                                <label>
                                    <span class="hide-for-medium-up">Textbox</span>
                                    <input :name="'textbox[' + String(index) + '][heading]'" type="text" v-model="usage.textbox.heading">
                                </label>

                                <p class="meta" v-if="usage.folders.length > 0">
                                    Pages:
                                    <template v-if="inUseOnThisFolder(usage)">
                                        this page<template v-if="otherFolders(usage).length > 0">, </template>
                                    </template>
                                    <template v-for="(folder, index) in otherFolders(usage)">
                                        <a :href="folder._links.view">{{ folder.name }}</a><template v-if="(index + 1) < otherFolders(usage).length">, </template>
                                    </template>
                                </p>

                                <p class="meta" v-if="usage.dashboards.length > 0">
                                    Dashboards: {{ usage.dashboards.map((v) => v.name).join(', ') }}
                                </p>
                            </div>
                        </div>
                        <nav>
                            <a class="icon-delete" :href="usage.textbox._links.delete" title="Delete"></a>
                        </nav>
                    </div>
                    <div class="hide actions-small-1" data-collapse-state="collapsed" :data-collapsable="'collapser-textbox-' + String(index)">
                        <article class="content" v-html="usage.textbox.body">
                        </article>
                    </div>
                </li>
            </ul>

            <input type="submit" value="Save" />
        </section>
    </form>
</template>
