# SA-5681 Comms/News
The News upgrade which introduces new features and audiencing specificity.

## Comms Officers
*Comms (Communications) Officers* are a new Service Permission that is intended to sit between Superusers and existing News Moderators. 

You can assign Comms Officers by modifying users and giving them the Comms Officer Service Permission. We envisage this occurring only a couple times; it shouldn't be a regularly undertaken action.

* Comms Officers are super-moderators whose responsibilities include proofreading news articles, editing them, enforcing standards (where applicable) and general maintenance for content creators.

* Comms Officers can establish Policies which enable system behaviours and workflows.

* Comms Officers are universal; they operate across all campuses in all respects.

* Comms Officers are typically Staff members. However, any other role type can be given this Service Permission such as Students and Parents.

## Policies
*Comms Policies* are a set of configurable options available to Comms Officers. These alter the behaviour of various parts of the News workflow. There are only a few at the moment; more will be introduced as required.

The Comms Policy screen is located at /comms/news/policy

* Only Comms Officers can change the Policy settings and view this screen in a configurable manner.

* All other non-comms-officer staff members can view a read-only version of the Policy settings, as a reference.

### Global Moderation
Activating this will force all articles to require a Moderator's approval before it can be made available to the audience.

Deactivating this will enable content creators to be their own Moderators.

### Publish Date Offset
Setting a value here will enable the Publish Date to have a default offset from the time of creation. It only suggests a Publish Date; content creators are free to change this as they desire.

Leaving this empty will cause articles to be published immediately.

### Expiry Date Offset
Setting a value here will enable the Expiry Date to have a default offset from the time of creation. It only suggests an Expiry Date; content creators are free to change this as they desire.

Leaving this empty will cause articles to expire one week from time of creation.

### "Read More" Link
Some news articles are quite long - even in their blurbs - and they get truncated for brevity in some displays such as email digests, components, etc. Truncation occurs after 300 characters and to the nearest word. Unfortunately some users don't realise that there is more to the article than they can see; there is no prompt to "read more". 

Providing a value for this policy will enable a link to prompt them to "read more" but you can use any phrase you want. 

Leaving it empty will disable the link from being available. 

### Save For Later
This is an in-built bookmarking system specifically for news articles. Activating this policy allows users to add articles to their lists. 

Deactivating this will prevent articles from being added to their lists, but their previously-saved articles will still be available and removable.

### Topics
A reference list of Topics that were configured by the Superuser in admin. Only Superusers can alter this list.

## Content Creators' Workflows
There are currently two workflows for content creators: Moderated and Unmoderated. The sequence of events in both are listed below and are described from a Content Creator's perspective.

Both workflows are illustrated with a railway at the top of every relevant screen. Audience members won't see this. If you hover over each node you'll get some additional information about what happens at every stage.

Content Creators start at /comms/news/create

### Draft Interval
This isn't listed as a publicly-facing setting, but the default autosave interval is 5 seconds. We can expose this as a configurable control later, if desired.

### Actions
Some actions require certain preconditions to exist.
* To create an article, you don't actually need any content. You can create placeholder articles.
* To edit an article, you must be considered its author and the article must not be accepted for publication.
* To submit an article for moderation, you must have a title, publish and expire dates.
* To publish an article to its audience, moderation must be deactivated; technically, this assumes the author to be the moderator (thus, self moderating) but operationally allows you to choose when to make it available.
* To publish an article to its audience, the article must have a title, publish date and expiry date.
* To delete an article, you must be considered its author and the article must not be accepted for publication.

### Moderated Workflow
This workflow requires more people and infrastructure to introduce more control over content creators.

> Compose > Preview > Awaiting Moderation > Awaiting Publication > Published > Expired

* *Create* news article with the content you want. It'll automatically save as you create.
* Visit the *Preview* screen where you can see what you've made so far.
  * Go back to *Update* anything, if applicable/necessary, then revisit the *Preview* screen.
* *Submit For Moderation* so that the Moderator can examine your creation. You can still change its contents.
* At this point a Moderator will *View* the article you created; you don't have to do anything.
  * Moderators may also change your content as required.
* If the Moderator accepts your article, they will *Publish* it, making it available to the audience on the date specified. After Moderators conduct this action you won't be able to change or delete the article.
  * Alternatively, a Moderator may *Reject* your article and send it back into the *Create* phase where you'll see the reason why it was rejected.
  * Alternatively, a Moderator may *Delete* your article.
* The article is *Archived* once the Expiry Date is reached.

### Unmoderated Workflow
This workflow imitates the current behaviour of Schoolbox. It is far more liberal, for better or worse.

> Compose > Preview > Awaiting Publication > Published > Expired

* *Create* news article with the content you want. It'll automatically save as you create.
* Visit the *Preview* screen where you can see what you've made so far.
  * Go back to *Update* anything, if applicable/necessary, then revisit the *Preview* screen.
* *Publish* the article to make it available to the audience on the date specified.
  * Articles that were created when Moderation was required will still require moderation even if the policy is deactivated.
  * Articles created when Moderation wasn't required will be considered self-moderated by the author.
* The article is *Archived* once the Expiry Date is reached.
* You may *Delete* your article at any time.

## Moderators' Workflow
There is only one prescribed flow when moderation is required.

> Compose > Preview > Awaiting Moderation > Awaiting Publication > Published > Expired

It begins at the Requiring Moderation page found at /comms/news/moderation. From here you can view a list of articles that require moderation in accordance with the current Policy settings; it also shows you a list of recently-moderated articles where applicable.

* *View* a list of articles requiring Moderation.
* *Preview* an article of your choosing.
  * You can *Update* its contents, if applicable/necessary. It'll automatically save as you create.
* *Publish* the article to make it available to its audience when the Publish Date is reached.
* You can *Reject* a submitted article at any time.
* You may *Delete* an article at any time.

### Actions
Some actions require certain preconditions to exist.
* To accept an article for publication to its audience, it must have a title, publish and expire dates and be accepted by a moderator.
* To reject an article, it be submitted for moderation and you are not its author. You can't reject draft articles.

### Edge Cases
**Limbo (Trapped Between Settings)**. This occurs when moderation is activated, an article is submitted for moderation, but the moderation requirement is then deactivated. This leaves submitted articles in an inconclusive state of being, requiring moderators to accept these articles as if moderation was activated. This can be avoided by clearing the moderation list before deactivating moderation.

**Moderator Authors**. Moderators cannot reject their own articles.


## Known Issues
There a number of known issues.

* **Selectable Topics**. On the create/update screen, the Topics selectize doesn't show you all the options you can choose when you click into its input field.

* **Topics don't initiate drafts**. For some reason changing the Topics relating to an article isn't being detected and won't trigger a draft autosave on the create/update screen.

* **Uncached results**. Every time you click a tab in the News Component, it'll asynchronously retrieve the appropriate set of articles. I did have a dirty fling with caching at @andrewryantech request, but I found myself stuck on Save For Later. This is a toggle and articles can appear across multiple topics; this requires some bmf w.r.t. cache invalidation (it was able to remember the previous state of an article that you Saved For Later, which was very confusing)

* **Hiding empty topics**. Without some bmf I can't really hide topics that have no articles. This is because the articles available in each topic are determined by audiencing logic; this is costly regardless of how many articles could be retrieved. To hide topics that have no content that you can access means I have to do audiencing checks on every article in every topic to determine their potential visibility to the current user. For now, the News Component hides when there are exactly 0 articles in the db. Otherwise it'll do its aforementioned async behaviour and return an empty state when there was nothing to see.

