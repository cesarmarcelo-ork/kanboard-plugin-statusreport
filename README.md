# StatusReport — Status Report for Kanboard

The **StatusReport** plugin lets users explicitly classify a comment as a **Status Report**. The most recent Status Report becomes the official **Current Status** of the task.

The operational goal is to allow the current status to be perceived directly on the **Kanban board card**, without requiring the task to be opened. To achieve this, the Current Status is mirrored by default in the **Description** field, without deleting or replacing other manually entered information.

## Compatibility

- Kanboard **>= 1.2.50** (1.2.x series)
- Supported databases: **SQLite, MySQL/MariaDB and PostgreSQL**
- No external dependencies
- No cron
- No network calls
- No changes to the Kanboard core

Plugin version remains **1.0.0**.

### Validations performed during development

- Code audit and functional reference validation on **Kanboard 1.2.50 + PHP 8.3 + SQLite**.
- Real interface validation on **Kanboard 1.2.52**, including plugin loading, creating a Status Report, promoting an existing comment, re-promoting a historical report, card mirror, and preservation of manual description text.
- The test runner delivered in this release contains **43 checks** and should be executed in the actual environment where the plugin will be tested.

Versions later than 1.2.52 must pass through the same runner and manual validation from this README before sign-off.

---

## Functional concept

The plugin differentiates two types of entries:

| Entry | Stays in comments | Updates the Current Status |
|---|---:|---:|
| Comment / Follow-up | Yes | No |
| Status Report | Yes | Yes |

The latest comment does **not** become the Current Status automatically. Only a comment explicitly classified as a Status Report changes the active status of the task.

Example:

```text
08/10 — Status Report A
08/11 — Comment B
08/11 — Comment C
08/12 — Status Report D
```

At the end:

- A, B, C and D remain in the history;
- A and D are Status Reports;
- D is the Current Status;
- B and C did not change the active status.

---

## Current Status on the card

The mirror of the Current Status in the description is **enabled by default**.

The display on the card follows this format:

```text
Current Status

Text of the active Status Report.

Updated on: 08/12/2026 23:19
Updated by: User name
```

In Kanboard's rendering:

- **Current Status** uses the normal card font, only in bold;
- no decorative lines are required;
- the internal plugin delimiters do not appear;
- any manual text outside the region managed by StatusReport is preserved.

Internally, the plugin uses Markdown reference definitions that are not rendered:

```markdown
[status-report-start]: #

**Current Status**

Report text.

Updated on: ...
Updated by: ...

[status-report-end]: #
```

These delimiters are necessary for the plugin to safely locate only its own region of the description.

### Compatibility with legacy markers

The first implementation used:

```text
<!-- STATUS_REPORT_START -->
<!-- STATUS_REPORT_END -->
```

The current model recognizes these legacy markers only for migration. On the next card synchronization, they are automatically replaced by the invisible Markdown markers.

---

## Usage

### 1. Create a new Status Report

In the task, use:

**Add a Status Report**

The plugin creates the comment and its classification in the same logical flow. The comment becomes the Current Status and the card is updated.

### 2. Promote an existing comment

Use:

**Set as current status**

You can promote:

- a regular comment;
- a historical Status Report;
- any comment visible to the user and belonging to the task.

If a historical report is promoted again, it receives a new ordering in the classification table and becomes the Current Status again. The comment is not duplicated or changed.

### 3. Edit the active report

The text of the Current Status is always read from the original comment. Therefore, editing the current comment will cause the panel and the card to reflect the new content.

The date shown as **Updated on** is:

```text
max(classification date, last modification date of the comment)
```

This covers both cases:

- editing a current report later moves the date forward;
- promoting an old comment that was edited in the past keeps the classification date.

### 4. Delete the active report

The classification has `ON DELETE CASCADE` on the comment.

When the comment representing the Current Status is deleted:

- its classification disappears automatically;
- the immediately preceding Status Report becomes active again;
- if there is no previous one, the task has no Current Status.

### 5. Manual information in the description

The description continues to be available normally for information such as:

```text
Goal
Responsible
Deadline
References
Notes
Links
Request context
```

StatusReport exclusively replaces the region delimited by its internal markers.

---

## Architecture

The Current Status is **derived**, not duplicated.

Plugin table:

```text
status_reports
  id             deterministic ordering
  task_id        FK tasks(id)     ON DELETE CASCADE
  comment_id     FK comments(id)  ON DELETE CASCADE, UNIQUE
  user_id        user who classified/promoted
  date_creation  moment of classification
```

Rule:

```text
Current Status = most recent surviving status_reports row for the task (ORDER BY id DESC)
                 + current text of comments.comment
```

The comment is the **single source of truth for the text**.

---

## Kanboard resources used

| Resource | Use |
|---|---|
| `projectAccessMap` | `StatusReportController` requires `PROJECT_MEMBER` |
| `template:task:show:before-description` | Current Status panel |
| `template:task:dropdown:after-add-comment` | action to create report |
| `template:task:sidebar:after-add-comment` | action to create report |
| `template:config:application` | mirror configuration |
| `template:layout:top` | conflict warning |
| `template:layout:css` | stylesheet, registered in the asset format expected by Kanboard |
| `setTemplateOverride('comment/show')` | badge and contextual action in history |
| `comment.update` / `comment.delete` | mirror sync |
| `Schema/{Sqlite,Mysql,Postgres}.php` | automatic migration |

No core file is changed.

---

## Conflict with other plugins

The only overridden template is:

```text
comment/show
```

The override is a **wrapper**: it renders the core template and only adds the StatusReport badge/action.

If another plugin competes for the same template, StatusReport detects the conflict via the effective mapping and enters **restricted mode**.

### Unavailable in restricted mode

- `[Status Report]` / `[Current Status]` badge directly in the comment history;
- contextual link `Set as current status` on each comment.

### Still available

- Current Status panel;
- creating Status Reports;
- card mirror;
- promoting an existing comment from the panel/modal.

The administrator receives a warning with the conflicting plugin, template, file, and reason.

---

## Security

- Native ACL: `PROJECT_MEMBER` for plugin write actions;
- forms protected by CSRF;
- promotion via GET link uses the reusable GET-specific CSRF token verifier;
- explicit whitelist of fields before comment creation;
- `visibility` filtered according to user role;
- `comment_id` validated against the task;
- task validated against the project;
- output escaped via native helpers;
- Markdown rendered by the same Kanboard mechanism.

CSRF, whitelist and visibility filtering were reviewed by code audit and manual interface validation.

---

## Installation

The ZIP must have this internal structure:

```text
StatusReport/
└── Plugin.php
```

Manual installation:

```bash
cd /path/to/kanboard/plugins
unzip StatusReport-1.0.0.zip
```

At the end there must be:

```text
plugins/StatusReport/Plugin.php
```

When loading any Kanboard page, the migration mechanism automatically creates the `status_reports` table.

### Docker installations

If `plugins/` is a host bind mount, the folder can be copied from the host, but the runner must be executed **inside the runtime that contains the actual Kanboard code**.

Generic example:

```bash
docker exec -it <kanboard-container> sh
cd /var/www/app
php plugins/StatusReport/Test/run.php
```

The internal path and container name must be confirmed in the local environment.

---

## Validation runner

From the actual Kanboard application root:

```bash
php plugins/StatusReport/Test/run.php
```

The runner:

1. verifies it is in the actual Kanboard runtime and stops with a clear message if `app/common.php` is not available;
2. shows Kanboard version, StatusReport version, PHP, driver and foreign key state;
3. requires Kanboard `>= 1.2.50`;
4. requires effective foreign keys;
5. creates a temporary project;
6. runs **43 checks**;
7. restores exactly the previous mirror configuration, including when the option did not yet exist in the database;
8. removes the temporary project in `finally`.

The runner covers, among others:

- regular comment;
- first report;
- new report;
- editing current report;
- editing historical report;
- date rule;
- description mirror;
- preservation of manual text;
- invisible markers;
- migration of legacy markers;
- deletion with automatic restore;
- ACL;
- multiple cycles;
- idempotency;
- re-promotion of historical report;
- template conflict detected on install.

---

## Configuration

At:

**Settings → Application → Status Report**

there is the option:

**Mirror the current status inside the task description**

It is **enabled by default**.

The administrator can disable it, if they wish to use only the internal task panel.

---

## Known limitations

1. There is no specific action to **un-classify** a report. A status can be replaced by a new report or the comment can be deleted per the normal Kanboard rules.
2. In installations with asynchronous queues, the mirror update after `comment.update` may occur with a delay. The internal panel remains correct because it is derived at read time.
3. The visual integration in the history depends on the single `comment/show` override; conflict with another plugin activates restricted mode.
4. The runner must be run inside the actual Kanboard runtime; in Docker, running only on the volume host is not sufficient.

---

## Uninstallation

Remove:

```text
plugins/StatusReport
```

Comments remain intact because the text is never transferred to the plugin table.

The region already mirrored in the description remains as regular Markdown text. If there is a need to remove the classification data as well, that operation must be planned separately and is not necessary for simple plugin deactivation.

---

## Documentation files

- `README.md` — installation, usage and operation;
- `RELATORIO-TECNICO.md` — architecture, decisions and technical risks;
- `GUIA-TESTE.md` — deployment and acceptance testing guide;
- `LICENSE` — MIT license.

## License

MIT.
