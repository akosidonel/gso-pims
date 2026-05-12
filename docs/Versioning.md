# Automatic Versioning (Commit Count Segmentation)

The application uses a fully automatic, tag‑free version scheme derived only from the total number of commits on the current `HEAD`. No manual editing or git tags are required.

## Summary
Version format (default, when not overridden):

  MAJOR.MINOR.PATCH+<shortHash>[.dirty]

Where:
- MAJOR = fixed seed (currently 1) – can be changed in code later if you intentionally start a new era.
- MINOR = floor(total_commits / 100)
- PATCH = total_commits % 100
- +<shortHash> = 7‑character git commit hash (for uniqueness / traceability)
- .dirty (optional) = appended when the working tree has uncommitted changes

Example progression (commit count → version):
- 0 commits (fresh repo with first commit counted as 1) → 1.0.1+abc1234
- 7 commits → 1.0.7+1f09e2d
- 99 commits → 1.0.99+77aa001
- 100 commits → 1.1.0+55bb220 (MINOR increments)
- 275 commits → 1.2.75+9c0d4ef

This guarantees a monotonically increasing version without relying on tags or manual bumps. Every new commit maps deterministically to exactly one version.

## Precedence / Overrides
`include/version.php` applies this precedence:
1. If environment variable `APP_VERSION` is set: use it verbatim (skips git logic) – useful for CI pipelines.
2. Else if git metadata is available: compute commit count segmentation (algorithm above).
3. Else (no `.git` directory): fallback to `0.0.0+YYYYMMDDHHMM` timestamp.

## Programmatic Access
```php
$meta = include __DIR__.'/../include/version.php';
echo $meta['full'];          // e.g. 1.2.75+9c0d4ef
echo $meta['version'];       // e.g. 1.2.75
```

## HTML Badge
Simply including the file echoes a small badge:
```php
include __DIR__.'/../include/version.php';
```
When sourced from git it shows: `v1.2.75+9c0d4ef (275)` where the number in parentheses is the total commit count.

## Returned Array Fields
| Key | Meaning |
|-----|---------|
| name | Application name label |
| version | MAJOR.MINOR.PATCH (commit-count segmentation) |
| full | version plus +hash and optional .dirty |
| hash | Short commit hash (if git present) |
| total_commits | Integer total commits on HEAD |
| dirty | Boolean: working tree has uncommitted changes |
| source | 'git', 'env', or 'fallback' |
| date | Last commit date (git) or current date fallback |
| tag | Always null in this scheme (legacy compatibility) |
| commits_since_tag | Always null (legacy compatibility) |

## Dirty Working Tree
If you see `.dirty` at the end of `full`, commit or stash changes to remove it. This helps ensure production builds are traceable to a clean commit.

## Environment Override (`APP_VERSION`)
Set before PHP starts (web server env, `.env`, or CI export):
```bash
export APP_VERSION=2.0.0-rc1
```
All requests will then report exactly `2.0.0-rc1` with `source = env`.

## Caching
For performance a small JSON cache (keyed by current commit hash) is stored in the system temp directory. A recompute only happens when HEAD changes or cache is missing/invalid.

## CI / Build Without .git
If you deploy without the `.git` folder, the fallback value `0.0.0+YYYYMMDDHHMM` is used. To keep the commit-based version in such environments you can:
1. Retain `.git` in the artifact (simplest), or
2. Bake the computed version into an environment variable (set `APP_VERSION` during build), or
3. Pre-run `include/version.php` in a build step and write its array to a PHP file you then include (advanced scenario).

## Rationale
- Deterministic: every commit has one version.
- Monotonic: lexical and semantic ordering track commit chronology (except for `.dirty` transient suffix).
- Zero manual work: just commit.
- Tag independence: no need to manage or push tags.

## Changing the MAJOR Seed
Currently hard-coded to 1. If you want to declare a major conceptual rewrite, you can bump the seed in the code (look for `$majorSeed`). This does not retroactively change historic versions; it simply starts producing `2.x.y`, `3.x.y`, etc., from that commit onward.

## Legacy Artifacts
`VERSION` file and `tools/bump-version.php` (now a stub) remain only for backward compatibility. They are not used by the active algorithm.

## Troubleshooting
- Shows `0.0.0+<timestamp>`: `.git` not present and no `APP_VERSION` set.
- Shows `.dirty`: You have uncommitted changes.
- Commit count seems off: Ensure repository isn't shallow (remove `--depth` clone) and that you're on the expected branch.

## Recommended Workflow
- Commit normally.
- (Optional) Set `APP_VERSION` in CI for release candidates or marketing versions.
- Avoid manual editing—let commit count drive patch/minor changes.

---
Automatic, commit-count based versioning keeps things simple and always up to date.
