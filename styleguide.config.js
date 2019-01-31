module.exports = {
  components: "www/javascript/react-components/**/*.tsx",
  serverPort: 8883,
  pagePerSection: true,
  assetsDir: "www",
  propsParser: require("react-docgen-typescript").withDefaultConfig().parse,
  template: {
    head: {
      links: [
        {
          rel: "stylesheet",
          href: "css/demo/chrome-foundation.css"
        }
      ]
    }
  },
  sections: [
    {
      name: "Cards",
      sectionDepth: 1,
      components: "www/javascript/react-components/Card/**/*.tsx"
    },
    {
      name: "Other",
      sectionDepth: 1,
      components: "www/javascript/react-components/**/*.tsx",
      ignore: "www/javascript/react-components/Card/**/*.tsx"
    }
  ]
};
