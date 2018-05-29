# Business as Usual (BAU)

## BAU duties

Possible jobs to undertake when performing BAU.

### Bring Unconfirmeds to Ready

Go through tickets in Jira in the 'Unconfirmed' state, bringing them to Ready by confirming they exist, setting priority based on the rubric below, and curating them to meet our
[Definition of Ready](definition-of-ready.md).

### Clean and curate old tickets

Go through older tickets in Jira, confirming that they still exist, bringing them to Ready, closing duplicates.
    
### Sentry Duty

Go through the backlog of unresolved issues in [Sentry](https://sentry.io/), determining severity and automatically creating Jira issues from any that are deemed to require a fix.

### Coding fixes for Ready bugs

Write fixes for Bugs in the Ready state, ideally ones that you have not curated yourself.    

## Priority Rubric

 Question | Low | Medium | High
 --- | --- | --- | ---
1. How much damage does this cause? | No data damage (or only informative text is wrong), or no difficulty in entering data | Data is misreported (calculations are wrong), but usable, or can be created with difficulty | Data becomes unusable or corrupt, or uncreatable, or data that should be private is exposed
2. How disruptive is this issue? | Users aren't diminished in ability to perform any functions | Users can perform core functions, in a diminished capacity | Users can't complete core functions of system  
3. How many people does it affect? | Only a few people are affected | All users, or a key constituency of users, are affected

Priority | Criteria
--- | ---
High | 1 or more highs
Med | 2 or more mediums
Low | 1 medium
Lowest | All lows
