# Routine: Daily per-camp operations report (HTML)

Recreate this on a new machine: ask Claude to "create a scheduled task", schedule **daily at 08:00 local**,
taskId `karibu-daily-camp-notes`, and paste the prompt below verbatim.

The server script `cron-daily-camp-notes.php` (already live on production) does ALL the formatting —
it outputs a clean, color-coded **HTML** page anyone can read. The routine just saves it. No markdown.

## Schedule
- Cron: `0 8 * * *`  ·  taskId: `karibu-daily-camp-notes`

## Prompt (paste verbatim)

Daily Karibu Pantry Planner — per-camp kitchen report (HTML).

Project folder: <the project folder on THIS machine>

OBJECTIVE: Each morning, generate the color-coded HTML operations report from the live server and save it into the project's reports/daily/ folder. Read-only — never modify any live data.

STEPS:
1. Read the SSH credentials from the project's CLAUDE.local.md (host 46.202.197.46, port 65002, user u929828006, and the SSH password). Remote web root: domains/palegoldenrod-coyote-386848.hostingersite.com/public_html
2. Run the report generator in HTML mode and capture its full HTML output:
   sshpass -p '<password from CLAUDE.local.md>' ssh -o ConnectTimeout=25 -o StrictHostKeyChecking=accept-new -p 65002 u929828006@46.202.197.46 "cd domains/palegoldenrod-coyote-386848.hostingersite.com/public_html && php cron-daily-camp-notes.php html"
   (Ignore the harmless post-quantum key-exchange warning.)
3. Save that HTML verbatim to reports/daily/<today>.html in the project (create reports/daily/ if missing). Get <today> by also running the script with no argument (JSON mode) and reading its "today" field — do NOT use the local clock.
4. Give a 2-3 line summary of the headline (which camps are fine, which need follow-up) — the HTML itself has a "Follow up today" box at the top; summarise that.

CONSTRAINTS: Strictly read-only. Only run cron-daily-camp-notes.php and write the local HTML file. If the server is unreachable, note it briefly and stop.

## Optional: email it to managers automatically
The HTML is email-safe (inline styles). To have the server email it to the camp managers every morning
(no laptop needed), ask Claude to add a small mailer cron on Hostinger — the manager emails are already
in the `notification_emails` table.
