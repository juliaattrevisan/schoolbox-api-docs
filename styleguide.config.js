module.exports = {
  components: "react/components/**/*.tsx",
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
      components: "www/javascript/react-components/**/*.tsx",
      ignore: [
        "www/javascript/react-components/Card/**/*.tsx",
        "www/javascript/react-components/Tabs/**/*.tsx",
        "www/javascript/react-components/Actions/**/*.tsx",
        "www/javascript/react-components/InformationList/**/*.tsx"
      ],
      sectionDepth: 1,
      sections: [
        {
          name: "Cards",
          content: "react/components/Card/Cards.md",
          components: [
            "react/components/Card/Card/*.tsx",
            "react/components/Card/LoadingCard/*.tsx"
          ]
        },
        {
          name: "Tabs",
          components: "www/javascript/react-components/Tabs/**/*.tsx"
        },
        {
          name: "Actions",
          components: "www/javascript/react-components/Actions/**/*.tsx"
        },
        {
          name: "Information List",
          components: "www/javascript/react-components/InformationList/**/*.tsx"
        }
      ]
    }
  ]
};
