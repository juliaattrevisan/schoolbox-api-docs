Loyola College Personal Learning Backpack
===

See [SA-2758](https://schoolbox.atlassian.net/browse/SA-2758)
for more information on this custom feature.

Custom CSS
---

If LC wants an icon for this component , they will need to add this custom CSS:

    .icon-masonry-Custom_Lc_Survey_Component_Eportfolio_Controller:before {
      @extend .icon-surveys;
      color: $icon-color-blog;
    }
