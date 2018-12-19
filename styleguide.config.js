const parserOptions = {};

module.exports = {
  components: "www/javascript/react-components/**/*.js",
  ignore: ["www/javascript/react-components/schoolbox.js"],
  serverPort: 8883,
  pagePerSection: true,
  template: {
    head: {
      links: [
        {
          rel: "stylesheet",
          href: "www/css/demo/chrome-foundation.css"
        }
      ]
    }
  }
};
