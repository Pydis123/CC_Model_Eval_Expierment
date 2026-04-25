# Follow-ups

Småsaker som inte hör till pågående plan men ska åtgärdas senare.

## ProcessClaudeCli — PATH-upplösning

`runner/src/Dispatch/ProcessClaudeCli.php` använder hårdkodat `'claude'` som kommandonamn. Fungerar när runnern körs från interaktiv zsh/bash där användarens PATH inkluderar `~/.local/bin/` (eller var claude än ligger).

**Problem:** `claude`-binären ligger typiskt i `~/.local/bin/claude` eller `~/.claude/local/node_modules/.bin/claude`, vilket inte är i default-PATH för non-interactive shells (t.ex. `cron`, `launchd`, `systemd`, PHP:s `proc_open` utan explicit env).

**Bekräftat 2026-04-23:** Verklig smoke-test failade tills `PATH="$HOME/.local/bin:$PATH"` exporterades explicit innan runner-anropet.

**Förslag på fix (ett av):**
1. `bin/cli` wraps `exec` med explicit PATH från `$_SERVER['HOME'] . '/.local/bin'` + default-PATH.
2. `ProcessClaudeCli` tar `string $claudeBinary` som redan är absolut väg — `bin/cli` upplöser den via `which claude` på startup och avbryter tydligt om inte hittad.
3. Lägg till `EXPERIMENT_CLAUDE_BINARY` env-variabel som override, annars `which claude` fallback.

Alternativ 2 är mest robust (felar snabbt vid startup, deterministiskt i loggar).

**Impact:** Ingen för manuell `run-all`-körning från interaktiv terminal. Blocker för framtida cron- eller launchd-schedulering.
