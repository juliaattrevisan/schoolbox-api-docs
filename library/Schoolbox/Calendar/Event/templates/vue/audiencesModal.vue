<template>
    <div>
        <reveal-modal ref="modal">
            <div class="small-12 medium-6 columns no-pad">
                <p><strong>Campuses</strong></p>
                <ul>
                    <li v-for="campus in campuses">
                        {{ campus.name }}
                    </li>
                </ul>
            </div>
            <div class="small-12 medium-6 columns no-pad">
                <p><strong>Roles</strong></p>
                <ul>
                    <li v-for="role in roles">
                        {{ role.name }}
                    </li>
                </ul>
            </div>
        </reveal-modal>
    </div>
</template>

<script>
    import RevealModal from "@schoolbox/reveal-modal";

    export default {
        name: "audiences-modal",
        props: ['campus-audiences', 'role-audiences'],
        components: {RevealModal},
        data() {
            return {
                campuses: this.campusAudiences,
                roles: this.roleAudiences,
            }
        },
        methods: {
            open() {
                this.$refs.modal.open();
            },
        }
    }
</script>

<style scoped>

</style>
