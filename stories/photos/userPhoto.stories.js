import UserPhoto from "../../vue/components/userPhoto/user-photo.vue";

export default {
    title: "Photos/UserPhoto",
    component: UserPhoto,
};

const Template = (args, { argTypes }) => ({
    components: { UserPhoto },
    template: `
        <user-photo />
        `,
});

export const Default = Template.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
