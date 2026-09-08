# Changelog

## 0.2.0 — 2026-09-07

### New: three values changeable without file access

Under **Settings → Addon Settings** there is a section for this addon, with two groups:

- **Presentation:** the Antlers layout the shipped templates are wrapped in (if the view is
  missing, the addon still falls back to its own plain shell instead of showing an error), and
  a switch for the shipped stylesheet. Off means the templates no longer include it; markup and
  class names stay, your own CSS still applies.
- **Neighbouring addons:** whether the address captured in the assessment goes to LeadHub as a
  contact. Off means it stays inside this addon.

Only the deviation is stored; everything else still follows `config/assessments.php`.

Not on the page: `routes.prefix` and `routes.throttle` are read while the routes are being
registered, `integrations.automations` at boot, and the bridge remembers at that point that it
has registered the trigger. A later "off" would not take it out again, a later "on" would not
add it. Both keys stay in the config, and the group descriptions on the page say so.

**New permission `manage assessments settings`.** Until it is assigned to a role nobody sees
it, not even a user who may do everything else in this addon. Existing permissions are
unchanged.

**Requires `goldnead/statamic-brand-context` 1.13 or later.** Under older versions the page is
there, but its values are not reliable: on an installation with a single brand the settings of
the addons registered last did not reach the config at all, and up to 1.12 a second save of the
same section silently deleted the first save's override. If you set values before this update,
check after updating whether they are still there.

## 0.1.1 — 2026-09-05

The shipped bundle was older than the source it was meant to come from.

### Fixed

- **`dist/` rebuilt to match the source.** Commit `6f2ee23` (error banners as `Alert`, delete
  moved into the header menu) changed `resources/js` and `resources/css`, but the committed
  `dist/build` still dated from 0.1.0. A site on this commit would have run the new PHP with the
  old JavaScript — the class of mismatch that produced "Cannot read properties of undefined" in
  the suite on 2026-09-03. A fresh build from the committed source is byte-identical to the
  unreviewed build that sat in the working tree; that is what is committed now.
- **Error banners are an `Alert`, delete lives in the `…` menu.** Two error banners sat as a red
  `div` directly on the grey panel; the delete button was a red header button rather than an entry
  in the header menu.
- **CI never ran.** The repository is private and the workflow declared `permissions: {}`, so
  `actions/checkout` could not read the repository and every job failed before a single test.
  Now `contents: read`. The `dist` job in this CI is the check that would have caught the mismatch
  above.

### Changed

- **Icon and cover in the suite's handwriting.** The icon is now a scale with three level marks
  and a needle in the third: points become a level, and only a scale shows both at once. Accent
  is a leaf green (`#44B234` → `#246619`), the largest free gap in the suite's hue circle. A cover
  image exists now, so the docs page has a share image.
- **This changelog is in English**, like the READMEs, the Marketplace copy and every other
  changelog in the suite. Content unchanged.

## 0.1.0 — 2026-09-02

First cut. A questionnaire with points per answer and result levels by score; the result becomes a
contact event and an automation trigger. No certificate, no file upload, no course required.

### Added

- **Three question types.** `single` (the chosen option's points), `multi` (the sum of the chosen
  options), `scale` (value × points per step). Every question is required; a skipped one is never
  quietly counted as zero.
- **Result levels** as gapless `min`–`max` ranges, inclusive at both ends. The server refuses
  overlaps and gaps always; that the levels cover the whole achievable range, only on publishing.
  Each level may redirect instead of showing the result page.
- **Public pages** at `/a/{handle}` (the form, `assessment.antlers.html`) and `/a/{handle}/r/{token}`
  (the result, `result.antlers.html`), inside the site's layout, with a shell of their own as a
  fallback. Both templates are publishable. `POST …/submit` with CSRF and a throttle (`20,1`),
  honeypot `website`, and a preview of an unpublished assessment for editors only.
- **Tags** `{{ assessments:url }}`, `{{ assessments:form }} … {{ /assessments:form }}` and
  `{{ assessments:result }} … {{ /assessments:result }}` for pages that draw the form themselves.
- **Event** `AssessmentCompleted`. With `statamic-leadhub` the address becomes a contact **without
  consent** and the result a timeline event `assessment.completed` carrying `score`, `result_key`,
  `result_label` and the answers. With `statamic-automations` there is the trigger
  `assessments.completed`, filterable by assessment and level.
- **Control Panel** under Tools → Assessments: listing, editor with a question and a level editor,
  responses per assessment with CSV export. Permissions `view assessments`, `edit assessments`,
  `view assessment responses`. German and English, light and dark mode.
- **Brand-scoped** through `statamic-brand-context`; the handle is unique across all brands, so the
  public address stays unambiguous.
- **Answers stay readable.** Every response holds an `answers_readable` snapshot (question text,
  chosen options, points) from the moment it was submitted; the editor updates questions by `id`
  rather than replacing them. The result token is only ever made server-side, the result page shows
  no address, the CSV export defuses formula cells, and the same address may answer more than once
  (each answer an event of its own).
