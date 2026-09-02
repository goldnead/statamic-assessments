# Assessments for Statamic

A questionnaire with points per answer and result levels by score. The visitor answers,
leaves an email address and sees a result right away. The result becomes a contact event
in LeadHub and a trigger in Automations, so it can set a tag, start a sequence or unlock an
offer — the part a quiz is actually for.

No certificate, no file upload, no course required.

## Requirements

| | |
| --- | --- |
| PHP | 8.2 or newer |
| Statamic | 6.0 or newer |
| Laravel | 12.40+ or 13 |
| Database | MySQL or SQLite (three tables of its own) |
| `goldnead/statamic-brand-context` | `^1.8`, installed alongside; invisible on a single-brand site |

## What it does

- **Three question types.** Single choice (the chosen option's points), multiple choice
  (the sum of the chosen options), scale (value × points per step). Every question is
  required.
- **Result levels.** Ordered ranges of points, inclusive on both ends, each with a key, a
  name, a text and optionally a redirect. The editor refuses overlaps and gaps always, and
  refuses to publish levels that do not cover every achievable score.
- **Public pages** under `/a/{handle}` (the form) and `/a/{handle}/r/{token}` (the result),
  wrapped in the site's layout, with plain shipped templates you can publish and replace.
  Or draw the form yourself with the tags.
- **One event**, `AssessmentCompleted`, and two optional bridges: the address becomes a
  LeadHub contact **without consent** with a timeline event `assessment.completed`; the
  Automations addon gets a trigger `assessments.completed` filterable by assessment and
  level.
- **Control Panel** under Tools → Assessments: listing, editor, responses per assessment
  with CSV export. Three permissions. German and English, light and dark.
- **Brand-scoped** through `goldnead/statamic-brand-context`. Handles are unique across
  brands because the public URL names nothing else.

## Installation

```bash
composer require goldnead/statamic-assessments
php artisan migrate
php artisan vendor:publish --tag=statamic-assessments
```

The third command copies the compiled Control Panel assets to
`public/vendor/statamic-assessments/`. Without it the CP pages render without their
JavaScript.

Optional siblings, used when installed and never required:

| Package | What it adds |
| --- | --- |
| `goldnead/statamic-leadhub` | Contact and timeline event per completed assessment |
| `goldnead/statamic-automations` | The trigger `assessments.completed` |

## Building an assessment

Tools → Assessments → **Create assessment**. Title, handle (the public address), intro and
closing text, whether to ask for a name, and the published switch. Then the questions:

| Type | Points |
| --- | --- |
| Single choice | the points of the chosen option |
| Multiple choice | the sum of the chosen options, each counted once |
| Scale from `min` to `max` | the chosen value × points per step |

Below the questions the editor shows the range a visitor can score with them — say 1 to
22. The result levels have to sit next to each other inside that range:

| Key | Name | Min | Max |
| --- | --- | --- | --- |
| `grundlagen` | Grundlagen | 1 | 8 |
| `aufbau` | Aufbau | 9 | 15 |
| `vertiefung` | Vertiefung | 16 | 22 |

A gap (`8` then `10`) or an overlap (`8` then `8`) is refused on save. A draft may cover
less than the range; a published assessment may not. If a question is edited after
publishing and a score falls outside every level anyway, the visitor gets the nearest end
rather than nothing.

A level with a **redirect** sends the visitor to that URL instead of the result page — a
course page, a booking link, a sales page for that segment.

### From code

```php
use Goldnead\Assessments\Facades\Assessments;

$assessment = Assessments::create([
    'handle' => 'stimm-check',
    'title' => 'Stimm-Check',
    'published' => true,
    'collect' => ['name' => 'optional'],   // off | optional | required
    'questions' => [
        ['text' => 'Wie oft singst du?', 'type' => 'single', 'options' => [
            ['label' => 'Selten', 'points' => 0],
            ['label' => 'Täglich', 'points' => 3],
        ]],
        ['text' => 'Woran arbeitest du?', 'type' => 'multi', 'options' => [
            ['label' => 'Atmung', 'points' => 1],
            ['label' => 'Register', 'points' => 2],
        ]],
        ['text' => 'Wie sicher in der Höhe?', 'type' => 'scale', 'min' => 1, 'max' => 5, 'points_per_step' => 1],
    ],
    'scoring' => [
        ['key' => 'anfang', 'label' => 'Am Anfang', 'min' => 1, 'max' => 5, 'text' => '…'],
        ['key' => 'weit', 'label' => 'Weit', 'min' => 6, 'max' => 11, 'text' => '…', 'redirect' => null],
    ],
]);

Assessments::find('stimm-check');
Assessments::update($assessment, ['published' => false]);
Assessments::score($assessment, $answers);          // ['score' => 7, 'breakdown' => [id => points]]
Assessments::submit($assessment, $email, $name, $answers); // stores, fires AssessmentCompleted
Assessments::readableAnswers($response);            // [['question', 'type', 'answer', 'points'], …]
```

`create()` and `update()` throw an `InvalidArgumentException` for levels that overlap or
leave a gap, for a scale that does not end above where it starts, and — when `published`
is on — for levels that do not cover the range.

`update()` with `questions` updates a question that carries its `id`, creates one that
does not, and deletes the ones no longer listed. Ids are what stored responses key their
answers by, so the Control Panel sends them back on every save. On top of that every
response keeps a snapshot of the question texts and chosen labels as they were at submit
time, so a question rewritten or removed later never blanks an older result.

## The public side

| Method | URL | Name | |
| --- | --- | --- | --- |
| GET | `/a/{handle}` | `assessments.show` | The form. 404 unless published, or the viewer holds `view assessments`. |
| POST | `/a/{handle}/submit` | `assessments.submit` | CSRF, throttled `20,1`, honeypot field `website`. |
| GET | `/a/{handle}/r/{token}` | `assessments.result` | The result, under the token of that one submission. |

The form posts `email`, optionally `name`, and `answers[{question_id}]`: an option index for
single choice, a list of indexes for multiple choice, the value for a scale. Every question
is validated against the question as it is now — an index that does not exist or a scale
value off the end is a 422, not a zero. The address has to be a real one
(`email:rfc,strict`, with a dot in the domain).

After a submit the visitor is redirected to the result page, or to the level's redirect. A
JSON client (`Accept: application/json`) gets `score`, `result_key`, `result_label`,
`result_url` and `redirect` back instead.

Two things about the result URL, both on purpose:

- **It is permanent and shareable.** The token is 40 random characters minted on the
  server; nothing the client sends becomes part of it. Whoever has the link sees the level,
  the score and the answers — not the email address, which is why the address is not on
  that page.
- **The same address may answer as often as it likes.** Every submit is its own response,
  its own result URL and its own `AssessmentCompleted` event. An automation that should
  fire only once per person says so in its own enrollment policy.

### Templates

`resources/views/assessment.antlers.html` and `result.antlers.html`, rendered through
Statamic's own view with the site's `layout` (configurable). When the site has no such
layout the addon's own shell is used. Publish them to change them:

```bash
php artisan vendor:publish --tag=assessments-views
```

Both templates get their variables under `assessment:` (`assessment:questions`,
`assessment:action`, …); only `title` is also in the cascade, for the layout's `<title>`.
Nothing else goes in flat, so the site's own `url` or `name` inside the layout stays
untouched. The shipped styling is a small inline stylesheet driven by custom properties on
`.assessment`; turn it off with `styles => false` and keep the class names.

### Tags

```antlers
{{ assessments:url handle="stimm-check" }}

{{ assessments:form handle="stimm-check" }}
    <form method="POST" action="{{ action }}">
        {{ csrf_field }}
        {{ questions }}
            <fieldset>
                <legend>{{ text }}</legend>
                {{ if is_single }}
                    {{ options }}<label><input type="radio" name="{{ field }}" value="{{ index }}"> {{ label }}</label>{{ /options }}
                {{ elseif is_multi }}
                    {{ options }}<label><input type="checkbox" name="{{ field }}[]" value="{{ index }}"> {{ label }}</label>{{ /options }}
                {{ elseif is_scale }}
                    {{ steps }}<label><input type="radio" name="{{ field }}" value="{{ value }}"> {{ value }}</label>{{ /steps }}
                {{ /if }}
            </fieldset>
        {{ /questions }}
        <input type="email" name="email" required>
        <button>Ergebnis anzeigen</button>
    </form>
{{ /assessments:form }}

{{ assessments:result }}   {{# reads ?r=<token>, or token="…" #}}
    <h1>{{ result_label }}</h1>
    <p>{{ score }} Punkte</p>
    {{ result_text | markdown }}
{{ /assessments:result }}
```

The tag pairs hand their variables over flat, since they run inside a template of your own.
`form` and `url` render nothing for an unpublished or unknown handle. `result` renders
nothing without a valid token, and never the email address.

## Bridges

Both are off the moment the sibling is not installed, and can be switched off in config.

**LeadHub.** On `AssessmentCompleted` the address is ingested with
`source = assessment:{handle}`, `type = assessment.completed`, and a payload of `score`,
`result_key`, `result_label`, `assessment`, `assessment_title` and the readable answers.
**No consent is set**: answering a questionnaire is not agreeing to mail. The contact id
LeadHub reports is written back onto the response.

**Automations.** The trigger `assessments.completed` (group "Assessments") with two optional
fields, `assessment` and `result_key`, both empty for "any". Output:
`response.{id,email,name,score,result_key,result_label,assessment,assessment_title,answers,completed_at}`.

A failure in either bridge is logged and never fails the submit; the response is stored
first.

## Control Panel

| Permission | Allows |
| --- | --- |
| `view assessments` | the listing, and previewing an unpublished assessment on the front end |
| `edit assessments` | create, edit, delete |
| `view assessment responses` | the responses page and the CSV export |

The responses page shows the latest 500; the export streams all of them, `;`-separated,
UTF-8 with BOM, one column per question with the answer labels. A cell that starts with
`=`, `+`, `-`, `@`, a tab or a carriage return is prefixed with `'`, so a name typed as
`=HYPERLINK(...)` opens in Excel as text rather than as a formula.

## Configuration

```bash
php artisan vendor:publish --tag=assessments-config
```

| Key | Default | |
| --- | --- | --- |
| `routes.prefix` | `'a'` | The first URL segment. Changing it changes every link already sent. |
| `routes.throttle` | `'20,1'` | Submits per minute per address. |
| `layout` | `'layout'` | The site layout the shipped templates are wrapped in. |
| `styles` | `true` | The shipped inline stylesheet. |
| `integrations.leadhub` | `true` | The contact handover, when LeadHub is installed. |
| `integrations.automations` | `true` | The trigger, when Automations is installed. |

## Events

| Event | Payload | When |
| --- | --- | --- |
| `Goldnead\Assessments\Events\AssessmentCompleted` | `$response` | Once per stored response, with score and level set. |

## Tables

`assessments`, `assessment_questions`, `assessment_responses`. Responses store the answers
by question id, a readable snapshot of them (`answers_readable`: question, chosen labels,
points), the score and the level key as they were at the time — a rule or a question edited
later does not rewrite what somebody was told.

## Development

```bash
composer install && npm install
npm run build        # dist/build is committed; CI fails on drift
composer test
composer lint
composer analyse
```

## License

Proprietary. See [LICENSE.md](LICENSE.md).
