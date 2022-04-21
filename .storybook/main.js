module.exports = {
  addons: [
    '@storybook/addon-links',
    '@storybook/addon-essentials'
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