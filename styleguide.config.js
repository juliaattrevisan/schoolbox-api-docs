module.exports = {
  components: "src/components/**/*.tsx",
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
      sectionDepth: 1,
      sections: [
        {
          name: "Stack",
          content: "docs/front-end/prologue/stack.md"
        },
        {
          name: "Directory Structure",
          content: "docs/front-end/prologue/directory-structure.md"
        }
      ]
    },
    {
      name: "Getting Started",
      sectionDepth: 1,
      sections: [
        {
          name: "Developing Components",
          content: "docs/front-end/getting-started/developing-components.md"
        },
        {
          name: "Creating Pages",
          content: "docs/front-end/getting-started/developing-in-sb.md"
        }
      ]
    },
    {
      name: "Components",
      components: "src/components/**/*.tsx",
      ignore: "src/components/Card/**/*.tsx",
      sectionDepth: 1,
      sections: [
        {
          name: "Cards",
          content: "src/components/Card/Cards.md",
          components: [
            "src/components/Card/Card/*.tsx",
            "src/components/Card/LoadingCard/*.tsx"
          ]
        }
      ]
    }
  ]
};
