# 📖 HireIQ — Complete Project Documentation

> **The definitive reference for understanding, running, and maintaining the HireIQ AI Interview Assessment Platform.**  
> Read this file if you want to understand every part of this project — how it works, how it's structured, and how to run it.

---

## 🟢 YES — You Need XAMPP to Run This Project

**HireIQ is a PHP + MySQL application. It MUST run on a local web server.**

Without XAMPP running, the site will not work at all. Here's what you need:

| XAMPP Service | Why It's Needed |
|--------------|----------------|
| **Apache** | Serves all PHP pages. Without it, `.php` files just download instead of running. |
| **MySQL** | Stores all data — users, interviews, questions, results, scores. |

### How to Start the Project Every Time

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**
4. Open your browser and go to: **http://localhost/ai_interview_platform/**

> **Tip:** If port 80 is blocked by something else (Skype, IIS), change Apache to port 8080 in XAMPP → Apache → Config → httpd.conf, then use `http://localhost:8080/ai_interview_platform/`

---

## 🗂️ Full Project Structure

```
C:\xampp\htdocs\ai_interview_platform\
│
├── .env                          ← API keys (NEVER share or commit this)
├── .htaccess                     ← Apache security rules, upload limits, Permissions-Policy
├── index.php                     ← Entry point (redirects to login/dashboard)
├── login.php                     ← Login page
├── register.php                  ← Candidate self-registration
├── logout.php                    ← Session destroy + redirect
├── forgot-password.php           ← Token-based password reset
│
├── config/
│   ├── app.php                   ← App constants, .env loader, error settings, autoload
│   └── database.php              ← PDO singleton (getDB() function)
│
├── includes/
│   ├── functions.php             ← Utility helpers (sanitize, redirect, formatDate, token)
│   ├── auth.php                  ← Login/logout/session/role helpers
│   ├── sessions.php              ← Tab-switch detection + screen lock logic
│   ├── layout.php                ← Generic HTML shell (head, nav, footer)
│   ├── admin_layout.php          ← Admin sidebar layout template
│   ├── candidate_layout.php      ← Candidate sidebar layout template
│   ├── ai_services.php           ← Groq Whisper + Gemini evaluation functions
│   ├── questions.php             ← Question CRUD helpers
│   ├── interviews.php            ← Interview fetch/status helpers
│   ├── tests.php                 ← Test fetch helpers
│   ├── notifications.php         ← Notification create/read helpers
│   ├── documents.php             ← Document upload + read tracking
│   └── candidates.php            ← Candidate listing + stats queries
│
├── admin/
│   ├── dashboard.php             ← Admin home: stats, recent activity
│   ├── candidates.php            ← View all candidates, search/filter
│   ├── candidate_view.php        ← Single candidate profile + interview history
│   ├── questions.php             ← Question bank (General + Interview-Specific tabs)
│   ├── question_edit.php         ← Edit a single question
│   ├── interviews.php            ← List/manage interviews
│   ├── interview_builder.php     ← Build interview: add/order questions inline
│   ├── interview_assign.php      ← Invite candidates to interviews (Select All)
│   ├── tests.php                 ← List/manage tests
│   ├── test_builder.php          ← Build test: add questions + set marks
│   ├── test_assign.php           ← Invite candidates to tests (Select All)
│   ├── attempt_review.php        ← Review answers, AI scores, publish verdict
│   └── documents.php             ← Upload documents for candidates
│
├── candidate/
│   ├── dashboard.php             ← Candidate home: pending invitations, recent results
│   ├── invitations.php           ← Accept/Decline interview + test invitations
│   ├── interview_engine.php      ← Live interview: record audio per question
│   ├── test_engine.php           ← Live test: answer questions with timer
│   ├── results.php               ← View published interview verdicts + admin notes
│   ├── notifications.php         ← Notification feed
│   └── documents.php             ← View/download admin-shared documents
│
├── api/
│   └── save_answer.php           ← AJAX endpoint: receive audio → save → trigger AI
│
├── database/
│   ├── schema.sql                ← Phase 1: users + password_resets tables
│   ├── migration_phase2.sql      ← Phase 2: interviews + interview_sessions
│   ├── migration_phase3.sql      ← Phase 3: questions, tests, invitations, notifications, docs
│   ├── migration_phase4.sql      ← Phase 4: audio_path, response_time, attempt links
│   ├── migration_phase5.sql      ← Phase 5: transcripts, ai_evaluations, evaluation_jobs
│   └── migration_phase6.sql      ← Phase 6: is_interview_specific, interview_results
│
├── assets/
│   ├── css/                      ← Custom stylesheets
│   ├── js/                       ← Custom JavaScript
│   └── images/                   ← Static images/icons
│
└── uploads/
    ├── audio/                    ← Candidate recorded audio files (webm format)
    ├── documents/                ← Admin-uploaded documents
    └── videos/                   ← Optional video uploads
```

---

## 🗄️ Database Architecture

### Database Name: `ai_interview_platform`

| Table | Purpose |
|-------|---------|
| `users` | All platform users — both admins and candidates |
| `password_resets` | Token-based password reset tokens with expiry |
| `interviews` | Interview definitions (title, duration, difficulty, status) |
| `interview_sessions` | Links a candidate to an interview, tracks completion |
| `questions` | Master question bank (general + interview-specific) |
| `interview_questions` | Questions assigned to a specific interview (with order) |
| `tests` | Test definitions (like interviews but MCQ/text-based) |
| `test_questions` | Questions inside a test with marks |
| `interview_invitations` | Admin → Candidate invitations for interviews |
| `test_invitations` | Admin → Candidate invitations for tests |
| `notifications` | In-app notifications per user |
| `documents` | Files uploaded by admin for candidates to read |
| `document_reads` | Tracks which candidate read which document |
| `attempts` | A candidate's single attempt at an interview or test |
| `answers` | One row per question per attempt (audio_path + response_time) |
| `transcripts` | Groq Whisper output for each answer |
| `ai_evaluations` | Gemini scores + feedback for each answer |
| `evaluation_jobs` | Status tracker for each AI evaluation job |
| `interview_results` | Admin's final verdict (Selected/Not Selected/On Hold) + published status |

### Key Relationships

```
users ──< interview_invitations >── interviews
users ──< interview_sessions >── interviews
interview_sessions ──< attempts
attempts ──< answers
answers ──< transcripts
answers ──< ai_evaluations
answers ──< evaluation_jobs
interviews ──< interview_questions >── questions
interviews ──< interview_results
```

---

## 🔐 User Roles & Access

| Role | Access | Login |
|------|--------|-------|
| `super_admin` | Full admin panel, all features | admin@aiplatform.com / password |
| `candidate` | Dashboard, invitations, interviews, tests, results | Self-registered |

- Roles are enforced by `requireAdmin()` and `requireLogin()` in every page header
- Sessions are secured with `httponly`, `samesite=Strict`, and `strict_mode`

---

## 🤖 AI Pipeline — How It Works

### Step 1: Candidate Records Audio
- Browser uses **MediaRecorder API** (`getUserMedia`)
- Audio is captured as `audio/webm` chunks
- On "Stop Recording" → blob assembled → sent via **AJAX FormData** to `api/save_answer.php`

### Step 2: Audio Saved
- `save_answer.php` receives the audio blob
- Saves it to `uploads/audio/answer_{id}_{timestamp}.webm`
- Creates/updates the `answers` row with `audio_path`

### Step 3: Groq Whisper Transcription
```
Function: groqTranscribe(string $audioPath): ?array
API:      https://api.groq.com/openai/v1/audio/transcriptions
Model:    whisper-large-v3-turbo
Returns:  ['text' => '...', 'language' => 'en']
```
- Builds raw `multipart/form-data` body (no cURL file upload)
- 120 second timeout (audio can be long)
- Max file size: 25 MB
- Result stored in `transcripts` table

### Step 4: Gemini Evaluation
```
Function: geminiEvaluate(question, transcript, difficulty): ?array
API:      https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent
Returns:  { overall_score, technical_score, communication_score, strengths, weaknesses, summary }
```
- Prompt acts as "expert technical interviewer"
- `maxOutputTokens: 8192` — **must be high** because gemini-2.5-flash uses reasoning tokens
- `temperature: 0.3` — deterministic, consistent scoring
- Result stored in `ai_evaluations` table

### Step 5: Admin Reviews
- `admin/attempt_review.php` shows all questions, audio players, transcripts, and AI scores
- Admin sets: **Selected / Not Selected / On Hold** + writes conclusion notes
- Admin clicks **Publish Result** → sets `is_published = 1`

### Step 6: Candidate Sees Result
- `candidate/results.php` shows only published results
- Verdict badge + admin notes are displayed

---

## 🛡️ Security Features

| Feature | Implementation |
|---------|---------------|
| Password hashing | `password_hash()` with bcrypt cost-12 |
| SQL injection prevention | PDO prepared statements everywhere |
| XSS prevention | `htmlspecialchars()` on all output |
| Session security | httponly + samesite=Strict + strict_mode |
| Directory protection | `.htaccess` blocks direct access to config/, includes/, database/ |
| Sensitive file blocking | `.sql`, `.env`, `.log`, `.json` files blocked by Apache |
| Camera/mic policy | `Permissions-Policy: camera=(self), microphone=(self)` in .htaccess |
| Upload validation | File size + MIME type checks before saving |
| Role enforcement | `requireAdmin()` / `requireLogin()` on every protected page |

---

## 🚫 Tab-Switch & Screen Lock (Anti-Cheating)

Located in: `includes/sessions.php`

**How it works:**
1. When candidate starts an interview/test, a JavaScript listener is attached
2. The **Visibility API** (`document.visibilitychange`) detects tab switches
3. The **blur event** on `window` detects focus loss (alt-tab, minimise, etc.)
4. **1st violation** → Warning modal appears: "You switched tabs. This has been recorded."
5. **2nd violation** → Interview/test is auto-submitted immediately
6. Right-click, F12, Ctrl+U, Ctrl+Shift+I are disabled during the session
7. Copy/paste disabled on question text

---

## 📋 Interview-Specific Questions

A special feature that keeps certain questions private:

- In `admin/interview_builder.php`, admin can create questions inline (not from the general bank)
- These questions are flagged `is_interview_specific = 1` in the `questions` table
- They appear under a **separate "Interview Questions" tab** in `admin/questions.php`
- They are **NEVER shown to candidates** until the interview begins
- The general question bank tab only shows `is_interview_specific = 0` questions
- Candidates cannot browse or preview interview-specific questions at any time

---

## 🎯 Interview Flow (From Candidate's View)

```
1. Candidate receives email/notification invitation
2. Candidate logs in → Invitations page
3. Candidate clicks "Accept" on an interview invitation
4. Candidate clicks "Start Interview" (only available after accepting)
5. Browser requests camera/microphone permission
6. First question appears with a countdown timer
7. Candidate clicks "Start Recording" → speaks their answer
8. Candidate clicks "Stop Recording" → audio sent to server via AJAX
9. System shows "Processing..." while Groq + Gemini run
10. Candidate advances to next question automatically
11. After all questions → "Interview Complete" screen
12. Admin reviews attempt in admin/attempt_review.php
13. Admin sets verdict + publishes
14. Candidate sees result in candidate/results.php
```

---

## 🧪 Test Flow (From Candidate's View)

```
1. Admin creates test in test_builder.php (text questions with marks)
2. Admin invites candidates via test_assign.php
3. Candidate accepts invitation
4. Candidate starts test — countdown timer begins for entire test
5. Candidate answers all questions (text input)
6. Tab-switch detection active throughout
7. Candidate submits or time expires (auto-submit)
8. Admin reviews in attempt_review.php
```

---

## ⚙️ Configuration Reference

### `.env` File (Root of Project)
```env
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxx
GEMINI_API_KEY=AQ.xxxxxxxxxxxxxxxxxxxxxxxx
```

### `config/database.php` — Database Settings
```php
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'ai_interview_platform');
define('DB_USER',    'root');
define('DB_PASS',    '');     // empty for XAMPP default
define('DB_CHARSET', 'utf8mb4');
```

### `config/app.php` — App Settings
```php
define('APP_ENV',  'development');   // change to 'production' for live
define('APP_NAME', 'HireIQ');
define('BASE_URL', 'http://localhost/ai_interview_platform');
```

### `.htaccess` — Upload Limits
```
php_value upload_max_filesize 20M
php_value post_max_size 22M
php_value max_execution_time 60
```

---

## 🧱 PHP Extensions Required

Verify these are enabled in `C:\xampp\php\php.ini`:

- `extension=pdo_mysql` — database connection
- `extension=curl` — Groq + Gemini API calls
- `extension=fileinfo` — MIME type validation on uploads
- `extension=mbstring` — multi-byte string handling
- `extension=openssl` — HTTPS/token generation

---

## 🔧 XAMPP Apache Modules Required

In `C:\xampp\apache\conf\httpd.conf`, ensure these are NOT commented out:

```
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
```

---

## 🚦 Default Admin Login

| Field | Value |
|-------|-------|
| Email | `admin@aiplatform.com` |
| Password | `password` |

> ⚠️ **Change this password immediately after first login!**

---

## 📂 Uploads Directory — Permissions

These folders must be **writable by Apache**. On Windows/XAMPP this is usually automatic.  
If uploads fail, right-click the folder → Properties → Security → give SYSTEM/IIS_IUSRS write access.

```
uploads/audio/        ← candidate audio recordings (.webm)
uploads/documents/    ← admin-uploaded PDFs/documents
uploads/videos/       ← optional video uploads
```

---

## 🐛 Common Problems & Fixes

| Problem | Cause | Fix |
|---------|-------|-----|
| "Page Not Found" on any URL | XAMPP Apache not running | Start Apache in XAMPP Control Panel |
| "Database connection failed" | MySQL not running OR wrong credentials | Start MySQL; check `config/database.php` |
| "Camera not allowed" in interview | Permissions-Policy header missing | Check `.htaccess` has `Permissions-Policy: camera=(self), microphone=(self)` |
| AI evaluation not working | `.env` keys empty or wrong | Paste correct API keys into `.env` |
| Audio upload fails (>20MB) | File too large | Shorten recording; max is 20MB per file |
| Gemini returns empty/truncated JSON | `maxOutputTokens` too low | Ensure `maxOutputTokens: 8192` in `ai_services.php` |
| "Access denied" on admin pages | Not logged in as admin | Login with admin@aiplatform.com |
| Blank page / 500 error | PHP error (dev mode off) | Set `APP_ENV = development` in `config/app.php` to see errors |
| Session lost on page refresh | Session not started | Every page must include `config/app.php` before any output |
| Results not showing to candidate | `is_published = 0` | Admin must click "Publish Result" in attempt_review.php |

---

## 📊 AI Scoring Breakdown

Each candidate answer receives three AI scores (0–100):

| Score | What It Measures |
|-------|-----------------|
| `technical_score` | Accuracy, depth, correctness of technical content |
| `communication_score` | Clarity, structure, fluency of spoken answer |
| `overall_score` | Weighted average of the above two |

Plus qualitative feedback:
- **Strengths**: 2–3 sentences on what was good
- **Weaknesses**: 2–3 sentences on what to improve
- **Summary**: 1–2 sentence overall verdict

---

## 🗺️ URL Map

### Admin URLs
| URL | Page |
|-----|------|
| `/admin/dashboard.php` | Admin home |
| `/admin/candidates.php` | All candidates list |
| `/admin/candidate_view.php?id=X` | Single candidate profile |
| `/admin/questions.php` | Question bank |
| `/admin/interviews.php` | Interviews list |
| `/admin/interview_builder.php?id=X` | Build/edit an interview |
| `/admin/interview_assign.php?id=X` | Assign candidates to interview |
| `/admin/tests.php` | Tests list |
| `/admin/test_builder.php?id=X` | Build/edit a test |
| `/admin/test_assign.php?id=X` | Assign candidates to test |
| `/admin/attempt_review.php?id=X` | Review + publish a candidate's attempt |
| `/admin/documents.php` | Manage documents |

### Candidate URLs
| URL | Page |
|-----|------|
| `/candidate/dashboard.php` | Candidate home |
| `/candidate/invitations.php` | Pending invitations |
| `/candidate/interview_engine.php?id=X` | Live interview session |
| `/candidate/test_engine.php?id=X` | Live test session |
| `/candidate/results.php` | Published results |
| `/candidate/notifications.php` | Notifications |
| `/candidate/documents.php` | Documents |

### Auth URLs
| URL | Page |
|-----|------|
| `/login.php` | Login |
| `/register.php` | Candidate register |
| `/forgot-password.php` | Password reset |
| `/logout.php` | Logout |

---

## 📦 How to Back Up This Project

### Back Up the Code
Copy the entire folder:
```
C:\xampp\htdocs\ai_interview_platform\
```
to a USB drive or cloud storage. **Include the `.env` file.**

### Back Up the Database
1. Open phpMyAdmin: http://localhost/phpmyadmin/
2. Click on `ai_interview_platform` database (left sidebar)
3. Click **Export** tab → Quick → Format: SQL → **Go**
4. Save the `.sql` file alongside your code backup

### Restore from Backup
1. Place the code folder back into `C:\xampp\htdocs\`
2. Open phpMyAdmin → **Import** the `.sql` backup file
3. Restore the `.env` file with your API keys
4. Start XAMPP Apache + MySQL and visit the URL

---

## 📡 External APIs Used

### Groq (Whisper Transcription)
- **Endpoint:** `https://api.groq.com/openai/v1/audio/transcriptions`
- **Model:** `whisper-large-v3-turbo`
- **Get your key:** https://console.groq.com/ → API Keys → Create Key
- **Free tier:** Generous free tier, no credit card needed
- **Rate limits:** ~100 req/min on free

### Google Gemini (Evaluation)
- **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent`
- **Model:** `gemini-2.5-flash`
- **Get your key:** https://aistudio.google.com/app/apikey → Create API Key
- **Free tier:** 15 req/min, 1M tokens/min on free tier
- **Note:** gemini-2.5-flash is a **reasoning model** — it uses extra tokens internally, which is why `maxOutputTokens` must be 8192+

---

## 📝 Project History (Phases)

| Phase | What Was Built |
|-------|---------------|
| **Phase 1** | Authentication system (login, register, forgot-password, roles) |
| **Phase 2** | Admin dashboard, candidate management, interview management |
| **Phase 3** | Question bank, test management, invitations, notifications, documents |
| **Phase 4** | Interview engine (MediaRecorder audio), test engine (timer + tab-lock), AJAX submission |
| **Phase 5** | AI pipeline: Groq Whisper transcription → Gemini evaluation → scores stored in DB |
| **Phase 6** | Admin verdict (Selected/Not Selected/On Hold), result publishing, candidate results page, interview-specific questions |
