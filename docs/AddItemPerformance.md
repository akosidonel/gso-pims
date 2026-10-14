# Add item performance

Saving NEW items uses batches of up to 200 bound rows for purchases and histories, inside the existing transaction. Unnumbered purchases still obtain each actual insert ID before building their `NPID:` history link. Donation IDs, end-user grouping, serials, and print reference groups retain their existing behavior.

Property allocation loads occupied numbers once per number pattern within a single preview/save request. Prefix searches use the property indexes; legacy numbers with surrounding spaces remain included. Each set excludes the numbers already reserved by earlier sets. The cache does not persist between requests. Add-item writers share an advisory lock while saving so simultaneous add-item requests cannot reuse the same snapshot; previews and PDFs do not acquire this lock.

PAR and ICS queries select the requested history references before deduplicating. `NPID:` links use the purchase primary key; property links use the property index and retain the original preference for a positive unit value, then the lowest item ID. PDF layout and grouping are unchanged.

Successful NEW saves return a fresh single-use token and refresh the purchase table without reloading the page. Failed saves restore their token at request shutdown so validation corrections can be resubmitted.

## Database setup

Run `php tools/perf/add_item_profile.php --add-indexes` once on each database deployment. It adds missing reference-number indexes to purchase, GF, SEF, Trust Fund, donation, and transfer histories. It checks existing leading indexes first and is safe to repeat. No schema changes run during an ordinary save or print.

## Verification

`php tools/perf/add_item_profile.php` performs read-only numbering and print lookup benchmarks. `--compare-print` also runs the original print lookups and checks identical item IDs. The original ICS query can take several minutes on a large reference; this option is for maintenance only.

`php tools/perf/add_item_save_check.php new 205` creates its own randomly named fixture database, clones the relevant table schemas, checks synthetic save data, then removes the fixture database at shutdown. It requires database creation privileges. Modes include `new`, `existing`, `sef`, `existing_sef`, `donation`, `trust`, `skip`, and `failure`. Optional third argument writes regression PDFs to a supplied directory.

Measured locally on 2026-10-14:

| Check | Before | After |
| --- | ---: | ---: |
| Number preview: 20 sets / 200 units | 1,902 ms | 4–15 ms |
| PAR item lookup: 229 rows | 7,217 ms | 6–7 ms |
| ICS item lookup: 8,000 rows | 678,698 ms | 52–67 ms |
| NEW save: 10,000 synthetic units | 834 ms | 582 ms |

These are local samples, not latency guarantees. The print lookup timings exclude PDF rendering and browser printing. Save comparisons use isolated fixture schemas; deployed data, storage, and concurrent work affect timing.

## Add Item table loading

The purchase DataTable now requests ten groups per page using server-side pagination. Item totals are aggregated before resolving departments; indexed history lookups run only for groups on the visible page. The read-only endpoint releases its session lock before querying. Search still covers purchase references, supplier, department, and formatted amounts. Select All fetches only matching group identities and includes other pages; individual selections survive paging and reset when the search or saved data changes.

`php tools/perf/add_item_table_profile.php --recent` measures both tables. The purchase response decreased from 204 rows / 67,637 bytes / 2,484 ms to ten rows / 3,381 bytes / 138–173 ms locally. The Existing table already loads ten rows in about 3 ms.

`php tools/perf/add_item_table_check.php` verifies paging, draw counters, totals, department/reference/supplier searches, literal search characters, empty pages, and filtered Select All without modifying inventory data. Comparing all 204 groups to the captured original endpoint produced identical field values. Browser checks using the actual table initialization and synthetic data verified selection persistence across pages, Select All across pages, and clearing selection on a changed search.
