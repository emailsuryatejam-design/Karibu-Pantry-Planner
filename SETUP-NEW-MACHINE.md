# Moving this project to a new computer / Claude account

**Key fact:** the app runs on the Hostinger **server**, not on your computer. This folder is just a
working copy + memory (`CLAUDE.md`, `CLAUDE.local.md`) + reports. So the whole move is: **copy this
one folder** to the new Mac. The new Claude account needs no special setup — it reads `CLAUDE.md`
from the folder automatically.

---

## The 4 steps (simplest path)

### 1. Copy this folder to the new Mac
Copy the entire `smart req_ karibu` folder (this whole thing — it includes the code, `CLAUDE.md`,
`CLAUDE.local.md`, `reports/`, `routines/`). Easiest transfer between two Macs: **AirDrop**. Otherwise
a USB drive or a private cloud folder.
> ⚠️ `CLAUDE.local.md` contains the SSH password in plain text. Transfer privately (AirDrop / USB) —
> don't email it or drop it on a shared/public link.

### 2. Install the few tools the workflow uses (new Mac)
- **Claude Code** (sign in with the new account).
- **sshpass** — needed to reach the server:
  `brew install hudochenkov/sshpass/sshpass`
- **php** and **node** (recommended, for the pre-deploy syntax checks):
  `brew install php node`  (rsync & ssh are already built into macOS)

### 3. Open Claude Code in the folder & verify
Open Claude Code with this folder as the working directory. Ask it: *"read CLAUDE.md and confirm the
connection"* — or just run this test in its terminal (password is in `CLAUDE.local.md`):
```
sshpass -p '<password>' ssh -o ConnectTimeout=25 -o StrictHostKeyChecking=accept-new -p 65002 \
  u929828006@46.202.197.46 "cd domains/palegoldenrod-coyote-386848.hostingersite.com/public_html && php cron-daily-camp-notes.php"
```
If it prints the per-camp JSON, you're fully connected — deploys, DB checks, everything works.

### 4. Recreate the routine(s)
Scheduled tasks live in `~/.claude/scheduled-tasks/` (outside this folder), so they don't travel.
Recreate them from `routines/` in this project — for the daily camp notes, follow
[routines/daily-camp-notes.md](routines/daily-camp-notes.md) (paste the prompt, schedule 8 AM daily).
Then click **"Run now"** once to approve the SSH step so future runs don't pause on a prompt.

---

## Optional: re-pull the latest code to be 100% sure
This folder already matches production (all changes are edited here, then rsynced up). But if you want
certainty, from the new machine:
```
sshpass -p '<password>' rsync -az -e "ssh -p 65002" \
  u929828006@46.202.197.46:"domains/palegoldenrod-coyote-386848.hostingersite.com/public_html/" ./
```

## What does NOT need moving
- The app / database — stays on Hostinger, untouched.
- Global `~/.claude/CLAUDE.md` (personal prefs) — the project works without it.
- Published Artifacts — none were used for this project (reports are local files).
