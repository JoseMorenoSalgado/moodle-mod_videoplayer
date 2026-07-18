# Drive Resource database model

The Moodle component is `mod_videoplayer`.

## `videoplayer`

Stores activity configuration.

Important production fields:

| Field | Purpose |
|---|---|
| `course` | Parent Moodle course. |
| `name` | Activity display name. |
| `source` | `googledrive` or `localpdf`. |
| `videourl` | Teacher-configured Google Drive/Docs share URL. Never rendered directly to learners. |
| `type` | `auto`, `video`, `pdf`, `image`, `document`, `spreadsheet`, `presentation`, or `file`. |
| `displaymode` | PDF display mode: `standard` or `ebook`. |
| `disabledownload` | Viewer deterrent setting. |
| `disablecontextmenu` | Viewer deterrent setting. |
| `enablewatermark` | Dynamic watermark setting. |
| `enablegamification` | Personal reward tracking setting. |
| `pointsperpage` | Reward point configuration. |
| `completionpercentage` | Required completion threshold. |

## `videoplayer_views`

One record per Drive Resource/user pair.

Unique key:

```text
(videoplayerid, userid)
```

### Common progress fields

| Field | Purpose |
|---|---|
| `progress` | Monotonic progress quantity. For video this is unique seconds watched; for PDF it is cumulative active reading time. |
| `completed` | Whether the configured completion threshold has been reached. |
| `completionpercentage` | Monotonic completion percentage. |
| `timespent` | Cumulative active time in seconds. |
| `points` | Total gamification points. |
| `timecreated` | Initial record timestamp. |
| `timemodified` | Last update timestamp. |

### Exact PDF state

| Field | Purpose |
|---|---|
| `lastpage` | Actual most recently reported page. It may move backward and is used for resume behavior. |
| `totalpages` | Total detected PDF pages. |
| `visitedpages` | JSON array of unique pages actually observed. Used to calculate exact PDF completion across sessions. |

PDF completion is calculated from the union of `visitedpages` divided by `totalpages`. The server does not infer completion from `lastpage / totalpages`.

### Exact video state

| Field | Purpose |
|---|---|
| `lastsecond` | Actual most recently reported playback position, used to resume video. |
| `totalseconds` | Detected video duration. |
| `watchedranges` | JSON array of normalized, merged playback intervals such as `[[0, 12.5], [30, 45.2]]`. |

Video completion is calculated from the unique duration covered by the union of `watchedranges` divided by `totalseconds`. Seeking directly to the end does not mark skipped time as watched.

## `videoplayer_rewards`

Stores user rewards.

Unique key:

```text
(videoplayerid, userid, rewardtype, rewardkey)
```

This prevents duplicate rewards for the same milestone.

## Upgrade path

Release `1.1.18-beta` / plugin version `2026071702` adds:

```text
videoplayer_views.visitedpages
videoplayer_views.lastsecond
videoplayer_views.totalseconds
videoplayer_views.watchedranges
```

The corresponding XMLDB migration is in `db/upgrade.php`.

## Backup and restore

When user data is included, backup/restore covers:

- common progress fields;
- exact PDF page state;
- exact video playback state;
- rewards.

## Privacy API

The Privacy API metadata/export/delete flow covers all fields in `videoplayer_views` and `videoplayer_rewards`, including `visitedpages`, `lastsecond`, `totalseconds` and `watchedranges`.

## Data-size controls

The progress service sanitizes and bounds client-provided JSON state:

- maximum visited-page entries;
- maximum watched-range entries;
- maximum JSON payload length;
- watched ranges are clamped to known media duration and merged before storage.

These limits prevent unbounded client state from being persisted directly.
