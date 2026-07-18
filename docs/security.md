# Drive Resource security model

Drive Resource is a protected Moodle delivery layer. Browser restrictions are deterrents; server-side Moodle authorization is the security boundary.

## Mandatory authorization

Every protected browser request must resolve and validate:

- course module;
- course;
- Drive Resource activity instance;
- `require_login($course, true, $cm)`;
- `context_module::instance($cm->id)`;
- `mod/videoplayer:view` capability.

The default view capability excludes guest access, but production administrators must still review role/enrolment overrides.

## Learner-facing URL exposure

Protected Moodle-owned viewers must not render:

- raw Google Drive file IDs;
- direct Google download URLs;
- Google preview URLs;
- open-in-Drive links;
- OAuth credentials or access tokens.

The browser receives Moodle `protected.php` URLs for protected video, PDF and image delivery.

## No Google preview iframe

Release `1.1.18-beta` removes the obsolete generic Google preview iframe route from the protected learner-facing architecture.

Google Docs, Sheets and Slides are exported server-side to PDF and rendered through local PDF.js. Images use a protected `<img>` route. Videos use protected HTML5 streaming.

Unknown generic files are not embedded as active same-origin content.

## Same-origin active content risk

A generic remote HTML file proxied through Moodle and embedded with script permissions can execute as content from the Moodle origin. Therefore Drive Resource must never use a generic active iframe for untrusted/unknown file types.

Only explicitly supported viewer types may render inline.

## Local protected files

Local PDFs are stored through Moodle File API outside the web root.

Delivery path:

```text
protected.php authorization
↓
protected_stream
↓
Moodle filedir/cache path
↓
validated byte-range response
```

## Upstream proxy boundary

`classes/local/http_range_proxy.php` is not a generic URL proxy.

The upstream destination is generated server-side from a validated Drive Resource record and internal Google Drive helper logic.

The proxy:

- accepts only a validated single byte range;
- uses one canonical outgoing range mechanism;
- forwards `If-Range` only with a valid range;
- bounds `If-Range` length;
- preserves redirect cookies in a request-scoped cookie store/engine;
- uses TLS peer/host verification;
- streams without buffering the complete binary resource in PHP memory;
- applies low-speed protection to stalled upstream connections;
- sanitizes relayed response headers;
- rejects unexpected HTML, XHTML and JSON interstitial bodies before sending successful media/PDF responses.

## MIME handling

Protected responses prefer a safe upstream `Content-Type`.

When Google responds with `application/octet-stream`, the proxy may infer a supported MIME type from a sanitized upstream `Content-Disposition` filename. Otherwise it falls back to the explicitly configured protected resource type.

This fallback is why generic `/file/d/...` links that cannot be auto-detected require an explicit teacher-selected type.

## Response hardening

Protected responses use appropriate combinations of:

- `Content-Type`;
- `Content-Disposition: inline` with sanitized filename;
- `X-Content-Type-Options: nosniff`;
- `X-Robots-Tag: noindex, nofollow, noarchive`;
- private browser caching;
- `no-transform`;
- `Vary: Range, If-Range` for upstream proxy delivery;
- `Accept-Ranges: bytes` when supported;
- `Content-Range` for valid partial content.

Invalid local/cache ranges return `416` with an unsatisfied `Content-Range`.

## PDF cache security

Google Drive PDF cache files are stored under:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

They are not public web files. Every browser request still passes through Moodle authorization.

Cache warming:

- writes to temporary files;
- validates PDF signature before final placement;
- atomically renames valid files into cache;
- cleans temporary/cookie artifacts;
- uses duplicate-suppressed ad-hoc tasks for cold-cache warming.

## Progress and privacy

Progress/reward records are personal data.

Privacy API coverage includes:

- progress/active time;
- completion percentage/state;
- actual last page;
- total pages;
- cumulative time spent;
- points;
- rewards.

## Deterrent controls

The following are not DRM:

- hiding download controls;
- disabling right click;
- blocking copy/drag/select gestures;
- watermark overlays;
- custom fullscreen UI.

A learner who can view content can still capture the screen or inspect requests to the Moodle protected endpoint.

## Google Drive credentials roadmap

The current release works with shareable Google Drive/Docs resources. It does not yet provide site-owned OAuth/service-account Drive API access for private enterprise files.

Future authenticated Drive API integration must:

- keep OAuth credentials/tokens server-side;
- use least-privilege scopes;
- never expose tokens to browser JavaScript;
- validate Moodle authorization before Google content access is relayed;
- validate returned MIME/metadata;
- preserve the no-preview-iframe architecture.

## Commercial security release gate

Before production:

- guest access denied by default;
- unenrolled user without capability denied;
- enrolled learner/teacher access verified;
- direct `protected.php?id=<cmid>` logged-out access denied;
- no raw Drive IDs/URLs in learner HTML;
- no Google preview iframe route;
- unknown generic files not embedded;
- upstream HTML/login/warning pages not returned as HTTP 200 media;
- valid ranges return correct `206` metadata;
- invalid ranges handled safely;
- local/cached PDFs inaccessible directly from web root;
- backup/restore does not leak files across contexts;
- Privacy API export/delete verified;
- Moodle developer debugging clean;
- browser console/network inspection clean;
- current Moodle/PHP security support reviewed before release.
