# Changelog

## 0.1.0 — 2026-09-02

Erste Fassung. Ein Fragebogen mit Punkten je Antwort und Ergebnisstufen nach Punktsumme; das
Ergebnis wird ein Kontakt-Ereignis und ein Automations-Auslöser. Kein Zertifikat, kein
Datei-Upload, kein Kurs nötig.

### Was drin ist

- **Drei Fragetypen.** `single` (Punkte der gewählten Option), `multi` (Summe der gewählten
  Optionen), `scale` (Wert × Punkte je Schritt). Jede Frage ist Pflicht; eine ausgelassene Frage
  zählt nicht still als null.
- **Ergebnisstufen** als lückenlose Bereiche `min`–`max`, inklusive an beiden Enden. Der Server
  lehnt Überschneidungen und Lücken immer ab; dass die Stufen den erreichbaren Bereich ganz
  abdecken, erst beim Veröffentlichen. Je Stufe optional eine Weiterleitung statt der
  Ergebnisseite.
- **Öffentliche Seiten** unter `/a/{handle}` (Formular, `assessment.antlers.html`) und
  `/a/{handle}/r/{token}` (Ergebnis, `result.antlers.html`), im Layout der Site, mit einer
  eigenen Hülle als Rückfall. Beide Templates veröffentlichbar. `POST …/submit` mit CSRF und
  Drossel (`20,1`), Honeypot `website`, Vorschau eines unveröffentlichten Assessments nur für
  Redakteure.
- **Tags** `{{ assessments:url }}`, `{{ assessments:form }} … {{ /assessments:form }}` und
  `{{ assessments:result }} … {{ /assessments:result }}` für Seiten, die das Formular selbst
  zeichnen.
- **Ereignis** `AssessmentCompleted`. Mit `statamic-leadhub` wird die Adresse ein Kontakt **ohne
  Einwilligung**, das Ergebnis ein Timeline-Ereignis `assessment.completed` mit `score`,
  `result_key`, `result_label` und den Antworten. Mit `statamic-automations` gibt es den
  Auslöser `assessments.completed`, filterbar nach Assessment und Stufe.
- **Control Panel** unter Werkzeuge → Assessments: Liste, Editor mit Fragen- und
  Stufen-Editor, Antworten je Assessment mit CSV-Export. Berechtigungen `view assessments`,
  `edit assessments`, `view assessment responses`. Deutsch und Englisch, heller und dunkler
  Modus.
- **Markenbezogen** über `statamic-brand-context`; die Kennung ist über alle Marken eindeutig,
  damit die öffentliche Adresse eindeutig bleibt.
- **Antworten bleiben lesbar.** Jede Response hält einen Schnappschuss `answers_readable`
  (Fragetext, gewählte Optionen, Punkte) vom Zeitpunkt des Absendens; der Editor aktualisiert
  Fragen per `id` statt sie zu ersetzen. Der Ergebnis-Token entsteht nur serverseitig, die
  Ergebnisseite zeigt keine Adresse, der CSV-Export entschärft Formel-Zellen, dieselbe Adresse
  darf mehrfach antworten (jede Antwort ein Ereignis).
