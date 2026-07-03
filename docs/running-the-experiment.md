# Running the experiment

A step-by-step guide for someone who has **never** used Claude Code, has **no**
software-development experience, and is new to macOS.

If you already know the tools, skip to [Part 5 — Initialize the experiment](#part-5--initialize-the-experiment).

---

## What you are about to do

You are going to run a scientific experiment. The experiment measures how well
four Claude model tiers (Haiku, Sonnet, Opus, Fable) perform at small,
realistic coding tasks when each model is given the same task under the same
conditions.

The experiment runs **160 task executions** in total: 8 tasks × 4 model tiers
× 5 replicates. Each execution gets up to 3 retry iterations. The runner
program performs the executions one after another, completely automatically.
You start it, walk away, and come back when it is done.

**Approximate time:** 10–30 hours of wall-clock time, mostly waiting on the
Claude rate-limit to reset between dispatches. Your computer can be doing other
things during this time.

**Approximate cost:** included in an Anthropic "Claude Max" subscription. The
experiment does not use the paid API — it uses your subscription via the
Claude Code command-line tool.

**What it produces:** a file named `docs/findings.md` with tables and numbers
comparing the four tiers on cost, speed, and success rate.

---

## Part 1 — What you need to know

Some vocabulary you will see in this guide:

| Term | Plain meaning |
|------|---------------|
| Terminal | A text-window on your Mac where you type commands. You open it by pressing `Cmd+Space`, typing `Terminal`, and pressing Enter. |
| Command | A line of text you type into Terminal and press Enter to run. |
| Shell prompt | The text that appears in Terminal before the cursor, often ending with `$` or `%`. In this guide, command lines start with `$`; you do **not** type the `$`. |
| Directory / folder | Same thing. Directories hold files. |
| Path | The location of a file, e.g. `/Users/yourname/Documents/report.pdf`. |
| Repository (repo) | A folder tracked by the `git` tool. This experiment lives in one repo. |
| Clone | Download a repo to your computer. |
| macOS package manager | A program that installs other programs. We will use one called **Homebrew**. |

You do not need to understand code. You just need to type commands carefully.
Copy-paste is your friend.

---

## Part 2 — Prepare your Mac

Do these steps **in order**. After each command, wait for it to finish (the
shell prompt reappears) before starting the next one.

### 2.1 Open Terminal

1. Press `Cmd + Space` to open Spotlight search.
2. Type `Terminal` and press Enter.

A window opens with a shell prompt. You will run every command in this window.

### 2.2 Install Homebrew

Homebrew is a package manager for macOS. It installs other software.

Paste this whole line into Terminal and press Enter:

```
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

It will ask for your Mac login password (the one you use to log into your
Mac). Type it and press Enter. The password will not appear as you type — this
is normal.

The install takes a few minutes. When it finishes, **close Terminal and open
it again** so the new installation is picked up.

Verify it worked:

```
$ brew --version
```

You should see something like `Homebrew 4.3.0` (the number will differ).

### 2.3 Install git

Git is the tool that downloads the experiment code.

```
$ brew install git
```

Verify:

```
$ git --version
```

You should see `git version 2.XX.X`.

### 2.4 Install PHP 8.4

PHP is the programming language the experiment code is written in.

```
$ brew install php@8.4
```

```
$ php --version
```

You should see a line starting with `PHP 8.4.X`. If you see an older version,
run:

```
$ brew link --overwrite --force php@8.4
```

Then try `php --version` again.

### 2.5 Install Composer

Composer is a dependency manager for PHP. The experiment uses it to install
PHP libraries.

```
$ brew install composer
```

Verify:

```
$ composer --version
```

You should see `Composer version 2.X.X`.

### 2.6 Install Docker Desktop

Docker runs the MariaDB database that the experiment's mock project uses.

1. Go to https://www.docker.com/products/docker-desktop/
2. Click **Download for Mac** — there are two options (Apple Silicon vs Intel).
   To check which you need, click the Apple logo in the top-left of your
   screen → **About This Mac**. If it says "Apple M1", "M2", "M3", or "M4",
   you need Apple Silicon. Otherwise you need Intel.
3. Open the downloaded `.dmg` file and drag the Docker icon into the
   Applications folder.
4. Open **Applications** in Finder and double-click **Docker**. Accept the
   terms of service. Wait until the Docker whale icon in the menu bar stops
   animating.

Verify:

```
$ docker --version
$ docker compose version
```

Both commands should print version numbers. Also run:

```
$ docker ps
```

This should print an empty table (just the column headers). If it says
"Cannot connect to the Docker daemon", Docker Desktop is not running — start
it from Applications.

### 2.7 Install Node.js (for Claude Code)

Claude Code needs Node.js to run.

```
$ brew install node
```

```
$ node --version
```

You should see `v20.X.X` or higher.

### 2.8 Install Claude Code

Claude Code is the command-line tool that orchestrates the experiment.

```
$ npm install -g @anthropic-ai/claude-code
```

If the command fails with a permission error, run:

```
$ sudo npm install -g @anthropic-ai/claude-code
```

…and enter your Mac password when asked.

Verify:

```
$ claude --version
```

You should see a version number.

**Important check:** find out where `claude` was installed.

```
$ which claude
```

If the output starts with `/opt/homebrew/` or `/usr/local/`, you are fine.
If it starts with `/Users/YOUR_NAME/.local/bin/` or `/Users/YOUR_NAME/.claude/`,
the runner may not find `claude` automatically later. **Write down the full
path** — you will need it in Part 6.

### 2.9 Log into Claude Code

Start Claude Code from Terminal (anywhere — it doesn't matter which folder
you are in):

```
$ claude
```

On first run it opens a browser window asking you to log in with your
Anthropic account. Log in. The browser should say "Success" and you can close
the tab. Back in Terminal, you should see a Claude Code interface.

Type `/exit` and press Enter to close Claude Code. Your login is saved.

---

## Part 3 — Download the experiment

### 3.1 Pick a place to keep it

Choose a folder where you want the experiment to live. A good choice is
your home folder. In Terminal:

```
$ cd ~
```

(`~` is a shortcut for your home folder.)

### 3.2 Clone the repo

```
$ git clone https://github.com/ANDERS/llm-dispatch-experiment.git
```

(Replace `ANDERS` with the actual GitHub path — ask the experiment author if
you don't have it.)

Enter the folder:

```
$ cd llm-dispatch-experiment
```

From now on, **every command in this guide assumes you are inside this folder**.
If you close Terminal and open a new one, you need to navigate back:

```
$ cd ~/llm-dispatch-experiment
```

### 3.3 Install PHP dependencies

The experiment has two PHP components (the runner and the mock project).
Install dependencies for both:

```
$ cd runner
$ composer install
$ cd ..
$ cd mock-project
$ composer install
$ cd ..
```

Each `composer install` takes 1–2 minutes the first time.

### 3.4 Start the database

```
$ docker compose up -d
```

The first time this runs, Docker will download the MariaDB image (~300 MB).
It takes a few minutes on a normal internet connection. Wait for the prompt
to return.

Verify the database is running:

```
$ docker compose ps
```

You should see a line for `mariadb` with status `running (healthy)`. If the
status is only `running` without `(healthy)`, wait 30 seconds and run the
command again.

### 3.5 Apply database migrations and seed data

```
$ cd mock-project
$ php tools/migrate.php
$ php tools/seed_demo.php
$ cd ..
```

The migrate command creates all the tables. The seed command inserts a handful
of demo rows. Both should complete without errors.

---

## Part 4 — Verify your setup

Before running the real experiment, confirm everything works.

### 4.1 Runner test suite

```
$ cd runner
$ vendor/bin/phpunit
$ cd ..
```

You should see a lot of dots and then:

```
OK, but there were issues!
Tests: 292, Assertions: 833, PHPUnit Deprecations: 1, Skipped: 1.
```

The "issues" wording looks alarming but only means "1 pre-existing deprecation
warning and 1 optional test was skipped". If the last line starts with **OK**
you are fine. If it starts with **FAILURES** or **ERRORS**, something is
wrong — see [Part 6 — Troubleshooting](#part-6--troubleshooting).

### 4.2 CLI help

```
$ php runner/bin/cli
```

You should see a list of commands including `state init`, `run-all`, `report`,
and others. Exit code is 0.

### 4.3 Claude binary smoke (optional but recommended)

This check costs ~$0.005 in subscription tokens and confirms Claude Code is
really reachable.

```
$ cd runner
$ vendor/bin/phpunit --filter=RealClaudeSmokeTest
$ cd ..
```

If it passes (1 test, green), your entire stack is confirmed working.
If it skips, either `claude` is not on the PATH (see Part 2.8) or the test was
told to skip.

---

## Part 5 — Initialize the experiment

This fills `state.json` with the 160 runs the experiment will execute.

### 5.1 Seed the run queue

```
$ php runner/bin/cli state init
```

You should see confirmation that 160 runs were generated. The file
`state.json` at the repo root is now populated.

### 5.2 Record which model versions are pinned

The experiment needs to know exactly which model ID each tier resolves to
so you can detect if Anthropic silently swaps the underlying model
mid-experiment. You provide the IDs explicitly; the runner stores them in
`state.json`.

```
$ php runner/bin/cli state pin-models \
    --haiku=claude-haiku-4-5-20251001 \
    --sonnet=claude-sonnet-5 \
    --opus=claude-opus-4-8 \
    --fable=claude-fable-5
```

Replace each value with the model IDs you want to use. Anthropic's
currently-published model IDs at the time of writing are roughly of the form
shown above — but check Anthropic's docs for the latest. Dated IDs (with the
`-YYYYMMDD` suffix) detect silent swaps better than aliases.

If you need to overwrite an already-pinned set, append `--force`.

Verify the output prints the four IDs you supplied. If anything goes wrong,
see [Part 6 — Troubleshooting](#part-6--troubleshooting).

---

## Part 6 — Run the experiment

### 6.1 Start the run

```
$ php runner/bin/cli run-all
```

That is the whole command. The runner now:

1. Picks the next run from the queue.
2. Creates a temporary working copy of the mock project in `/tmp/`.
3. Dispatches Claude with the task prompt using the pinned model for this run.
4. Waits for Claude to finish (or hit a rate-limit).
5. Runs the success checks (PHPUnit tests, query-count probes, etc.) against
   the working copy.
6. Appends one line to `results/results.jsonl` describing the run.
7. Moves on to the next run.

Progress for queue-empty, abort, and rate-limit events is written to
`results/runner.log`. **Per-run progress is NOT printed during the dispatch
itself** — the runner reads Claude's output as a single buffer and only
emits log lines at lifecycle events. The terminal will appear silent for
minutes at a time during normal operation. This is expected.

### 6.2 What you will see (and not see)

Lifecycle events that DO print:
- Queue empty (when all 160 runs are done)
- Unexpected-error counter increments
- Abort after 5 consecutive unexpected errors
- Rate-limit sleeps and wake-ups

What does NOT print during normal operation:
- Per-run "starting" / "finished" lines
- Token counts per dispatch
- Iteration progress within a run

To check progress while `run-all` is running, open a second terminal and:

```
$ wc -l results/results.jsonl     # how many runs are done
$ tail -F results/results.jsonl   # watch new rows append
```

A typical single run takes 30–120 seconds of Claude time, plus any rate-limit
wait. Expect total wall-clock time of 10–30 hours for the full 160.

### 6.3 If you need to stop

Press `Ctrl + C` in Terminal. The current run will be interrupted but the
queue state is preserved. Restart later with the same `php runner/bin/cli run-all`
command — it will pick up where it stopped.

If a run was interrupted mid-dispatch, its `claimedAt` timestamp remains. To
clear stale claims before restarting:

```
$ php runner/bin/cli state reset-stale --older-than=1h
```

This clears any claim older than 1 hour.

### 6.4 If the runner aborts itself

After **5 consecutive unexpected errors** (errors that are not normal task
failures — e.g. the Claude CLI crashed, the mock project's git worktree
failed to create), the runner writes a crash dump at
`results/runner-crash-<timestamp>.json` and exits with code 10.

Read the crash dump. It lists the last 5 errors with context. Common reasons:

- Disk full (check `/tmp/` free space)
- Docker stopped (restart Docker Desktop)
- Anthropic service outage (check https://status.anthropic.com/)

Once you have fixed the underlying issue, run `run-all` again — it resumes
from where it stopped.

---

## Part 7 — When it is done

### 7.1 Generate the report

```
$ php runner/bin/cli report
```

This reads `results/results.jsonl`, aggregates the 160 runs, runs a Monte Carlo
simulation for the alternative escalation policy, and writes
`docs/findings.md`.

### 7.2 Read the findings

Open `docs/findings.md` in any text viewer:

```
$ open docs/findings.md
```

(This opens it in macOS's default Markdown viewer — usually TextEdit or Quick
Look. You can also open it in any editor you like.)

The report contains:

- Per-cell summary: for each `(task, tier)` pair, how many passed, average
  cost, average wall-clock time
- Cross-tier comparison: which tier is cheapest, fastest, most reliable
- Policy-B simulation results: what would have happened if we used
  cheapest-first escalation instead of retry-only
- Method and limitations references

### 7.3 (Optional) Share the results

Commit `docs/findings.md` and `results/results.jsonl` to git and push. Ask
the experiment author for instructions.

---

## Part 8 — Troubleshooting

### `claude: command not found` when the runner dispatches

Either Claude Code was not installed, or it was installed to a location not
on the default system PATH.

First, check if it is installed at all:

```
$ which claude
```

If this prints nothing, go back to Part 2.8 and install Claude Code.

If `which claude` prints a path that starts with `/Users/YOUR_NAME/.local/bin/`
or similar (not `/opt/homebrew/bin/` or `/usr/local/bin/`), the runner may
not find it. The simplest fix: extend your shell PATH to include that
directory. Add this line to the end of `~/.zshrc`:

```
export PATH="$HOME/.local/bin:$PATH"
```

Then close Terminal and open a new one.

### `Cannot connect to the Docker daemon`

Docker Desktop is not running. Open **Applications** in Finder and start
**Docker**. Wait for the whale icon in the menu bar to stop animating.

### `port 3307 is already in use`

Something else on your Mac is using port 3307. Most likely you have a previous
run of the experiment (or another MariaDB instance) already running. Stop it:

```
$ docker compose down
```

If the error persists, run:

```
$ lsof -iTCP:3307 -sTCP:LISTEN
```

This tells you which process is using the port. Close that program, then
`docker compose up -d` again.

### PHPUnit shows `FAILURES` during verification (Part 4.1)

1. Make sure Docker is running and the database is healthy
   (`docker compose ps` should show `running (healthy)`).
2. Make sure migrations and seeds were applied (Part 3.5).
3. Make sure `composer install` ran successfully in both `runner/` and
   `mock-project/`.

If tests still fail, the output will point at which test failed. Open a
support channel with the experiment author and share the output.

### Rate-limit sleep feels too long

This is normal. When Claude's subscription rate-limit is hit, the runner waits
until the limit resets (up to 5 hours) before continuing. You can verify
things are still alive by looking at `results/runner.log` — the last line
should be something like `sleeping until 2026-04-24T17:00:00Z`.

### `No pinned model for tier: X` during `run-all`

You skipped or partially completed Part 5.2. Run it with the four model IDs:

```
$ php runner/bin/cli state pin-models --haiku=<id> --sonnet=<id> --opus=<id> --fable=<id>
```

### The experiment aborted with 5 consecutive errors

See Part 6.4. Read `results/runner-crash-*.json`, fix the underlying cause,
run `run-all` again.

### I want to start over completely

This deletes all experiment state and results:

```
$ rm state.json results/results.jsonl results/runner.log
$ rm -rf /tmp/llm-disp-run-* worktrees/failed
$ docker compose down -v
$ docker compose up -d
$ cd mock-project && php tools/migrate.php && php tools/seed_demo.php && cd ..
$ php runner/bin/cli state init
$ php runner/bin/cli state pin-models --haiku=<id> --sonnet=<id> --opus=<id> --fable=<id>
```

**Warning:** `docker compose down -v` wipes the database volume. Only do this
if you really want to start from scratch.

---

## Appendix — File and log locations

| Path | What it contains |
|------|------------------|
| `state.json` | The queue of pending runs, completed runs, and pinned model IDs |
| `results/results.jsonl` | One JSON line per completed run — the raw data the report builds on |
| `results/runner.log` | Progress log from `run-all` (human-readable) |
| `results/runner-crash-*.json` | Abort-time crash dumps |
| `/tmp/llm-disp-run-<id>/` | Per-run git worktree (auto-cleaned up if run passes) |
| `worktrees/failed/` | Kept worktrees from runs that failed (for debugging) |
| `docs/findings.md` | The final report, generated from `results/results.jsonl` |
| `docs/methodology.md` | Explanation of the experiment design |
| `docs/limitations.md` | Known caveats when interpreting the report |

If anything else goes wrong, the runner log and crash dumps are the first
place to look.
