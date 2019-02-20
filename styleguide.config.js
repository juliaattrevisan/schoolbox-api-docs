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
      components: "react/components/**/*.tsx",
      ignore: [
        "react/components/Card/**/*.tsx",
        "react/components/Tabs/**/*.tsx",
        "react/components/Actions/**/*.tsx",
        "react/components/InformationList/**/*.tsx"
      ],
      sectionDepth: 1,
      sections: [
        {
          name: "Cards",
          content: "react/components/Card/Cards.md",
          components: [
            "react/components/Card/Card/**/*.tsx",
            "react/components/Card/CardList/**/*.tsx"
          ]
        },
        {
          name: "Tabs",
          components: [
            "react/components/Tabs/Tabs/*.tsx",
            "react/components/Tabs/LoadingTabs/*.tsx",
            "react/components/Tabs/Tab/*.tsx"
          ]
        },
        {
          name: "Actions",
          components: "react/components/Actions/**/*.tsx",
          components: [
            "react/components/Actions/Actions/*.tsx",
            "react/components/Actions/Action/*.tsx"
          ]
        },
        {
          name: "Information List",
          components: "react/components/InformationList/**/*.tsx"
        }
      ]
    }
  ]
};
