# Drive Resource security model

Drive Resource is a protected Moodle delivery layer. Browser restrictions are deterrents; Moodle server-side authorisation is the enforceable boundary.

## Supported security baseline

The current release supports Moodle 5.0–5.2 and PHP 8.2/8.3. Security validation is anchored to `MOODLE_500_STABLE`. Running the plugin on an undeclared Moodle or PHP branch is unsupported because API behaviour and security fixes may differ.

## Authorisation

Every protected request must validate:

- course module belongs to `mod_videoplayer`;
- course and activity instance exist;
- `require_login($course, true, $cm)` succeeds;
- `context_module::instance($cm->id)` is used;
- user has `mod/videoplayer:view`.

Guest access is not granted by the default capability archetypes.

## URL and data exposure

Plugin-owned viewers must never expose:

- raw Google Drive file IDs;
- direct Google Drive download URLs;
- Google preview URLs;
- open-in-Drive controls;
- upstream error bodies.

Protected browser URLs point to Moodle `protected.php`.

## Storage

Local PDFs use Moodle File API outside the web root. Google Drive PDF cache files remain under:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Every browser request is re-authorised before bytes are returned.

## Streaming boundaries

`protected_stream` handles trusted local/cache files, PDF signature verification, local ranges and cache lifecycle.

`http_range_proxy` handles upstream HTTP delivery, accepts one validated range, relays allowlisted headers, sanitises values and streams without buffering the entire resource.

The endpoint must never become a generic URL proxy. Upstream destinations must come from validated activity data and explicit Google host patterns.

## Response hardening

Protected responses use validated MIME types, inline disposition with sanitised filenames, `nosniff`, no-index directives, private caching and `no-transform`. Valid ranges preserve `206`, `Content-Range` and `Content-Length`; invalid ranges return `416` without leaking upstream details.

## Task and cache safety

PDF cache warming uses Moodle ad-hoc tasks with duplicate suppression. Full files are written to temporary paths, verified as PDFs and atomically renamed. Lock, cookie and temporary files are cleaned up.

## UI isolation

Moodle compiles root module CSS globally. Drive Resource keeps `styles.css` free of viewer rules and loads `styles_activity.css` only on its activity page.

Fullscreen and overlay rules must remain scoped to Drive Resource roots. This prevents invisible overlays, click interception and UI denial of service in course formats such as Tiles/Mosaico.

The plugin must never modify third-party course formats or themes to solve a local compatibility defect.

## Personal data

Progress, active time, completion, page position, points and rewards are personal data. Privacy API must cover metadata, context discovery, export and deletion for users and approved user lists.

## Moodle 5.0 security validation

The automated matrix checks PHP syntax, coding style, metadata, XMLDB savepoints, Mustache, AMD and PHPUnit against Moodle 5.0. Production validation additionally requires:

- current supported Moodle 5.0 maintenance release;
- guest and unenrolled access denial;
- direct protected endpoint denial without login/capability;
- no raw Drive data in HTML;
- safe handling of CR/LF in filenames and headers;
- upstream failures not returned as HTTP 200;
- correct valid/invalid range behaviour;
- no direct web access to local/cache files;
- normal navigation in Tiles/Mosaico and standard formats;
- Backup/Restore and Privacy API tests;
- review of current Moodle security advisories before release.

Screen capture and browser endpoint inspection cannot be prevented absolutely. Product claims must not describe viewer controls as DRM.
