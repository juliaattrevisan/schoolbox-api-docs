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
      name: "Introduction",
      content: "docs/front-end/introduction.md"
    },
    {
      name: "Components",
      components: "www/javascript/react-components/**/*.tsx",
      ignore: "www/javascript/react-components/Cards/**/*.tsx",
      sectionDepth: 1,
      sections: [
        {
          name: "Cards",
          components: "www/javascript/react-components/Cards/**/*.tsx",
          content: "www/javascript/react-components/Cards/Cards.md"
        }
      ]
    }
  ]
};
