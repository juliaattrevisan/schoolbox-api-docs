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
      name: "Prologue",
      sections: [
        {
          name: "Stack",
          content: "docs/front-end/prologue/stack.md"
        }
      ]
    },
    {
      name: "Getting Started",
      sectionDepth: 1,
      sections: [
        {
          name: "Developing components",
          content: "docs/front-end/getting-started/developing-components.md"
        },
        {
          name: "Creating pages on Schoolbox",
          content: "docs/front-end/getting-started/developing-in-sb.md"
        }
      ]
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
