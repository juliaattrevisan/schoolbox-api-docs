# ePortfolio Data Retention Design

## Context

Jira SA-21885 asks for a data retention strategy that permanently removes soft-deleted ePortfolio data once the configured ePortfolio retention period has elapsed.

The current PR introduces `Schoolbox\Admin\DataRetention\Strategy\Eportfolio` and the `ValidStrats::EPORTFOLIO` constant. The strategy should fit the existing data retention CLI framework, including dry-run mode, perform mode, transactions per chunk, retention period override, configured retention period lookup, chunk size, loop limit, and process locking.

## Goal

Add an ePortfolio strategy that:

- Finds soft-deleted ePortfolio pages older than the retention cutoff and hard-deletes those pages with their page-owned data.
- Finds independently soft-deleted ePortfolio child rows older than the cutoff and hard-deletes those rows even when their parent page remains active.
- Deletes related rows in dependency order so foreign key constraints are not violated.
- Leaves external ownership tables alone, especially My Files and storage records.

## Relevant Tables

```text
eportfolio_page.page_id
|-- eportfolio_permission.page_id
|-- eportfolio_component_blog.page_id
|   |-- eportfolio_component_blog_comment.blog_id
|   `-- eportfolio_component_blog_image.blog_id
|       `-- storage.hash (referenced only; not deleted by this strategy)
|-- eportfolio_component_filelist_item.page_id
|   `-- my_file.id
`-- component_instance.identifier
    |-- component_config_instance.component_instance_id
    |-- eportfolio_component_advancedlist_item.component_instance_id
    |   `-- storage.hash (referenced only; not deleted by this strategy)
    |-- eportfolio_component_basiclist_item.component_instance_id
    |-- eportfolio_component_linklist_item.component_instance_id
    `-- eportfolio_component_textbox_item.component_instance_id
```

`component_instance.identifier` is a polymorphic link, not a database foreign key. For this strategy it should only be treated as an ePortfolio page link when `component_instance.module = 'eportfolio'`.

## Selection Model

Use a split selection model.

The page cascade selection finds `eportfolio_page` rows where:

```sql
deleted_at IS NOT NULL
AND deleted_at < :old
```

Independent child cleanup finds rows from tables that have their own `deleted_at` value:

- `eportfolio_component_blog`
- `eportfolio_component_blog_comment`
- `eportfolio_component_blog_image`
- `eportfolio_component_filelist_item`

Independent cleanup should exclude rows already covered by the page cascade. Blog comments and blog images should also exclude rows whose blog parent is already covered by the same chunk's blog deletion set.

Component-instance-owned rows do not have an independent soft-delete marker, so they are deleted only through a page cascade:

- `component_instance`
- `component_config_instance`
- `eportfolio_component_advancedlist_item`
- `eportfolio_component_basiclist_item`
- `eportfolio_component_linklist_item`
- `eportfolio_component_textbox_item`

## Chunk Shape

`getIdsToDelete()` should return root delete targets. Each target should identify the source table and primary key:

```php
[
    ['table' => self::TABLE_EPORTFOLIO_PAGE, 'id' => 123],
    ['table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG, 'id' => 456],
    ['table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG_COMMENT, 'id' => 789],
    ['table' => self::TABLE_EPORTFOLIO_COMPONENT_BLOG_IMAGE, 'id' => 321],
    ['table' => self::TABLE_EPORTFOLIO_COMPONENT_FILELIST_ITEM, 'id' => 654],
]
```

Use table-name constants as the canonical values to avoid repeated string literals. This follows the local `MyFiles` strategy pattern while avoiding a second vocabulary of custom type names.

## Chunk Semantics

The CLI `--chunk` value limits the number of root delete targets processed per transaction. It does not limit the total number of physical rows deleted after tree expansion.

For example, a chunk of 1000 root targets may expand to more than 1000 physical deletes when those targets include ePortfolio pages with blogs, permissions, file lists, and component instances. A single page cascade should remain atomic inside one chunk rather than being split across multiple transactions.

Operators can reduce `--chunk` for unusually dense ePortfolio data. The strategy should log both root target counts and per-table row counts so transaction size is visible in dry-run and perform modes.

## Deletion Order

For page cascades, delete leaf rows before parent rows:

1. Find `component_instance.id` values for page IDs where `module = 'eportfolio'`.
2. Delete component child rows:
   - `component_config_instance`
   - `eportfolio_component_advancedlist_item`
   - `eportfolio_component_basiclist_item`
   - `eportfolio_component_linklist_item`
   - `eportfolio_component_textbox_item`
3. Delete blog children for page IDs:
   - `eportfolio_component_blog_comment`
   - `eportfolio_component_blog_image`
4. Delete page-owned rows:
   - `eportfolio_component_blog`
   - `eportfolio_component_filelist_item`
   - `eportfolio_permission`
5. Delete `component_instance` rows.
6. Delete `eportfolio_page` rows.

For independent child cleanup:

1. Delete eligible `eportfolio_component_blog_comment` rows.
2. Delete eligible `eportfolio_component_blog_image` rows.
3. Delete eligible `eportfolio_component_filelist_item` rows.
4. Delete eligible `eportfolio_component_blog` rows after their comments and images have been removed.

## Safety Rules

- Use the existing `Base` transaction model.
- Use `Base::deleteStatement()` so dry-run mode selects rows and perform mode deletes rows.
- Use placeholder arrays from `Alaress\Database\Predicate\Builder` for all ID lists.
- Return early from delete helpers when an ID list is empty.
- Do not delete rows from `my_file`, `storage`, or `user`.
- Do not split one page cascade across chunks.
- Keep page cascade expansion inside `deleteChunk()` so chunking remains based on root targets.

## Logging

`hasDataToDelete()` should log the number of eligible root targets and the related data counts that can be known cheaply. `deleteChunk()` should log:

- Root target IDs by table at debug level.
- Per-table row counts deleted or selected in dry-run mode.
- Whether the chunk was run in dry-run or perform mode.

## Tests

Add focused PHPUnit coverage for `Strategy\Eportfolio`.

Test behaviours:

- An eligible soft-deleted page cascades through all page-owned tables.
- An active page is preserved while an eligible soft-deleted blog is removed.
- An active page is preserved while an eligible soft-deleted filelist item is removed.
- Blog comments and images are removed before their eligible blog parent.
- Child rows under an eligible soft-deleted page are not double-counted as independent delete targets.
- `my_file` and `storage` rows are never deleted by this strategy.
- `ValidStrats::EPORTFOLIO` is present in `STRATS_WHITELIST`.
- The command-line bus instantiates the ePortfolio strategy when the strategy is enabled.

## Non-Goals

- Do not introduce a generic graph-based delete planner.
- Do not clean up My Files records linked from ePortfolio content.
- Do not clean up storage records referenced by ePortfolio content.
- Do not alter ePortfolio schema or foreign keys as part of this PR.
