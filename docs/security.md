# Drive Resource security model

Drive Resource is a protected Moodle delivery layer. Browser restrictions are not the security boundary.

## Enforced server-side protection

Every protected resource request must validate:

- the course module exists and belongs to `mod_videoplayer`;
- the course exists;
- the activity instance exists;
- `require_login($course, true, $cm)` succeeds;
- `context_module::instance($cm->id)` is used;
- the user has `mod/videoplayer:view`.

The default view capability excludes the guest archetype. Administrators can override Moodle roles, so production installations must still review role assignments and enrolment policies.

## Data and URL exposure

Plugin-owned protected viewers must never expose:

- raw Google Drive file IDs;
- direct Google Drive download URLs;
- Google preview URLs;
- open-in-Drive links.

Learner-facing PDF and video URLs point only to Moodle `protected.php`.

Local PDFs are stored through Moodle File API outside the web root. Google Drive PDF cache files are stored under:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Cached files are not public endpoints. Authorization is performed again on every browser request before any bytes are returned.

## Delivery service boundaries

### `protected_stream`

Responsible for trusted local/cache files:

- byte-range parsing and local segment streaming;
- private cache headers with `no-transform`;
- PDF signature validation during cache warming;
- local cache lifecycle and cleanup.

### `http_range_proxy`

Responsible for upstream protected HTTP delivery:

- accepts only a single validated byte range;
- sends one canonical upstream range through cURL;
- supports `If-Range` and `HEAD` where applicable;
- relays only required safe response metadata;
- sanitizes relayed header values;
- does not expose upstream error bodies as successful content;
- streams without loading complete resources into PHP memory.

The proxy must not forward arbitrary browser headers to Google Drive. Only explicitly allowlisted headers should be forwarded.

## HTTP response hardening

Protected responses use:

- `Content-Type` validated against a conservative MIME pattern;
- `Content-Disposition: inline` with a sanitized filename;
- `X-Content-Type-Options: nosniff`;
- `X-Robots-Tag: noindex, nofollow, noarchive`;
- private browser caching;
- `no-transform` to avoid intermediary modification of ranged media/PDF data;
- `Vary: Range, If-Range` for proxied upstream resources.

Valid partial responses preserve `206 Partial Content`, `Content-Range` and `Content-Length`. Invalid upstream range requests return `416` where the upstream server provides that result.

## SSRF boundary

The browser never supplies an arbitrary proxy destination. `protected.php` derives the upstream URL only from a validated activity instance and the internal Google Drive URL helper. Future source integrations must maintain an explicit host allowlist and must not turn `protected.php` into a generic URL fetcher.

## PDF cache warming

Cold Google Drive PDFs are streamed immediately through the protected proxy and a deduplicated Moodle ad-hoc task warms the complete server cache.

Security properties:

- cache files remain outside the web root;
- full cache downloads are validated as PDFs before final placement;
- temporary files and cookie jars are cleaned up;
- cache warming does not bypass the browser request authorization path;
- the source URL remains server-side.

## UI and CSS isolation

Availability and integrity include ensuring that Drive Resource cannot disable navigation or interaction elsewhere in Moodle.

Moodle compiles a module's root `styles.css` globally. Drive Resource therefore keeps that file free of viewer rules and loads `styles_activity.css` only from the authorized activity page.

Security and compatibility requirements:

- viewer CSS must never execute on course overview or third-party course-format pages;
- selectors must remain under Drive Resource-specific class names;
- fixed-position overlays and fullscreen fallback rules must be scoped to the activity root;
- the module must not patch or override `format_tiles`, themes or other plugins;
- release testing must confirm that course cards, indexes, breadcrumbs, modal controls and animated navigation remain clickable.

This boundary reduces the risk of accidental click interception, invisible overlays, UI denial of service and cross-plugin CSS collisions.

## Deterrent controls

The following are UX deterrents, not DRM:

- hiding download controls;
- disabling right click;
- blocking copy/drag/select gestures;
- watermark overlays;
- custom fullscreen UI.

A user who can view protected content can still capture screenshots, record the screen or inspect browser traffic to the Moodle endpoint. Product documentation must not claim absolute download prevention.

## Personal data

Progress and reward records are personal data. Privacy API coverage must include:

- progress/active time;
- completion percentage and state;
- last page and total pages;
- points;
- earned rewards.

## Commercial security validation

Before release:

- verify guest access is denied by default;
- verify an unenrolled user without capability is denied;
- verify enrolled learner and teacher access according to roles;
- verify direct `protected.php?id=<cmid>` requests require login;
- verify no raw Drive IDs/URLs are present in protected viewer HTML;
- verify CR/LF cannot be injected through filenames or relayed headers;
- verify upstream 4xx/5xx bodies are not returned with HTTP 200;
- verify valid ranges return correct `206` metadata;
- verify invalid ranges do not disclose upstream details;
- verify local and cached PDFs cannot be fetched directly from the web root;
- verify `styles.css` contains no viewer rules;
- verify Tiles/Mosaico and standard course formats navigate normally after cache regeneration;
- verify backup/restore does not leak files across course contexts;
- verify Privacy API export/delete behavior;
- review Moodle security advisories and supported PHP/Moodle versions before each commercial release.
