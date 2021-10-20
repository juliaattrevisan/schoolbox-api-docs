import BottomDrawer from './bottom-drawer';

// More on default export: https://storybook.js.org/docs/vue/writing-stories/introduction#default-export
export default {
  title: 'Schoolbox/Bottom Drawer',
  component: BottomDrawer,
};

const Template = (args, { argTypes }) => ({
  props: Object.keys(argTypes),
  components: { BottomDrawer },
  template:
    '<bottom-drawer><p>Hey there!</p></bottom-drawer>',
});

export const HideDrawer = Template.bind({});
HideDrawer.args = {
  showDrawer: false,
};

export const ShowDrawer = Template.bind({});
HideDrawer.args = {
  showDrawer: true,
};