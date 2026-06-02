# Point Release Notes

Use this pathway when preparing Schoolbox point release notes from a Jira release report.

The audience is usually Schoolbox superusers, but the notes should be readable by any school staff member. Keep the language clear, practical, and focused on visible user impact.

## Source Data

1. Open the Jira release report for the target version.
2. Query all issues in the release by fix version.
3. Include only issues where the Jira `Release Notes` field has a meaningful value.
4. Exclude issues where `Release Notes` is empty or effectively `NA`, such as `NA`, `N/A`, `n/a`, or notes that say the work is internal-only.
5. Use the issue type to decide the release note section:
   - `Task` or improvement-style work belongs under `Updates / Improvements`.
   - `Bug` or fix-style work belongs under `Issues Resolved`.
6. Use the Jira `Components` field for grouping headings.
7. If the release note text is unclear, lightly review the Jira description, comments, or linked pull request to understand the user-facing impact.

## Writing Style

- Start each item with the Jira key, followed by a short plain-English sentence.
- Prefer wording like `Fixed an issue where...`, `Added...`, `Updated...`, or `Improved...`.
- Avoid internal implementation detail unless it is necessary for school staff to understand the impact.
- Do not mention package updates, automated tests, internal-only fixes, or security/CVE implementation detail unless the Jira `Release Notes` field explicitly says it should be included.
- Keep product/module names familiar to school staff, for example `Class List`, `Course Import`, `Manage Submissions`, `AI Services`, or `advanced search`.
- Preserve the release note's meaning, but rewrite awkward or technical Jira wording into staff-readable language.

## HTML Format

Use the same structure for point releases:

```html
<p>
    Release VERSION will be deployed on <strong>DAY DATE at 6PM AEST</strong>. The outage is expected to be under 1 minute.&nbsp;
</p>
<p>
    You can now do much more using Schoolbox. To find out, see:&nbsp;
</p>
<ul>
    <li>
        <a target="_blank" rel="noopener noreferrer" href="https://help.schoolbox.com.au/news/NEWS_ID#page-anchor-updates-improvements">Updates/Improvements</a>
    </li>
    <li>
        <p>
            <a target="_blank" rel="noopener noreferrer" href="https://help.schoolbox.com.au/news/NEWS_ID#page-anchor-issues-resolved-bugs">Issues Resolved</a>
        </p>
        <p>
            &nbsp;
        </p>
    </li>
</ul>
<h2 id="page-anchor-updates-improvements">
    Updates / Improvements
</h2>
<h4>
    Component:
</h4>
<ul>
    <li>
        SA-XXXXX - Staff-readable update note.
    </li>
</ul>
<p>
    &nbsp;
</p>
<h2 id="page-anchor-issues-resolved-bugs">
    Issues Resolved
</h2>
<h4>
    Component:
</h4>
<ul>
    <li>
        SA-XXXXX - Staff-readable fix note.
    </li>
</ul>
<h4>
    &nbsp;
</h4>
```

## Final Checks

- Confirm the release version and release date from Jira.
- Confirm the deployment time with the requester if it differs from the usual `6PM AEST` wording.
- Confirm every included issue has a non-`NA` Jira `Release Notes` value.
- Confirm every included issue is in the correct section based on issue type.
- Confirm every included issue is grouped under its Jira component.
- Replace `NEWS_ID` with the Schoolbox Help news article ID, or leave a clear placeholder if it is not known yet.
