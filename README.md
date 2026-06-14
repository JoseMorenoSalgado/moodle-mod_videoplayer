# Drive Resource for Moodle

**Drive Resource** is a Moodle activity module for embedding and tracking learning resources hosted in Google Drive.

The internal Moodle component remains `mod_videoplayer` for compatibility with existing installations, but the user-facing product name is **Drive Resource**.

## Features

- Embed Google Drive resources inside Moodle courses.
- Support for videos, PDFs, images, documents, spreadsheets and presentations.
- Automatic resource type detection.
- Protected interface that hides direct Google Drive navigation links from the Moodle page.
- Plugin-owned fullscreen viewer for mobile and desktop.
- Presence-based progress tracking for Google Drive embedded resources.
- Teacher progress report per activity.
- Moodle Completion API integration.
- Backup and Restore support.
- Privacy API support.
- English and Spanish language strings.
- Admin settings for tracking, protected mode and default completion behavior.

## Requirements

- Moodle 4.5 or later.
- PHP version supported by the target Moodle release.
- HTTPS-enabled Moodle site.
- Google Drive resources shared with permissions that allow Moodle users to view them.

## Installation

1. Copy the plugin folder to:

   ```bash
   mod/videoplayer
   ```

2. Visit Moodle site administration to complete the plugin installation.

3. Go to:

   ```text
   Site administration > Plugins > Activity modules > Drive Resource
   ```

4. Configure the default tracking and protected mode settings.

## Usage

1. Enter a Moodle course.
2. Turn editing on.
3. Add a new activity.
4. Select **Drive Resource**.
5. Enter the activity name.
6. Paste a supported Google Drive or Google Docs URL.
7. Select the resource type or leave it as **Automatic**.
8. Configure the completion percentage if needed.
9. Save and display.

## Supported resources

Drive Resource supports common Google Drive and Google Docs URLs, including:

- Videos from Google Drive.
- PDF files.
- Images.
- Google Docs documents.
- Google Sheets spreadsheets.
- Google Slides presentations.
- Generic Drive files supported by the Google Drive preview viewer.

## Protected mode

Protected mode hides plugin-owned direct links to Google Drive and restricts iframe popup permissions.

Important limitation: Google Drive controls its own embedded viewer. If Google displays internal controls, Moodle cannot remove those controls from inside the iframe because of browser cross-origin restrictions.

The plugin also includes a `protected.php` endpoint intended to reduce direct URL exposure by authorizing access through Moodle. This mode is designed to avoid permanent storage in `moodledata`.

## Fullscreen viewer

Drive Resource includes its own fullscreen overlay. This allows students to expand the resource on mobile and desktop while staying inside Moodle.

The fullscreen overlay does not intentionally open Google Drive in a new browser tab.

## Progress tracking

Google Drive iframes do not expose exact video playback time to Moodle. For that reason, Drive Resource uses presence-based tracking:

- active time in the resource page,
- periodic heartbeat updates,
- completion when the configured threshold is reached.

For providers that expose playback APIs, future versions may support real video playback percentage tracking.

## Teacher reports

Teachers with the `mod/videoplayer:viewreport` capability can access the progress report for each activity. The report shows:

- student name,
- email,
- progress time,
- completion percentage,
- completion state,
- last update time.

## JavaScript AMD build

Development files live in:

```text
amd/src/
```

Compiled production files live in:

```text
amd/build/
```

To compile AMD files using Moodle tooling:

```bash
npm install
npx grunt amd
```

or, inside a Moodle development environment:

```bash
grunt amd
```

## Compatibility

Current stable release:

- Release: `1.0.0`
- Component: `mod_videoplayer`
- Supported Moodle versions: 4.5 to 5.1

## Documentation

Additional technical documentation is available in the `docs/` directory:

- `docs/architecture.md`
- `docs/database.md`
- `docs/developer-guide.md`

## License

GNU GPL v3 or later.

## Maintainer

Elearning Cloud  
https://elearningcloud.io
