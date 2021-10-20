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
  stories: [
    '../stories/**/*.stories.mdx',
    '../stories/**/*.stories.@(js|jsx|ts|tsx)',
    '../vue/components/**/*.stories.@(js|jsx|ts|tsx)',
  ],
}