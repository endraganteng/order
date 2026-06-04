# CLAUDE.md — Project Rules for Claude Code

## Anti-Stall and Checkpoint Workflow

Rules wajib untuk semua sesi kerja:

- Work in small visible steps.
- Never silently scan the whole project.
- Never silently run long commands.
- Before using tools, explain the immediate goal.
- After reading important files, summarize findings.
- Before editing, explain the planned change.
- After editing, create a checkpoint.
- If interrupted or resumed, continue from the latest checkpoint.
- Do not restart the task from the beginning unless explicitly asked.
- If uncertain, inspect the smallest relevant file set.
- Do not modify unrelated files.
- Do not touch `.env`, `vendor`, `node_modules`, logs, cache, or build output.
- Prefer small safe fixes over large refactors.
- For Laravel bugs, identify controller/service/view/route involved before editing.
- For Firebase/RTDB logic, avoid changing data structure unless explicitly requested.
- For finance/bonus/shift logic, preserve existing business rules unless asked to change them.

## Response Behavior

- Give short progress updates during long tasks.
- Clearly say what has been done and what remains.
- If a command fails, stop and explain the failure.
- If the next step is risky, ask before continuing.
- When "lanjut", continue from the previous checkpoint.
- When "status", summarize current progress, open questions, and next step.

## Project Context

- Laravel app with Firebase Realtime Database (RTDB)
- PHP backend, Blade templates
- Core services: BonusService, FirebaseService, PayrollService, SalesCampaignService
- Key directories: `app/Services/`, `app/Http/Controllers/`, `resources/views/`
- Tests: `tests/Unit/Services/`, run with `php artisan test --filter=...`
