# Wafiq (وافِق) — Send Quotes & Invoices, Track Them, Get the Approval

> **Status:** idea + build plan (October 2026).
> **Edition 1 = Picalica (self-hosted, one company per install).** Edition 2 = SaaS later, from the **same codebase** (see section 6).
> **Stack:** Laravel 12 · Inertia.js 2 · React 19 (**JavaScript / JSX, no TypeScript**) · Tailwind CSS 4 · Vite · MySQL / MariaDB.
> **Language:** Arabic (RTL) only in v1. Every string lives in one language file, so English can be added later.
> **Sold as:** a one-time script on Picalica. Each buyer installs it on their own hosting, with **unlimited users, clients and documents.**
> **Separate from DraftYes:** this is its own product and brand. It shares no code, domain or marketing with the DraftYes project.
> The feature checklist at the end of this file is for you to mark: delete or `~~strike~~` anything you don't want in v1.

---

## 1. The product in one paragraph

**Wafiq** lets a company's team create **professional Arabic quotations and invoices**, **send them by email or WhatsApp with a tracked link**, and **follow every document until the client answers**: the team sees when the client **opened** it, and whether they **approved** it, **rejected** it, or let it **expire**. Every step notifies the team by email and in the app. The team collaborates on each document with comments, @mentions and assignments, and logs in with a **magic link** (no passwords). The company pays **once**, installs it on its own server, and keeps all its data.

### Name
- **وافِق (Wafiq)** means "approve!": it's the one button we want every client to press. It's short, easy to say in every Arabic dialect, and clear in Latin script.
- **Arabic tagline:** «وافِق — أرسل عرض السعر أو الفاتورة، تابع فتحها، واحصل على موافقة عميلك بضغطة.»
- **Picalica title:** «وافِق – نظام إرسال وتتبع عروض الأسعار والفواتير والموافقة عليها»
- ⚠️ **Before using it publicly:** check Picalica, Google, the Saudi / UAE / Egyptian trademark registers and domain availability (e.g. `wafiq.app`, `getwafiq.com`, `wafiq.sa`). If it's taken, keep the same idea: a one-word Arabic verb that the client "does" (alternatives to check: **وقّع** Waqqi', **اعتمِد** I'tamid, but **not** "اعتماد / Etimad", which is a Saudi government platform).

### Our website (domain to register)
The product is self-hosted, so buyers run it on **their own** domain and send email from **their own** address. Our domain is the marketing and support home:

| Address | Use |
|---|---|
| `{domain}` | Landing page (Arabic): features, video, screenshots, "Buy on Picalica" button (later: "Start free" for the SaaS) |
| `demo.{domain}` | Live demo (demo mode: read-only + nightly reset), linked from the Picalica listing |
| `{domain}/docs` | Arabic documentation (install, cron & mail, user guide, FAQ) |
| `support@{domain}` | Support email for buyers |
| `{domain}/updates` | Changelog + download notes for new versions |

---

## 2. Who buys it

| Buyer | Why they want it |
|---|---|
| **Small companies** (contracting, maintenance, printing, events, IT services, wholesale, interior design) | Many quotes per week, several sales staff, and they want to know **who opened the quote and who is waiting for an answer** |
| **Agencies & studios** | Send branded proposals fast, see when the client opened them, get a clear online approval instead of "OK" in a chat |
| **Web developers on Picalica** (commercial licence) | Install it for their clients as a paid service |

**The promise:** "Stop sending Word files and asking 'did you see it?'. Send a link by WhatsApp or email, see the moment your client opens it, and get their approval in one click."

---

## 3. The document workflow (the heart of the product)

The same six statuses apply to **quotations and invoices**:

| Status | Arabic | Meaning | Set by |
|---|---|---|---|
| **Draft** | مسودة | Being prepared; only the team sees it | Created by a member |
| **Sent** | مُرسَل | Shared with the client by email, WhatsApp or link | Member sends it (email sent, WhatsApp share opened, or link copied and marked as sent) |
| **Viewed** | تمت المشاهدة | The client opened the link | Automatically, on the client's first real view |
| **Approved** | موافَق عليه | The client approved it online | The client (typed name + agreement checkbox) |
| **Rejected** | مرفوض | The client declined it (with an optional reason) | The client |
| **Expired** | منتهي | The validity date passed without an answer | Automatically (scheduler) |

```
            ┌───────── extend validity / resend ─────────┐
            ▼                                             │
 Draft ──► Sent ──► Viewed ──► Approved ✓                 │
             │         │                                  │
             │         └─────► Rejected ✗                 │
             └─────────┴─────► Expired ⏱ ─────────────────┘
```

### Rules
- **Approved** and **Rejected** are final for that version. To change an approved or rejected document, the team creates a **new revision** (v2), which starts as Draft.
- **Expired** can be brought back: **extend validity** + resend, and it goes back to **Sent**.
- **Editing a sent document** creates a **revision**. The old link then shows "this version was replaced" with a link to the newest one, so the client never approves an outdated price.
- **Invoices** also have a separate **payment status**: unpaid / partially paid / paid / overdue. "Approved" means the client confirmed the invoice; payment is tracked on its own.
- **Approved quotation → Convert to invoice** in one click (items, client and terms copied; documents linked).
- **Internal review** (optional): above an amount limit, a draft needs a manager's OK before it can be sent. This is a badge on the draft ("waiting for review"), **not** a client status, so it never mixes with the client's "Approved".

### Who gets notified (email + in-app)

| Event | Notified |
|---|---|
| Client **viewed** (first time) | Creator, assignee, watchers |
| Client **approved** / **rejected** | Creator, assignee, watchers, admins |
| Document **expires in 2 days** (no answer yet) | Creator + assignee ("follow up now") |
| Document **expired** | Creator + assignee |
| Sent but **not viewed after 3 days** | Creator ("the client hasn't opened it") |
| Review needed / review done | Reviewers / creator |
| @mention, new comment, assigned to you | That member |
| Payment recorded / invoice overdue | Creator + accountants |

Each member chooses in their settings: instant / daily digest / off, per event.

---

## 4. Sharing & tracking

### 4.1 Three ways to send
| Channel | How it works |
|---|---|
| **Email** | Sent by Wafiq from the company's address: message + **tracked link** + PDF attached (optional). Status → Sent. |
| **WhatsApp** | Wafiq opens WhatsApp (app or WhatsApp Web) with the client's number and a **ready Arabic message containing the tracked link** (`wa.me` click-to-chat: free, no WhatsApp API needed). The member presses send in WhatsApp. Status → Sent. |
| **Copy link** | For any other channel (SMS, Telegram, LinkedIn…). The member marks it as sent. |

**Every send creates its own tracked link** (one per recipient and channel), so the timeline shows *"Opened via WhatsApp by Ahmed — 3 times, last 10 minutes ago"*.

Example WhatsApp message (editable template in settings):
> مرحباً أحمد، نرسل لك عرض السعر رقم QT-2026-0042 من شركة الإتقان بقيمة 12,500 ر.س.
> يمكنك مراجعته والموافقة عليه من هنا: https://quotes.alitqan.sa/d/k7Qx…
> العرض صالح حتى 15 أكتوبر 2026.

### 4.2 What "Viewed" really means (avoid false alarms)
WhatsApp, Telegram, Slack and email security scanners (e.g. Outlook Safe Links) **open links automatically** to build previews or check for viruses. If we counted those, every document would show "Viewed" instantly.

Wafiq counts a view only when:
1. The page is opened in a real browser that **runs JavaScript**, and it sends a small "viewed" signal after the page has been **visible for 2+ seconds**. Preview bots don't run JavaScript.
2. The visitor is **not a logged-in team member** (opening your own link doesn't count).
3. Known crawler user agents are ignored (`WhatsApp/`, `facebookexternalhit`, `TelegramBot`, `Slackbot`, `Twitterbot`, `LinkedInBot`, `Googlebot`…).

The link preview card in WhatsApp still looks good: Open Graph tags show the company logo, "عرض سعر QT-2026-0042" and the amount, but **no private details**.

### 4.3 What the client sees (public page, no login)
- Company logo and colours, the document in a clean responsive layout (works on phones), and a **Download PDF** button.
- **Approve**: types their name, ticks "I agree to the terms", optionally draws a signature *(P2)* → confirmation screen. The approval (name, date, IP, device) is stamped on the PDF.
- **Reject**: optional reason (preset choices + free text) → the team is notified.
- **Ask a question** *(P2)*: a message that lands in the document's comments.
- If expired: "this offer expired on …, contact us". If replaced: link to the newest version.
- The page is `noindex`, and the link token is long and random, revocable and regenerable.

### 4.4 Tracking in the app
- **Timeline** per document: created → sent (channel, by whom) → viewed (how many times, when, which link) → approved / rejected / expired.
- **Lists** show status badges, "last viewed 2h ago", and "sent 4 days ago, not viewed" warnings.
- **Dashboard pipeline:** how many documents are Sent / Viewed / Approved / Rejected / Expired, approval rate, average time to approval, total value waiting for an answer.

---

## 5. Positioning and price

| | Value |
|---|---|
| Model | One-time purchase, one company per install, **unlimited users and documents** |
| **Personal licence** | **$39** (one company) |
| **Commercial licence** | **$99** (install for clients / use in a paid service) |
| Extra income | Installation service ($30–60), yearly updates & support (30–40% of licence), customisation |
| Picalica limits | Applications: personal $10–100, commercial $10–250 ✓ |

**Angle on Picalica:** existing scripts generate invoices inside sales or inventory systems. Wafiq is about the **conversation with the client**: tracked sharing on WhatsApp, view tracking, and online approval, for **teams**.

### ⚠️ Legal positioning (must appear in the listing and docs)
- Wafiq is a **quotation & commercial invoice tool with online approval**. It is **not** a certified e-invoicing solution for **ZATCA Phase 2** (Saudi) or the **ETA** (Egypt).
- Optional: the **ZATCA Phase 1 QR code** on simplified tax invoices, clearly labelled "Phase 1 only".
- The online approval is a **record of the client's acceptance** (name, time, IP), **not a certified e-signature**. The docs must say this plainly.

---

## 6. Editions & SaaS-ready architecture

We sell the **self-hosted edition on Picalica first**, then launch the **SaaS** on our domain **from the same codebase**. To make that switch cheap, the app is **multi-company inside, single-company for Picalica**, from the first line of code.

| | **Picalica edition** (v1) | **SaaS edition** (later) |
|---|---|---|
| Companies per install | **Exactly one**, created by the installer | Unlimited (each signup = a company) |
| Who hosts | The buyer | Us |
| Signup page | ❌ (team members are invited) | ✅ public signup → new company |
| Billing / plans | ❌ none | ✅ subscriptions (Stripe / Tap / Moyasar…), plan limits |
| Company URL | The buyer's domain | `{company}.{domain}` or `{domain}/{company}` |
| Super-admin panel | ❌ | ✅ (manage companies, plans, usage) |
| Everything else | Same features, same code | Same features, same code |

### How the code stays SaaS-ready
- **Every business table has `company_id`** (clients, items, documents, payments, comments, settings, sequences…), with an index.
- A `BelongsToCompany` model trait adds a **global scope** (`where company_id = current company`) and fills `company_id` automatically on create.
- A `CurrentCompany` service decides the company:
  - **Picalica edition:** always the single company (cached).
  - **SaaS edition:** from the subdomain / logged-in user's membership.
- **Users belong to companies through a `company_user` pivot** (with the role on the pivot), so in the SaaS one person can be in several companies.
- **Number sequences, settings, uploads and the public share links are per company** (uploads stored under `companies/{id}/…`).
- Config switch: `APP_EDITION=self_hosted | saas` (`config/edition.php`). SaaS-only code lives in **separate folders** (`app/Saas/…`, `resources/js/Pages/Saas/…`, `routes/saas.php`), loaded only when the edition is `saas`.
- **Tests run in both editions** (a CI matrix), and a test proves that company A can never see company B's data.

### The Picalica release must not contain the SaaS
- The release script **deletes the SaaS folders** (`app/Saas`, `routes/saas.php`, SaaS pages, billing packages) before zipping, so buyers never receive your SaaS billing or signup code.
- **Licence text** (on Picalica and in the package): *"Personal licence: one company. Commercial licence: installing it for your own clients. Not allowed: offering it as a public multi-company / subscription service (SaaS) or reselling the source code."* This protects your future SaaS from competing copies.

---
## 7. Roles & permissions

| Permission | Owner | Admin | Accountant | Sales | Viewer |
|---|:-:|:-:|:-:|:-:|:-:|
| Company settings, branding, templates, numbering, taxes | ✅ | ✅ | — | — | — |
| Invite / remove members, change roles | ✅ | ✅ | — | — | — |
| Create, edit, **send** quotations | ✅ | ✅ | ✅ | ✅ (own) | — |
| Internal review (above the amount limit) | ✅ | ✅ | — | — | — |
| Create, edit, send invoices | ✅ | ✅ | ✅ | ✅ (from own approved quotes) | — |
| Record payments, void invoices | ✅ | ✅ | ✅ | — | — |
| See all documents & tracking | ✅ | ✅ | ✅ | own + shared with them | ✅ |
| Reports & exports | ✅ | ✅ | ✅ | own | ✅ |
| Delete documents | ✅ | ✅ | drafts only | own drafts | — |

*One **Owner** (created by the installer). Ownership can be transferred.*

---

## 8. Other flows

### 7.1 Login with a magic link
1. Member enters their email → **"Check your inbox"** (the same message whether or not the email exists).
2. Email contains a **single-use link, valid 15 minutes**.
3. Click → logged in → "remember this device" for 30 days.
4. Fallbacks (buyers often misconfigure email):
   - **Password login** can be turned on in settings (off by default).
   - CLI rescue: `php artisan wafiq:login-link owner@company.com` prints a link.
   - The installer **sends a test email** and won't finish until it arrives, or until the owner sets a password.

### 7.2 Invite a teammate
Admin enters email + role → invitation email → teammate clicks → sets name → logged in. Invitations expire after 7 days and can be resent or revoked.

### 7.3 Collaboration on a document
- **Comments** on every quotation and invoice (internal, never visible to the client).
- **@mention** a member, who gets an email and an in-app notification linking to the comment.
- **Assign** a document to a member ("follow up with this client").
- **Watchers**: creator, assignee and mentioned members get the client events (viewed / approved / rejected); anyone can follow or unfollow.
- **Activity timeline** merges team actions and client events in one place.

---

## 9. Document rules (get these right from day one)

- **Numbering:** separate sequences per type (`QT-{YYYY}-{0001}`, `INV-{YYYY}-{0001}`), **reserved in a database transaction** (no duplicates), optional yearly reset. Revisions keep the number with a suffix: `QT-2026-0042-v2`.
- **Locked after sending:** changes create a revision (see section 3).
- **Money:** stored as **integer minor units** (no float errors); one currency per document; tax per line; a document discount is spread across lines before tax.
- **Snapshots:** client and company details are **copied into the document when it's sent**, so later edits never change sent or approved documents.
- **Totals are calculated on the server** (never trust the browser) and mirrored live in the browser with the same rules.
- **Validity:** quotations have a "valid until" date (default from settings, e.g. 15 days). Invoices have a due date, plus an optional "approve by" date.

---

## 10. PDF & print (Arabic is the hard part)

- Server PDFs (download + email attachment) use **TCPDF** (**LGPL-3.0**, legal to bundle in a paid script). It handles **RTL and Arabic letter shaping**, embeds an Arabic font (**IBM Plex Sans Arabic** or **Noto Naskh Arabic**, both under the SIL Open Font License), and works on shared hosting (pure PHP, no Chrome).
  - ❌ Not mPDF (GPL-2.0, risky to bundle commercially). ❌ Not headless Chrome (doesn't run on most shared hosting).
- **Browser print view** for the most precise output ("Print → Save as PDF").
- **3 templates** (Classic, Modern, Minimal), plus logo, colour, stamp and signature images, footer and bank details.
- **Amount in words in Arabic** (e.g. «فقط اثنا عشر ألفاً وخمسمئة ريال سعودي لا غير»): our own tested class, per currency.
- **Approval stamp** on approved PDFs: "Approved by Ahmed Ali on 3 Oct 2026 10:42 — IP 5.1.x.x".
- Optional **Hijri date**, optional **ZATCA Phase 1 QR** (labelled "Phase 1 only").

---

## 11. Emails (Arabic, RTL, branded)

| Email | To | When |
|---|---|---|
| Magic login link / invitation | Member | On request / invite |
| **Document sent** (message + tracked link + optional PDF) | Client | On "Send by email" |
| **Reminder: waiting for your approval** | Client | Optional, X days after sending without an answer |
| **Expiring soon** (2 days left) | Client (optional) + creator | Scheduler |
| **Viewed / approved / rejected / expired** | Creator, assignee, watchers | Client action / scheduler |
| **Approval confirmation** (copy of the approved PDF) | Client | After approval |
| Review needed / done, @mention, comment, assigned | Member | Collaboration |
| Payment recorded / overdue reminder | Team / client | Accountant action / scheduler |
| Daily digest (optional) | Member | Instead of many single emails |

Rules (lessons from Bazarly):
- All emails are **queued**. Shared hosting runs the queue from **one cron entry**, with a `sync` fallback.
- **A mail failure never breaks the user's action**: it's logged, and the send is recorded with a "failed, retry" option.
- Client emails use the company's name and reply-to address. The "from" address is set in settings and tested by the installer.

---

## 12. Security essentials

- **Magic links:** random 64-character token **stored hashed**, single use, 15-minute expiry, rate limited (per email and per IP), same response for unknown emails, older links invalidated, session regenerated.
- **Share links:** one per send, long random token (never the document id), revocable and regenerable, optional expiry, `noindex`, no private data in the link preview.
- **Client actions** (approve / reject) are rate limited, protected against double submission, and record name, time, IP and user agent. They're only allowed on the latest revision while it's not expired.
- **Authorization** via Laravel Policies on every action.
- **Uploads** (logo, stamp, signature, attachments): images re-encoded, files stored privately and streamed through the app.
- CSRF, throttling, secure headers, encrypted SMTP password in settings, audit log of sensitive actions (role changes, voids, deletions, link revocations).
- Backups: one-click **database + files export** for the owner.

---

## 13. Technical architecture

```
wafiq/
├─ app/
│  ├─ Actions/            # SendDocument, RecordView, ApproveDocument, RejectDocument, ReviseDocument,
│  │                      # ConvertQuoteToInvoice, RecordPayment, ExpireDocuments
│  ├─ Enums/              # DocumentStatus, PaymentStatus, Channel, Role, PaymentMethod
│  ├─ Http/Controllers/   # Quotes, Invoices, Clients, Items, Team, Settings, Public (client page), Tracking
│  ├─ Models/             # Company, User, Client, Item, Document, DocumentLine, DocumentSend, DocumentView,
│  │                      # Payment, Comment, Activity, Watcher
│  ├─ Notifications/      # one class per event, queued, mail + database
│  ├─ Policies/
│  ├─ Services/           # Money, TotalsCalculator, NumberSequence, ArabicAmountInWords, PdfRenderer,
│  │                      # ZatcaQr, WhatsAppLink, BotDetector
│  └─ Support/            # Notifier (safe sending), Settings
├─ resources/js/          # React (JSX)
│  ├─ Pages/              # Auth, Dashboard, Documents (Quotes/Invoices), Clients, Items, Team, Settings, Public
│  ├─ Components/         # UI kit (ported from Bazarly)
│  ├─ Components/Document # LineItemsEditor, TotalsBox, StatusBadge, SendDialog, TrackingTimeline, CommentThread
│  └─ lib/                # i18n (useT), money/format, dates (Gregorian + Hijri)
├─ lang/ar/               # ui.php, validation.php, auth.php, mail strings
└─ docs/                  # Arabic install guide, user guide, cron & mail guide
```

- **JavaScript, not TypeScript:** `.jsx`, ESLint + Prettier, JSDoc on shared helpers, **PropTypes** on reusable UI components.
- **Reused from Bazarly** (ported from TSX to JSX): RTL Tailwind theme and tokens, UI components, `useT()`, Inertia error pages, `Notifier`, rate-limit and security patterns, RTL mail theme, scheduler and queue setup, render-check and test helpers, **polling** (`usePoll`) for live status updates on the document page.
- **Packages:** `inertiajs/inertia-laravel`, `tecnickcom/tcpdf` (LGPL), `intervention/image` (MIT), `@inertiajs/react`, `react`, `lucide-react` (ISC), `@fontsource/ibm-plex-sans-arabic` (OFL). **No GPL code is bundled.**
- **Requirements for buyers:** PHP 8.2+, MySQL 5.7+ / MariaDB 10.3+, `gd`, `mbstring`, `intl`, HTTPS, one cron entry. Works on cPanel shared hosting.

### Database (main tables)

| Table | Key columns |
|---|---|
| `companies` | **one row in the Picalica edition, many in the SaaS** · name, legal_name, vat_number, cr_number, address, phone, email, logo, stamp, signature, currency, settings (single row) |
| `company_user` | company_id, user_id, **role** (the role lives here, so one person can join several companies in the SaaS) |
| `users` | name, email, avatar, password (nullable), notification_prefs (json), last_login_at |
| `login_tokens` / `invitations` | email, token_hash, expires_at, used_at / accepted_at, ip |
| `clients` | type, name, contact_name, email, **phone (for WhatsApp)**, vat_number, address, notes, owner_id |
| `items` / `tax_rates` | name, unit, price_minor, tax_rate_id / name, rate, is_default |
| `documents` | **type** (quote / invoice), number, revision, parent_id (previous revision), **status** (draft, sent, viewed, approved, rejected, expired), payment_status (invoices), client_id + client snapshot, currency, issue_date, **valid_until**, due_date, totals (minor), notes, terms, template, review_state, created_by, assigned_to, sent_at, **first_viewed_at, last_viewed_at, views_count**, approved_at / by_name / ip / ua, rejected_at / reason, expired_at, quote_id (on invoices) |
| `document_lines` | document_id, item_id, description, qty, unit, unit_price_minor, discount, tax_rate, line_total_minor, position |
| `document_sends` | document_id, **channel** (email / whatsapp / link), recipient (email or phone), **token_hash**, sent_by, sent_at, delivered_status (email), revoked_at, expires_at |
| `document_views` | document_id, send_id, viewed_at, ip, user_agent, device (mobile / desktop), duration_seconds |
| `payments` | document_id, amount_minor, method, paid_at, reference, note, recorded_by |
| `comments` / `activities` / `watchers` | polymorphic; activities include client events (viewed, approved…) with send_id |
| `number_sequences`, `attachments`, `notifications`, `jobs`, `settings` | as usual |

*Every business table below `users` also has **`company_id`** (indexed), filled and filtered automatically by the `BelongsToCompany` trait.*

*Quotations and invoices share one `documents` table with a `type` column, because they share the same workflow, tracking, sharing and PDF code.*

---

## 14. Step-by-step build plan (to-do)

> Each phase ends with **tests passing** and the app **working end to end**. The final scope depends on the checklist in section 16.

### Phase 0 — Project setup
- [x] New Laravel 12 project `wafiq` (separate repo, **not** inside `redstore`)
- [x] Install Inertia 2 + React 19 (JSX) + Tailwind 4 + Vite, ESLint + Prettier
- [x] Port the Arabic RTL theme, fonts and UI components from Bazarly (TSX → JSX, add PropTypes)
- [x] `lang/ar` files, `useT()` helper, Arabic validation messages
- [x] Inertia error pages, Toaster, layouts (App, Auth, Public client page, Print)
- [x] GitHub Actions (PHP tests + JS lint/build, **both editions**), SQLite test config, `TestCase` without Vite
- [x] **SaaS-ready foundation:** `companies` + `company_user` tables, `BelongsToCompany` trait (global scope + auto `company_id`), `CurrentCompany` service, `APP_EDITION` config, test that companies are isolated

### Phase 1 — Auth & team
- [ ] Owner created by an installer stub (full installer in Phase 10)
- [ ] Magic link login (hashed tokens, expiry, single use, throttle, same response for unknown emails)
- [ ] "Remember this device", optional password login, `wafiq:login-link` rescue command
- [ ] Invitations (invite, resend, revoke, accept), roles + Policies, team page
- [ ] Notifier + queued notifications + RTL mail theme + `lang/ar.json`
- [ ] Tests: login link lifecycle, throttling, no account discovery, invitations, permissions matrix

### Phase 2 — Company settings
- [ ] Company profile, branding (logo, colour, stamp, signature), bank details
- [ ] Tax rates (country presets + custom), currencies, number formats & sequences
- [ ] Default validity days, default terms & notes, **WhatsApp and email message templates** (with variables: client name, number, amount, link, valid-until)
- [ ] Email settings + **send test email**; reminder and expiry rules; internal review amount limit

### Phase 3 — Clients & items
- [ ] Clients CRUD (company / person, **WhatsApp phone with country code**), search, tags, notes, client page (documents, approval history, balance)
- [ ] Items catalog CRUD + quick search in the line editor
- [ ] CSV import / export

### Phase 4 — Documents (quotations & invoices)
- [ ] Money + TotalsCalculator (PHP) mirrored in `lib/money.js`, shared test cases
- [ ] Arabic amount-in-words service (ريال، درهم، جنيه، دينار، دولار، يورو)
- [ ] Line items editor (add / remove / reorder, item search, qty, unit, price, discount, tax, live totals)
- [ ] Create / edit / duplicate drafts; number reservation in a transaction
- [ ] Optional internal review (amount limit, approve / request changes)
- [ ] Revisions (v2, v3…) with the "replaced" behaviour on old links
- [ ] Convert approved quotation → invoice
- [ ] Lists with status filters, search, "not viewed after X days" filter

### Phase 5 — Sharing & tracking ⭐ (the core of Wafiq)
- [ ] `document_sends`: one tracked link per send (channel + recipient + hashed token)
- [ ] **Send dialog**: Email (message editor + PDF option) / WhatsApp (prefilled `wa.me` message, opens app or WhatsApp Web) / Copy link
- [ ] Public client page (responsive, branded, PDF download, expired / replaced states)
- [ ] **View tracking** with the JS "visible 2s" beacon, bot / crawler filter, team-member exclusion; `first_viewed_at`, `views_count`, per-send stats
- [ ] Open Graph preview (logo, title, amount; no private details)
- [ ] **Approve** (typed name + terms checkbox) / **Reject** (reason), with rate limit + double-submit protection; confirmation email to the client
- [ ] Status machine (Draft → Sent → Viewed → Approved / Rejected / Expired) in one tested class
- [ ] Scheduler: expire documents, "expiring in 2 days", "not viewed after 3 days", optional client reminders
- [ ] Live status on the document page (polling) + tracking timeline

### Phase 6 — Payments (invoices)
- [ ] Record payments (partial / full, method, reference), payment status, overdue detection + reminders
- [ ] Void invoice with reason

### Phase 7 — PDF & print
- [ ] TCPDF renderer with Arabic font + 3 templates, logo / stamp / signature, amount in words, bank details
- [ ] Approval stamp on approved PDFs; optional Hijri date; optional ZATCA Phase 1 QR
- [ ] Browser print view

### Phase 8 — Collaboration & notifications
- [ ] Comments with @mentions, assignment, watchers
- [ ] Activity timeline (team actions + client events)
- [ ] In-app notifications (bell + page) + per-member email preferences (instant / digest / off) + daily digest

### Phase 9 — Dashboard & reports
- [ ] **Pipeline** (Sent / Viewed / Approved / Rejected / Expired counts and values), approval rate, average time to approval, value waiting for an answer
- [ ] Needs attention: not viewed, viewed but no answer, expiring soon, overdue invoices
- [ ] Reports: by member, by client, by month; VAT summary; receivables aging; CSV / Excel export
- [ ] Full backup export (owner)

### Phase 10 — Buyer experience
- [ ] **Web installer** (requirements → database → company → owner → mail test → done)
- [ ] Demo seed data (Arabic clients, items, documents in every status, realistic tracking history)
- [ ] **Demo mode** (read-only + nightly reset) → deploy on `demo.{domain}`
- [ ] Release script: clean zip (no `.env`, `.git`, `node_modules`), built assets, vendor included, **SaaS folders removed** (`app/Saas`, `routes/saas.php`, SaaS pages), `APP_EDITION=self_hosted` forced
- [ ] Update path (migrations only) + `CHANGELOG.md`

### Phase 11 — Quality
- [ ] Feature tests for every flow (auth, permissions, totals, numbering, **status machine**, sending, **tracking & bot filter**, approve / reject, expiry, revisions, payments, emails)
- [ ] Render check of every page with real data (as in Bazarly)
- [ ] PDF check: Arabic shaping, numbers, long tables, page breaks
- [ ] Real-world test: send via WhatsApp and Gmail / Outlook, confirm that previews and scanners **don't** mark documents as Viewed
- [ ] Manual test on cPanel shared hosting + a real SMTP provider; security pass

### Phase 12 — Website, docs & Picalica listing
- [ ] **{domain}** landing page (Arabic), `/docs`, `support@{domain}`
- [ ] Arabic docs (install cPanel / VPS, cron & mail, first steps, sharing & tracking, roles, templates, FAQ)
- [ ] 3 short Arabic videos: install · create & send by WhatsApp · track and get approval
- [ ] Cover 1700×970, 8–12 screenshots (pipeline, send dialog, client page on phone, tracking timeline, PDF)
- [ ] Listing text with the **legal note** (not ZATCA Phase 2, approval ≠ certified e-signature), licence text **forbidding public SaaS / multi-company resale** (see section 6), third-party credits (TCPDF LGPL, fonts OFL, icons ISC)

### Phase 13 — Launch & after
- [ ] Submit to Picalica; fix review feedback
- [ ] Support from real questions → FAQ; plan v1.1 from buyer requests (P2 list)
- [ ] **SaaS edition**: public signup, company subdomains, plans & subscriptions, super-admin panel, usage limits (same codebase, `APP_EDITION=saas`)

---

## 15. Listing highlights (why people buy)

1. **Send by WhatsApp or email with a tracked link**: know the moment your client opens it.
2. **Online approval**: the client approves or rejects in one click; no more "did you see my quote?".
3. **Clear pipeline**: Draft → Sent → Viewed → Approved / Rejected / Expired, with automatic follow-up reminders.
4. **Made for Arabic**: RTL everywhere, correct Arabic PDFs, amount in words, Hijri date.
5. **For teams**: roles, internal review, comments, @mentions, assignments, email notifications, magic link login.
6. **One-time price, unlimited everything, your own server.**

---

## 16. Feature checklist — mark what you DON'T want in v1

> Hidden with `<!-- -->` = not in the MVP (kept for later versions). Items marked **(core)** are needed for the product to make sense.
>
> **MVP rule:** keep only what solves the core problem — *"I sent a quote: did the client see it, and did they say yes?"* — for a team.

### Editions (keep these: they make the SaaS switch cheap later)
- [ ] **(core)** SaaS-ready data model (`company_id` everywhere, company isolation tests)
- [ ] **(core)** `APP_EDITION` switch; Picalica release without SaaS code
- [ ] **(core)** Licence text forbidding public SaaS resale
- [ ] SaaS edition: signup, plans & billing, super-admin *(after Picalica launch)*

### Workflow & statuses
- [ ] **(core)** Statuses: Draft, Sent, Viewed, Approved, Rejected, Expired (quotations and invoices)
- [ ] **(core)** Automatic Expired status from the validity date
- [ ] **(core)** Extend validity + resend (Expired → Sent)
- [ ] **(core)** Revisions (v2, v3…); old links point to the latest version
- [ ] **(core)** Convert approved quotation → invoice
<!-- - [ ] Internal review before sending (above an amount limit) -->
<!-- - [ ] Separate payment status on invoices (unpaid / partial / paid / overdue) -->

### Sharing & tracking
- [ ] **(core)** Send by email (message + tracked link + optional PDF)
- [ ] **(core)** Send by WhatsApp (prefilled message with tracked link, no API)
- [ ] **(core)** Copy link (any channel)
- [ ] **(core)** One tracked link per send (know which channel and recipient opened it)
- [ ] **(core)** Accurate "Viewed" (ignores WhatsApp / email previews, bots and team members)
- [ ] **(core)** View count, first / last viewed time, device type
- [ ] **(core)** Public client page (phone-friendly, branded, PDF download)
- [ ] **(core)** Client approves (typed name + terms checkbox) / rejects (reason)
- [ ] Approval stamp on the PDF (name, date, IP)
<!-- - [ ] Approval confirmation email to the client -->
- [ ] Link preview card in WhatsApp (logo, title, amount)
<!-- - [ ] Revoke / regenerate a link, optional link expiry -->
<!-- - [ ] Client draws a signature *(P2)* -->
<!-- - [ ] Client asks a question from the page (goes into comments) *(P2)* -->
<!-- - [ ] Client sees all their documents in one link ("client portal") *(P2)* -->

<!-- ### Follow-ups & notifications
- [ ] **(core)** Team notified on viewed / approved / rejected / expired (email + in-app)
- [ ] "Not viewed after 3 days" alert to the creator
- [ ] "Expiring in 2 days" alert to the creator
- [ ] Automatic reminder email to the client (waiting for approval)
- [ ] Automatic reminder to the client before expiry
- [ ] **(core)** In-app notifications (bell + page)
- [ ] Per-member email preferences (instant / daily digest / off)
- [ ] Daily digest email
- [ ] Live status update on the document page (no refresh needed) -->

### Accounts & team
- [ ] **(core)** Magic link login (email, 15-minute single-use link)
- [ ] **(core)** Team invitations by email (resend, revoke, expiry)
- [ ] **(core)** Roles: Owner, Admin, Accountant, Sales, Viewer
<!-- - [ ] Optional password login (safety net if email breaks) -->
- [ ] CLI rescue command to generate a login link
<!-- - [ ] "Remember this device" for 30 days -->
<!-- - [ ] Logged-in devices list + "log out other devices" -->
<!-- - [ ] Transfer ownership; deactivate member (keep their documents) -->

### Collaboration
- [ ] **(core)** Comments on every document (internal)
- [ ] **(core)** @mentions with email + in-app notification
<!-- - [ ] Assign document to a member -->
<!-- - [ ] Watchers (follow / unfollow a document) -->
- [ ] **(core)** Activity timeline (team actions + client events)
<!-- - [ ] Field-level change history ("price changed from X to Y") -->

### Company settings
- [ ] **(core)** Company profile (name, VAT number, CR number, address, contacts)
- [ ] **(core)** Logo + primary colour
- [ ] Stamp and signature images on documents
<!-- - [ ] Multiple bank accounts shown on invoices -->
- [ ] **(core)** Tax rates (country presets + custom)
- [ ] **(core)** Multiple currencies (one per document)
- [ ] **(core)** Number formats & sequences (yearly reset)
- [ ] **(core)** Editable WhatsApp & email message templates
- [ ] Default validity days, terms & notes per document type
<!-- - [ ] Email settings with "send test email" -->
<!-- - [ ] Multiple brands / letterheads in one install *(P2)* -->

### Clients & items
- [ ] **(core)** Clients (company / person) with WhatsApp phone and email
<!-- - [ ] Client page: documents, approval history, balance -->
<!-- - [ ] Client tags & internal notes -->
- [ ] **(core)** Items catalog (product / service, unit, price, tax)
<!-- - [ ] CSV import / export (clients, items) -->

### Document content
- [ ] **(core)** Line items editor with live totals, line discount, per-line tax
- [ ] Document-level discount
<!-- - [ ] Section headings inside the items table -->
<!-- - [ ] Attachments (specs, drawings) -->
<!-- - [ ] Optional items the client can tick / untick *(P2)* -->
<!-- - [ ] Deposit / advance invoice (e.g. 50% upfront) *(P2)* -->
<!-- - [ ] Credit notes *(P2)* -->
<!-- - [ ] Recurring invoices *(P2)* -->

<!-- ### Payments (invoices)
- [ ] **(core)** Record payments (partial / full, method, reference)
- [ ] Overdue detection + reminder emails
- [ ] Void invoice with reason
- [ ] Online payment link on invoices (Stripe / PayPal / Tap / Moyasar; code exists in Bazarly) *(P2)* -->

### PDF & print
- [ ] **(core)** Arabic PDF (TCPDF, correct shaping, embedded font)
<!-- - [ ] 3 templates (Classic, Modern, Minimal) -->
- [ ] **(core)** Amount in words in Arabic (التفقيط)
<!-- - [ ] Hijri date option -->
<!-- - [ ] ZATCA Phase 1 QR code (labelled "Phase 1 only") -->
<!-- - [ ] Browser print view -->

### Dashboard & reports
- [ ] **(core)** Pipeline: counts and values per status
- [ ] **(core)** "Needs attention" list (not viewed, no answer, expiring, overdue)
- [ ] Approval rate + average time to approval
<!-- - [ ] Performance by member (sent, viewed, approved, value) -->
<!-- - [ ] Sales by month / client; VAT summary; receivables aging -->
<!-- - [ ] CSV / Excel export; full backup export -->

### Buyer experience (needed for Picalica)
- [ ] **(core)** Web installer with mail test
- [ ] **(core)** Arabic documentation + videos (on {domain}/docs)
- [ ] **(core)** Demo seed data + live demo on demo.{domain}
<!-- - [ ] Demo mode (read-only + nightly reset) -->
<!-- - [ ] Update guide + changelog on {domain}/updates -->
<!-- - [ ] Dark mode *(P2)* -->
<!-- - [ ] English interface *(P2)* -->
