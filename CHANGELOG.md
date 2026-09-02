# Changelog

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
