import '../www/static/css/core/chromeFoundation.css'; //currently webpack with npm run watch is compiling faster than the below
// import '!style-loader!css-loader!sass-loader!../www/static/css/foundation/chromeFoundation.scss';

export const parameters = {
  actions: { argTypesRegex: "^on[A-Z].*" },
  controls: {
    matchers: {
      color: /(background|color)$/i,
      date: /Date$/,
    },
  },
}