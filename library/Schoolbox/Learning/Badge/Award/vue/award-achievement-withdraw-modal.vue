<script>
import BadgeImage from "./../../vue/badge-image";
import RevealModal from "@schoolboxVue/components/reveal-modal";
import toastMixin from "@schoolboxVue/components/toast-mixin";

export default {
    /**
     * Name.
     * The name of the component that we have here. This name will be used in the container
     * to spawn this component.
     */
    name: "award-withdraw-modal",

    /**
     * Mixins.
     * Basically traits for vue; allows you to share javascript between components in the same
     * manner as a php trait. They must already be imported at the top of the file and will
     * be superpositioned on top of this javascript here, inside export default {}
     */
    mixins: [toastMixin],

    /**
     * Props.
     * Immutable props that were passed to this component, usually via twig, in our setup.
     * These are the hyphen-separated vars which are magically computed into camelcase elsewhere.
     */
    props: [],

    /**
     * Components.
     * Any other vue components you also want to use here; they must already be imported
     * at the top of the file.
     */
    components: {
        BadgeImage,
        RevealModal,
    },

    /**
     * Data.
     * Mutable properties that can be accessed from within this component via "this" keyword
     * Note that v-if and v-for must never be on the same node together, as this overrides
     * the meaning of "this" in a way you don't expect.
     */
    data() {
        return {
            award: {},
        };
    },

    /**
     * Mounted.
     * Basically, when vue has done all its inits and is ready to go
     * kind of like window.onload
     */
    mounted: function () {},

    /**
     * Computed properties.
     * Access the results of a function as if they were a property of an object.
     */
    computed: {},

    /**
     * Methods.
     * Arbitrary javascript functions which operate on the args provided, just like any
     * other ordinary javascript that you might have written before.
     */
    methods: {
        /**
         * Open this modal.
         */
        open(award) {
            this.award = award;

            this.$refs.modal.open();
        },

        /**
         * Close this modal
         */
        close() {
            this.$refs.modal.close();
        },

        /**
         * Perform withdrawal
         */
        async withdraw() {
            this.toastActivate("Withdrawing achievement...");
            try {
                const response = await fetch(this.award._links.achievementWithdraw, {
                    method: "POST",
                    body: this.getFormData(this.award.recipient.id),
                });

                /* This is to prevent 'Fetch failed loading' in the console, as nothing is returned and Fetch currently doesn't like that
                 */
                await response.text();

                /* Send bat signal to the parent component
                 */
                this.$emit("withdraw-complete");

                /* Close this modal
                 */
                this.close();

                /* Pop toast
                 */
                this.toastSuccess("Withdraw complete");
            } catch (error) {
                if (error.response) {
                    if (400 === error.response.status) {
                        /* Goal prevents achievement from being withdrawn
                         */
                        self.toastError(
                            "Unable to withdraw achievement; a goal might exist and should be withdrawn first.",
                        );
                    } else {
                        /* Generic error
                         */
                        self.toastError("Unable to withdraw achievement; please try again.");
                    }
                }
                console.warn(error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Put in the award id to withdraw
         */
        getFormData(userId) {
            const formData = new FormData();
            formData.append("userIds[0]", userId);
            return formData;
        },
    },
};
</script>
<template>
    <reveal-modal ref="modal">
        <template v-if="award.badge">
            <h2>
                Withdraw <strong>'{{ award.badge.name }}'</strong>
            </h2>

            <div class="compact card small-12 badge-card">
                <div>
                    <badge-image :badge="award.badge"></badge-image>
                    <h3>{{ award.badge.name }}</h3>
                </div>
            </div>

            <p>Are you sure you want to withdraw this achievement from {{ award.recipient.fullName }}?</p>
            <ul class="flex-list buttons">
                <li><a @click.prevent="withdraw()" class="button submit">Withdraw</a></li>
                <li><a @click.prevent="close()" class="button">Cancel</a></li>
            </ul>
        </template>
    </reveal-modal>
</template>
