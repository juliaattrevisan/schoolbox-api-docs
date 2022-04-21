module.exports = {
  addons: [
    '@storybook/addon-links',
    '@storybook/addon-essentials',
    '@storybook/addon-a11y',
    '@storybook/addon-storysource',
    '@storybook/addon-interactions',
  ],
  core: {
    builder: 'webpack5',
  },
  features: {
    postcss: false,
  },
  framework: '@storybook/vue3',
  staticDirs: ['../stories/assets'],
  stories: [
    '../stories/**/*.stories.mdx',
    '../stories/**/*.stories.@(js|jsx|ts|tsx)',
    '../vue/components/**/*.stories.@(js|jsx|ts|tsx)',
  ],
}