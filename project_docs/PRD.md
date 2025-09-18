Got it — here’s the full regenerated Markdown project knowledge doc for ReleaseIt.ai (MVP). This version is continuous and complete.

⸻


# ReleaseIt.ai — Project Knowledge (MVP)

## Overview
**Problem:**  
High-functioning PMs (especially ADHD-leaning) excel at vision but struggle with execution details, stakeholder management, and information overload (Slack, email, meetings).  

**Solution:**  
ReleaseIt.ai is a **PM’s AI companion** that provides lightweight release/workstream structure and offloads minutiae:  
- Summarizes ingested inputs (emails, pasted threads, uploads)  
- Generates release notes & GTM collateral  
- Provides daily briefs & recaps  
- Manages release checklists  

**Principle:**  
Basecamp-style: lean, opinionated, small team, profitable early.  

---

## Architecture

- **Framework:** Laravel 11 + Inertia.js + Vue 3 + Tailwind CSS  
- **Auth:** Laravel Breeze + Sanctum (session-based, magic link optional)  
- **Queue:** Redis + Horizon (AI jobs, email ingestion, digests)  
- **DB:** Postgres (DigitalOcean Managed)  
- **Storage:** AWS S3 (private buckets for docs/uploads)  
- **Email Intake:** AWS SES → SNS → Laravel webhook → queue  
- **AI:** OpenAI GPT-4o-mini + Anthropic Claude 3.5 Sonnet via `AiService` abstraction  
- **Hosting:** DigitalOcean App Platform or Droplets (app + workers + DB)  

---

## Core Domain & Tables

**Entities**
- `users` (solo PM initially)  
- `workstreams` (product lines; user-scoped)  
- `releases` (belongs to workstream)  
- `checklist_templates` (seeded: Patch / Feature / Major)  
- `checklist_items` (per release; generated or ad-hoc)  
- `stakeholders` (contacts with role, timezone, notes)  
- `ingestions` (email/file/paste events)  
- `documents` (uploaded files; stored in S3)  
- `notes` (AI-generated summaries, release notes, collateral)  
- `tasks` (AI-extracted actions; linked to release or standalone)  
- `ai_jobs` (log of AI calls: type, tokens, cost, status)  
- `briefs` (daily/weekly snapshots)  

---

## Routes & Screens

### Routes
- `/` → Dashboard (Top 3 priorities, Quick Add, upcoming items)  
- `/workstreams` → List/create workstreams  
- `/workstreams/{id}` → Workstream overview  
- `/releases` → List releases  
- `/releases/{id}` → Release Hub (checklist, status, AI outputs, exports)  
- `/ingest` → POST endpoints for SES webhook, paste, uploads  
- `/settings` → Profile, trial caps, API keys (future)  

### Inertia Pages
- `Dashboard/Index.vue` (Top 3, Quick Add, Upcoming)  
- `Workstreams/Index.vue`, `Workstreams/Show.vue`  
- `Releases/Index.vue`, `Releases/Show.vue` (tabs: Checklist | Notes | Collateral | Files)  
- `Settings/Index.vue`  

### Shared Components
- `QuickAdd.vue` → Paste box → AI parse → preview tasks/notes  
- `Checklist.vue` → Inline items with due dates & assignees (text only MVP)  
- `AiOutput.vue` → Release notes, GTM drafts with copy/export  
- `UploadDropzone.vue` → Presigned S3 uploads  
- `PriorityCard.vue` → Task display (title, context)  

---

## Email Intake (SES)

1. **Setup:**  
   - Verify domain in SES.  
   - MX record → SES inbound.  
   - SES Rule → SNS topic → HTTPS subscription to `/ingest/email`.  

2. **Flow:**  
   - SNS hits `IngestionController@ses`.  
   - Validate → persist `ingestions` row → queue `ProcessEmailIngestion`.  
   - Job extracts body/attachments, stores docs in S3.  
   - Calls `AiService::summarizeEmailThread()`.  
   - Creates `notes` + `tasks`, linked to release if possible.  

3. **Daily Digest:**  
   - Scheduled job batches emails → AI → `briefs`.  

---

## Quick Add Flow

- User pastes text into Quick Add box.  
- Backend stores `ingestions` (type=paste).  
- Job `ProcessPasteIngestion`:  
  - Detects intent (tasks, notes, status).  
  - Extracts tasks & deadlines.  
  - Summarizes into note.  
- Returns parsed items immediately, finalizes in background.  

---

## AI Service Abstraction

```php
interface AiService {
    public function summarizeEmails(array $msgs): AiResult;
    public function extractTasks(string $text, ?int $releaseId=null): AiResult;
    public function generateReleaseNotes(array $inputs): AiResult;
    public function generateCollateral(array $context, array $variants): AiResult;
}

	•	Router chooses model:
	•	Cheap (extraction) → GPT-4o-mini / GPT-3.5 / OSS.
	•	Polished (release notes, collateral) → Claude 3.5 / GPT-4o.
	•	All calls logged in ai_jobs (tokens, cost, status).

⸻

Briefs (Daily / Weekly)
	•	Morning Brief:
	•	“Top 3 priorities” (due soon, overdue, high-priority tasks).
	•	Evening Recap:
	•	Completed vs open tasks.
	•	Suggested tomorrow’s focus.
	•	Delivered via dashboard + email.

⸻

Collateral Generator (MVP)
	•	Input: short feature description.
	•	Output:
	•	Release notes (customer-facing).
	•	Internal summary (exec/eng).
	•	Sales one-pager (bullets, value props).
	•	Customer email (subject + body).
	•	Stored in notes table with type.
	•	Export: Markdown, copy to clipboard.

⸻

Exports
	•	Markdown download.
	•	Copy to clipboard.
	•	Future: Google Docs / Notion API.

⸻

Migrations (Outline)
	•	users
	•	workstreams (id, user_id, name, description)
	•	releases (id, workstream_id, name, type, status, planned_date, notes_json)
	•	checklist_templates (id, name, items_json)
	•	checklist_items (id, release_id, title, due_date, assignee_text, done_at)
	•	stakeholders (id, user_id, name, email, role, timezone, notes)
	•	ingestions (id, user_id, release_id nullable, type enum[email,paste,upload], subject, meta_json, size_bytes)
	•	documents (id, user_id, release_id nullable, s3_key, original_name, mime, size)
	•	notes (id, user_id, release_id nullable, title, body_md, kind enum[summary,release_notes,collateral], meta_json)
	•	tasks (id, user_id, release_id nullable, title, due_date nullable, owner_text, status enum[open,done], source_ingestion_id)
	•	ai_jobs (id, user_id, kind, status, prompt_tokens, completion_tokens, cost_usd, meta_json)
	•	briefs (id, user_id, date, morning_json, evening_json)

⸻

Cost Control (Trial Caps)
	•	Free trial limits (per user):
	•	Emails ingested: 200
	•	Docs uploaded: 10
	•	AI summaries: 20
	•	Middleware enforces → upsell screen if exceeded.
	•	Batching: emails summarized daily, not per-email.
	•	Hybrid models: small model for parsing, big model for polished output.

⸻

Security
	•	Store raw emails minimally, redact long-term PII where possible.
	•	S3 private; signed URLs for access.
	•	Policies for row-level auth.
	•	Encrypt all secrets, rotate keys.
	•	Audit logs for ingestions + AI jobs.

⸻

Milestones (8–10 Weeks)

Week 1–2
	•	Auth, schema, workstreams/releases, checklist UI.

Week 3–4
	•	Quick Add flow + AI task extraction.
	•	Dashboard Top 3 priorities.

Week 5
	•	SES inbound → email → AI → notes/tasks.

Week 6
	•	Collateral generator + exports.

Week 7
	•	Trial caps + billing (Stripe).
	•	Settings page.

Week 8
	•	Polish UX, Sentry, Horizon monitoring, onboarding flow.

Beta Weeks 9–10
	•	Harden ingestion parsing.
	•	Batch jobs for email digests.
	•	Performance review.

⸻

Success Criteria
	•	100+ trial signups in 3 months.
	•	15–20% conversion → paid.
	•	70% active users engage 3+ times/week.
	•	Avg 20+ AI interactions/user/mo.
	•	Retention after 3 months >60%.

⸻


