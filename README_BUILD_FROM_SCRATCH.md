# 🏗️ HireIQ — Build From Scratch Checklist

> **Purpose:** This file tells you exactly how to recreate the entire HireIQ AI Interview Assessment Platform from zero.  
> Every step is numbered and checkable. Work top-to-bottom.

---

## 🧰 PREREQUISITES — Install These First

- [ ] **XAMPP** (PHP 8.1+ / Apache / MySQL) → https://www.apachefriends.org/
- [ ] **Node.js 18+** → https://nodejs.org/
- [ ] **A Groq account** (for Whisper transcription) → https://console.groq.com/
- [ ] **A Google AI Studio account** (for Gemini) → https://aistudio.google.com/
- [ ] A modern browser (Chrome or Firefox)

---

## PHASE 1 — Project Skeleton & Authentication

### 1.1 Folder Structure
Create the following folder at `C:\xampp\htdocs\ai_interview_platform\`:

```
ai_interview_platform/
├── admin/
├── api/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── candidate/
├── config/
├── database/
├── includes/
└── uploads/
    ├── audio/
    ├── documents/
    └── videos/
```

### 1.2 Config Files

- [ ] Create `config/database.php`
  - Define: `DB_HOST=localhost`, `DB_PORT=3306`, `DB_NAME=ai_interview_platform`, `DB_USER=root`, `DB_PASS=`
  - Create a `getDB(): PDO` singleton function
  - Use `PDO::ERRMODE_EXCEPTION`, `PDO::FETCH_ASSOC`, and real prepared statements

- [ ] Create `config/app.php`
  - Define: `APP_ENV`, `APP_NAME=HireIQ`, `APP_VERSION`, `BASE_URL`
  - Add session security settings (`cookie_httponly`, `use_strict_mode`, `cookie_samesite`)
  - Add `.env` file loader (reads `KEY=VALUE` lines → PHP `define()`)
  - Fallback: `define('GROQ_API_KEY', '')` and `define('GEMINI_API_KEY', '')`
  - `require_once` database.php, functions.php, auth.php at the bottom

- [ ] Create `.env` in root
  ```
  GROQ_API_KEY=your_groq_key_here
  GEMINI_API_KEY=your_gemini_key_here
  ```

- [ ] Create `.htaccess` in root
  - `Options -Indexes` (disable directory browsing)
  - Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`
  - **Important:** `Permissions-Policy: camera=(self), microphone=(self)` — required for audio recording
  - Block direct access to `config/`, `includes/`, `database/` with RewriteRules
  - Block `.sql`, `.log`, `.env`, `.json`, `.md` file access
  - PHP settings: `upload_max_filesize 20M`, `post_max_size 22M`, `max_execution_time 60`

### 1.3 Core Includes

- [ ] Create `includes/functions.php`
  - Utility helpers: `sanitize()`, `redirect()`, `formatDate()`, `generateToken()`

- [ ] Create `includes/auth.php`
  - `loginUser(email, password)` using bcrypt + `password_verify()`
  - `logoutUser()` session destroy
  - `isLoggedIn()` and `isAdmin()` checks
  - `requireLogin()` and `requireAdmin()` guards

- [ ] Create `includes/sessions.php`
  - Tab-switch detection logic (visibility API + blur/focus events)
  - Screen lock enforcement during active interview/test
  - Warning modal after 1st tab switch, auto-submit after 2nd (configurable)

- [ ] Create `includes/layout.php` — shared HTML shell (head, nav, footer)
- [ ] Create `includes/admin_layout.php` — admin sidebar layout
- [ ] Create `includes/candidate_layout.php` — candidate sidebar layout

### 1.4 Database — Phase 1

- [ ] Create `database/schema.sql` and run it in phpMyAdmin:
  ```sql
  CREATE DATABASE IF NOT EXISTS ai_interview_platform CHARACTER SET utf8mb4;
  USE ai_interview_platform;

  -- users table
  CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(150) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','candidate') NOT NULL DEFAULT 'candidate',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB;

  -- password_resets table
  CREATE TABLE password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB;

  -- Seed default admin (password: password)
  INSERT INTO users (full_name, email, password_hash, role) VALUES
  ('Super Administrator', 'admin@aiplatform.com',
   '$2y$12$OZXKAjAJsnqOGJSPvAOQN.m8uh2oC904uemhl2ukFGp5WGrVdTD.C', 'super_admin');
  ```

### 1.5 Auth Pages

- [ ] Create `login.php` — email + password form, session flash messages, Bootstrap 5 UI
- [ ] Create `register.php` — candidate self-registration form with bcrypt hashing
- [ ] Create `logout.php` — session destroy + redirect to login
- [ ] Create `forgot-password.php` — token-based password reset form
- [ ] Create `index.php` — redirect to login or dashboard based on role

---

## PHASE 2 — Admin Dashboard & Candidate Management

### 2.1 Database — Phase 2

- [ ] Create and run `database/migration_phase2.sql`:
  - `ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL`
  - Create `interviews` table (id, title, description, duration, difficulty, status, created_by)
  - Create `interview_sessions` table (id, interview_id, candidate_id, status, score, started_at, completed_at)

### 2.2 Admin Pages

- [ ] Create `admin/dashboard.php`
  - Stats cards: total candidates, active interviews, completed sessions, pending reviews
  - Recent activity table
  - Quick-action buttons

- [ ] Create `admin/candidates.php`
  - Table of all candidates (role = 'candidate')
  - Columns: name, email, status, last login, actions
  - Search/filter functionality

- [ ] Create `admin/candidate_view.php`
  - Detailed profile of a single candidate
  - Their interview history, scores, documents

---

## PHASE 3 — Question Bank, Tests, Notifications, Documents

### 3.1 Database — Phase 3

- [ ] Create and run `database/migration_phase3.sql`:
  - `questions` table (id, question_text, expected_topics, difficulty, category, created_by, is_interview_specific)
  - `interview_questions` table (id, interview_id, question_id, difficulty, sequence_order)
  - `tests` table (id, title, description, duration, status, created_by)
  - `test_questions` table (id, test_id, question_text, difficulty, category, marks)
  - `interview_invitations` table (id, interview_id, candidate_id, invited_by, status)
  - `test_invitations` table (id, test_id, candidate_id, invited_by, status)
  - `notifications` table (id, user_id, title, message, type, is_read)
  - `documents` table (id, title, description, file_path, uploaded_by)
  - `document_reads` table (document_id, candidate_id)
  - `attempts` table (id, session_id, test_id, started_at, completed_at, score, interview_id, candidate_id)
  - `answers` table (id, attempt_id, question_id, test_question_id, answer_text, audio_path, response_time)
  - `transcripts` table (id, answer_id, transcript_text, language)
  - `ai_evaluations` table (id, answer_id, overall_score, technical_score, communication_score, strengths, weaknesses, summary, model_used)

### 3.2 Includes / Helpers

- [ ] Create `includes/questions.php` — CRUD helpers for questions (filter out interview-specific by default)
- [ ] Create `includes/interviews.php` — interview fetch/status helpers
- [ ] Create `includes/tests.php` — test fetch helpers
- [ ] Create `includes/notifications.php` — create/mark-read notification helpers
- [ ] Create `includes/documents.php` — document upload + read-tracking helpers
- [ ] Create `includes/candidates.php` — candidate listing + stats helpers

### 3.3 Admin Pages

- [ ] Create `admin/questions.php`
  - Two tabs: **General Question Bank** | **Interview-Specific Questions**
  - CRUD: add, edit, delete questions
  - Interview-specific questions are hidden from candidates and general bank

- [ ] Create `admin/question_edit.php` — edit a single question

- [ ] Create `admin/interviews.php`
  - List all interviews with status badges
  - Create / archive / activate interviews

- [ ] Create `admin/interview_builder.php`
  - Add questions from general bank OR create new inline questions (saved as interview-specific)
  - Drag-and-drop or numbered sequence ordering
  - Set duration, difficulty per question

- [ ] Create `admin/interview_assign.php`
  - Invite candidates to an interview
  - **"Select All Candidates"** checkbox at bottom of list
  - Sends notification on assignment

- [ ] Create `admin/tests.php` — list/manage tests
- [ ] Create `admin/test_builder.php` — add questions to a test, set marks
- [ ] Create `admin/test_assign.php` — invite candidates to tests (with Select All)
- [ ] Create `admin/documents.php` — upload documents for candidates to review

### 3.4 Candidate Pages

- [ ] Create `candidate/dashboard.php`
  - Welcome card, pending invitations count, recent results card
  - Quick links to invitations, interview engine, results

- [ ] Create `candidate/invitations.php` — Accept / Decline interview and test invitations
- [ ] Create `candidate/notifications.php` — notification feed with mark-all-read
- [ ] Create `candidate/documents.php` — view/download documents shared by admin

---

## PHASE 4 — Interview & Test Engines (Recording + Submission)

### 4.1 Database — Phase 4

- [ ] Create and run `database/migration_phase4.sql`:
  - Add `audio_path` column to `answers`
  - Add `response_time` (seconds) column to `answers`
  - Add `interview_id` and `candidate_id` to `attempts`
  - Add `video_path` to `answers` (optional)

### 4.2 Interview Engine (`candidate/interview_engine.php`)

- [ ] Load interview questions from DB for the candidate's session
- [ ] Display one question at a time with a timer
- [ ] **MediaRecorder API** — record audio from microphone
  - Request `getUserMedia({ audio: true })`
  - Record as `audio/webm` chunks
  - On "Stop Recording" → convert blob → FormData → POST to API
- [ ] Submit audio via AJAX to `api/save_answer.php`
- [ ] Tab-switch detection (from `includes/sessions.php`) — warn → auto-submit
- [ ] Screen lock: disable right-click, F12, copy, inspect during interview
- [ ] Auto-advance to next question or show completion screen

### 4.3 Test Engine (`candidate/test_engine.php`)

- [ ] Similar to interview engine but for MCQ/text tests
- [ ] Countdown timer for entire test
- [ ] Tab-switch detection enabled
- [ ] Submit all answers at once on completion or timeout

### 4.4 API Endpoints (`api/`)

- [ ] Create `api/save_answer.php`
  - Accept `multipart/form-data` with audio blob + attempt_id + question_id
  - Save audio file to `uploads/audio/` with unique filename
  - Insert/update `answers` row with `audio_path`
  - Trigger Phase 5 evaluation pipeline (call `groqTranscribe()` + `geminiEvaluate()`)
  - Return JSON `{ success: true, answer_id: X }`

---

## PHASE 5 — AI Evaluation Engine

### 5.1 Database — Phase 5

- [ ] Create and run `database/migration_phase5.sql`:
  - Update `transcripts` table: add `language` column, rename `transcript` → `transcript_text`
  - Update `ai_evaluations` table: add `technical_score`, `communication_score`, `strengths`, `weaknesses`, `summary`, `model_used` columns, remove old `score`/`feedback` columns
  - Create `evaluation_jobs` table (answer_id, status ENUM pending/transcribing/evaluating/completed/failed, error_msg, started_at, completed_at)

### 5.2 AI Services (`includes/ai_services.php`)

- [ ] Implement `groqTranscribe(string $audioPath): ?array`
  - Build multipart/form-data body manually (no CURL file upload — for reliability)
  - POST to `https://api.groq.com/openai/v1/audio/transcriptions`
  - Model: `whisper-large-v3-turbo`
  - Response format: `verbose_json`
  - Return `['text' => ..., 'language' => ...]` or null on failure
  - Enforce 25 MB file size limit
  - Support: webm, ogg, mp3, mp4, m4a, wav, flac

- [ ] Implement `geminiEvaluate(string $question, string $transcript, string $difficulty): ?array`
  - POST to `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent`
  - Prompt: Expert technical interviewer, return ONLY valid JSON
  - JSON fields: `overall_score`, `technical_score`, `communication_score`, `strengths`, `weaknesses`, `summary`
  - `maxOutputTokens: 8192` ← **critical** (reasoning models need this high)
  - `temperature: 0.3`
  - `responseMimeType: application/json`
  - Strip markdown code fences from response before `json_decode()`
  - Clamp scores to 0–100

- [ ] Implement DB helpers:
  - `saveTranscript(answerId, text, language)` — upsert `transcripts`
  - `saveEvaluation(answerId, evalArray)` — upsert `ai_evaluations`
  - `upsertEvaluationJob(answerId, status, errorMsg)` — track job state
  - `getTranscriptForAnswer(answerId)`, `getEvaluationForAnswer(answerId)`
  - `getEvaluationSummaryForAttempt(attemptId)` — big JOIN query for admin review

---

## PHASE 6 — Results, Admin Decisions & Candidate Results Page

### 6.1 Database — Phase 6

- [ ] Create and run `database/migration_phase6.sql`:
  - Add `is_interview_specific TINYINT(1) DEFAULT 0` to `questions` table
  - Add `interview_id INT UNSIGNED NULL` to `questions` table (links to which interview)
  - Create `interview_results` table:
    ```sql
    CREATE TABLE interview_results (
      id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      attempt_id      INT UNSIGNED NOT NULL,
      interview_id    INT UNSIGNED NOT NULL,
      candidate_id    INT UNSIGNED NOT NULL,
      admin_verdict   ENUM('Selected','Not Selected','On Hold') NOT NULL,
      admin_notes     TEXT NULL,
      is_published    TINYINT(1) NOT NULL DEFAULT 0,
      published_at    DATETIME NULL,
      created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    );
    ```

### 6.2 Admin Attempt Review (`admin/attempt_review.php`)

- [ ] Show all questions, audio players, transcripts, AI scores per answer
- [ ] Admin verdict section:
  - Radio buttons: **Selected** | **Not Selected** | **On Hold**
  - Large textarea: "Admin Conclusion / Notes"
  - **Publish Result** button (sets `is_published = 1`, logs `published_at`)
- [ ] Average AI scores displayed at top

### 6.3 Candidate Results Page (`candidate/results.php`)

- [ ] List all published results for the logged-in candidate
- [ ] For each interview: verdict badge (green/red/orange), admin notes, date
- [ ] Only shows results where `is_published = 1`
- [ ] Add "Results" link to candidate sidebar

---

## FINAL CHECKLIST — Before Testing

- [ ] All SQL migration files run in order: schema → phase2 → phase3 → phase4 → phase5 → phase6
- [ ] `.env` file created with real API keys (Groq + Gemini)
- [ ] `uploads/audio/`, `uploads/documents/`, `uploads/videos/` folders exist and are **writable** by Apache
- [ ] XAMPP Apache + MySQL are running
- [ ] `mod_rewrite` and `mod_headers` are enabled in `httpd.conf`
- [ ] PHP extensions enabled: `pdo`, `pdo_mysql`, `curl`, `fileinfo`, `mbstring`
- [ ] Test login: `admin@aiplatform.com` / `password`
- [ ] Change default admin password immediately after first login

---

## 🔑 API Keys — Where to Get Them

| Service | Where to Get | Add to `.env` as |
|---------|-------------|-----------------|
| Groq (Whisper) | https://console.groq.com/ → API Keys | `GROQ_API_KEY=...` |
| Google Gemini | https://aistudio.google.com/app/apikey | `GEMINI_API_KEY=...` |

---

## 🛠️ Tech Stack Summary

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, Bootstrap 5, Vanilla JavaScript |
| Backend | PHP 8.1+ |
| Database | MySQL 8 (via PDO) |
| Server | Apache (XAMPP) |
| AI Transcription | Groq Whisper Large V3 Turbo |
| AI Evaluation | Google Gemini 2.5 Flash |
| Audio Recording | Browser MediaRecorder API |
