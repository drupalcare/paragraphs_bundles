# The Paragraphs List index

`/admin/content/paragraphs/paragraphs-list` is answered from one derived table,
`paragraphs_bundles_list_index`, so a page costs the same however many
paragraphs the site holds. (Checked here with `EXPLAIN` on a 1,003-paragraph
site; the "contains" searches below are the named exceptions.)

## What the page does

- Columns: **PID**, a select box, **Type**, **NID**, **Content title** (linked
  to the node), **Label**, **Parent**, **Edit**. PID, the select box and Edit are
  always shown; the other five can be hidden with the checkboxes above the table
  (JavaScript; the choice is remembered in the browser).
- **Sort** by PID, Type, NID, Content title and Label. Under Content title,
  paragraphs nothing uses (no title) always come last, in both directions.
- **Filter** by type; **search** by content title ("starts with", or "contains",
  slower) and by label ("contains", slower).
- **Previous / Next** paging (keyset), 50 per page, in the address so a
  position can be bookmarked. There are no page numbers.
- The count "Displaying N out of M" is cached until the index changes.
- Access: the list needs the **`access paragraphs list`** permission
  (restricted; the list shows unpublished paragraphs too). Edit and delete also
  need entity update access, which Paragraphs answers from the host content;
  a paragraph nothing uses is editable with the permission alone. A row you may
  not update has no usable select box and no Edit link, and Delete Selected
  re-checks every id on the server.

## The rule

Every query is answered by an index and touches one page of rows, except:

- **Content title "contains"** and **Label "contains"** scan the index table's
  short strings (`LIKE '%x%'` cannot use an index).
- A page of a "starts with" title search is a range on the `root_title` index;
  with a non-default sort it may sort the matching rows.

## The table

| Column | Meaning |
|---|---|
| `pid` | paragraph id (primary key) |
| `bundle` | paragraph type |
| `root_type`, `root_id` | the entity at the top of the chain (`node`, `block_content`, ...); empty and 0 when nothing uses the paragraph |
| `depth` | 1 = direct child of the root; 0 when orphaned |
| `langcode` | the paragraph's default language |
| `label` | derived copy of `Paragraph::label()`, first 191 characters |
| `root_title` | derived copy of the root entity's label in its default translation, first 191 characters; empty when orphaned |

It is **derived**: nothing edits it by hand. The paragraphs' own `parent_type` /
`parent_id` columns are the source, and the verify command checks every column.
Titles are copied (`root_title`) so Content title can be sorted and searched
from the index without a join; the page still loads the full node title for
display.

`label` and `root_title` are 191 characters because Drupal's MySQL driver
prefixes any longer index at 191, and a prefix index cannot serve `ORDER BY`.
The indexes are `bundle_pid`, `root_pid` (root_type, root_id, pid), `label_pid`
and `root_title_pid`.

## How it stays true

- Saving any entity with a paragraphs field rebuilds that host's whole tree
  (walking down the indexed parent columns), so renaming a node rewrites
  `root_title` and `label` on every row of that tree. The hooks run after
  `entity_reference_revisions` has set the children's parent id, so a new node is
  indexed with its real id.
- Saving a paragraph rewrites its own row and its descendants'.
- Deleting a paragraph removes its row. `entity_reference_revisions` deletes a
  deleted host's paragraphs later, from its orphan-purger queue; until then their
  rows say "orphan", which is what they are.
- Renaming a paragraphs field or a paragraph type queues a chunked rebuild
  (queue `paragraphs_bundles_list_index_rebuild`, run by cron). A change asked
  for while one is running makes it go round again.
- An index failure during a save is logged and queues a rebuild; it never blocks
  the save. If the table does not exist yet (code deployed, `updb` not run),
  saving works and the page tells you to run the database updates.

## Upgrading

`drush updb` creates the table (update 9010), adds `root_title` to a table made
before it existed (9011), and the post-updates fill it, 500 paragraphs a step.
Orphan labels contain a paragraph summary that cannot be rendered during
database updates, so a clean pass is queued; run it at once with the rebuild
command below, or let cron do it.

## Commands

```
drush paragraphs-bundles:list-index:rebuild [--chunk=500] [--from=<pid>]
drush paragraphs-bundles:list-index:verify  [--all] [--sample=200]
```

`rebuild` walks the paragraphs by primary-key range in id order, rewrites each
row and drops rows of deleted paragraphs; interrupt it and resume with `--from`.
`verify` compares the table with a fresh derivation and exits non-zero on any
missing, extra or different row. `--all` is the authority; the default sample
is a quick check and can miss a problem.

## Not done, on purpose

- No "page 7 of 20,000": keyset paging has no page numbers.
- Translations: one row per paragraph; titles and labels are the default
  language's.
