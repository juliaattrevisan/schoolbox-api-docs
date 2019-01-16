module.exports = {
  components: "www/javascript/react-components/**/*.js",
  serverPort: 8883,
  pagePerSection: true,
  assetsDir: "www",
  template: {
    head: {
      links: [
        {
          rel: "stylesheet",
          href: "css/demo/chrome-foundation.css"
        }
      ]
    }
  }
};
