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

### Topics
A reference list of Topics that were configured by the Superuser in admin. Only Superusers can alter this list.

## Content Creators' Workflows
There are currently two workflows for content creators: Moderated and Unmoderated. The sequence of events in both are listed below and are described from a Content Creator's perspective.

Both workflows are illustrated with a railway at the top of every relevant screen. Audience members won't see this. If you hover over each node you'll get some additional information about what happens at every stage.

Content Creators start at /comms/news/create

## Draft Interval
This isn't listed as a publicly-facing setting, but the default autosave interval is 5 seconds. We can expose this as a configurable control later, if desired.


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
There is only one prescribed flow.

> Compose > Preview > Awaiting Moderation > Awaiting Publication > Published > Expired

It begins at the Moderation Queue found at /comms/news/moderation. From here you can view a list of articles that require moderation in accordance with the current Policy settings; it also shows you a list of recently-moderated articles where applicable.

* *View* a list of articles requiring Moderation.
* *Preview* an article of your choosing.
  * You can *Update* its contents, if applicable/necessary. It'll automatically save as you create.
* *Publish* the article to make it available to its audience when the Publish Date is reached.
* You can *Reject* an article at any time before it's published to the audience.
* You may *Delete* an article at any time.

### Edge Cases
**Trapped Between Settings**. This occurs when moderation is activated, an article is submitted for moderation, but the moderation requirement is then deactivated. This leaves submitted articles in an inconclusive state of being, requiring moderators to accept these articles as if moderation was activated.

**Moderator Authors**. Moderators cannot reject their own articles.