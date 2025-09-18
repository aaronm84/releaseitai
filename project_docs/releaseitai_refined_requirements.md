# ReleaseIt.ai — Refined Requirements (MVP)

## 1. Overview
**Problem**  
Product managers deal with too many emails, Slack messages, meetings, and shifting priorities. This makes it easy to lose track of details, forget follow-ups, or miss deadlines.

**Solution**  
ReleaseIt.ai is an **AI assistant for product managers**.  
It works like an “external brain” that:  
- Collects information from emails, pasted text, or uploaded documents.  
- Summarizes inputs into clear notes and tasks.  
- Helps manage releases with checklists and status updates.  
- Creates release notes and go-to-market collateral.  
- Sends daily briefs and end-of-day recaps.  

**Principle**  
Keep it simple. Ship a focused tool that delivers value fast. Build with a lean team and stay profitable.  

---

## 2. Target User
**Persona**  
- A product manager with ADHD-like challenges.  
- Good at strategy and vision.  
- Struggles with remembering details, following up, and filtering noise.  

**Context**  
- Manages multiple product lines or projects.  
- Works with engineers, designers, GTM, and vendors.  
- Needs help staying on top of priorities and tasks.  

---

## 3. Core User Flows
1. **Morning Brief**: AI shows top 3 priorities, key deadlines, and open tasks.  
2. **Quick Add**: User pastes notes, Slack text, or meeting points → AI turns them into tasks.  
3. **Release Hub**: Create a release → auto-checklist, deadlines, AI-generated release notes.  
4. **Collateral Generation**: User provides a short feature description → AI creates sales one-pager, customer email, or internal summary.  
5. **End-of-Day Recap**: AI sends summary of completed vs pending tasks, plus suggestions for tomorrow.  
6. **Email Forwarding**: Each user gets a unique email alias → forwarded emails are summarized and parsed into tasks.  

---

## 4. Functional Requirements (MVP)
- User authentication (magic link, session-based).  
- Dashboard with Morning Brief + Quick Add.  
- Release Hub with checklist templates (Patch / Feature / Major).  
- Email ingestion (AWS SES → SNS → webhook).  
- File/document uploads (stored in AWS S3).  
- AI task extraction, summaries, release notes, and collateral generation.  
- Daily briefs and end-of-day recaps (UI + email).  
- Export to Markdown or clipboard.  
- Trial usage caps (e.g., 200 emails, 10 docs, 20 AI summaries).  

**Non-MVP (Future)**  
- Slack/Jira/GitHub/Zoom integrations.  
- Multi-PM org accounts.  
- Calendar ingestion (ICS).  
- OCR for screenshots.  
- Voice dictation capture.  

---

## 5. Non-Functional Requirements
- **Performance**: AI responses under 10 seconds.  
- **Scalability**: Support at least 1,000 users on small infra.  
- **Reliability**: Daily briefs must always run.  
- **Security**: Encrypt data in storage and transit. Use private S3 buckets.  
- **Usability**: Clean, ADHD-friendly UI. Focus on “Top 3 priorities.”  

---

## 6. Tech Stack
- **Backend**: Laravel 11  
- **Frontend**: Inertia.js + Vue 3 + Tailwind CSS  
- **Database**: Postgres (DigitalOcean Managed)  
- **Queue/Cache**: Redis + Horizon  
- **Storage**: AWS S3  
- **Email ingestion**: AWS SES inbound + SNS webhook  
- **AI services**: OpenAI GPT-4o-mini + Anthropic Claude 3.5 Sonnet (via AiService abstraction)  
- **Hosting**: DigitalOcean (Forge-managed servers or App Platform)  

---

## 7. Success Metrics
- 100 trial signups in first 3 months.  
- 15–20% conversion from trial to paid.  
- 70% of active users engage 3+ times per week.  
- Average 20+ AI interactions per user per month.  
- Retention after 3 months above 60%.  

---

## 8. Risks and Mitigations
- **AI cost overrun** → use trial caps + batch processing.  
- **Adoption risk** → design flows that deliver value without integrations.  
- **Perception (“another PM tool”)** → position as *assistant*, not platform.  
- **Privacy concerns** → strong security defaults, clear data retention rules.  

---

## 9. Release Plan (8–10 weeks MVP)
**Week 1–2**: Auth, schema, workstreams/releases, checklist UI.  
**Week 3–4**: Quick Add + AI task extraction; Dashboard Top 3.  
**Week 5**: Email ingestion pipeline.  
**Week 6**: Collateral generator + exports.  
**Week 7**: Trial caps + billing integration.  
**Week 8**: UX polish, onboarding, monitoring.  
**Weeks 9–10**: Beta testing, hardening, performance checks.  
