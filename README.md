# 🚀 HireIQ — AI Interview Assessment & Enterprise Secure Document Platform

**HireIQ** is a production-grade, self-hosted AI-powered interview assessment and secure learning management (LMS) platform built using PHP, MySQL, Vanilla CSS, Bootstrap 5, and JavaScript.

---

## 🌟 Key Capabilities

### 1. 🤖 AI-Powered Interview Pipeline
*   **Audio Recording:** Uses the HTML5 `MediaRecorder` API to capture high-quality candidate audio responses in `.webm` format.
*   **Groq Whisper Transcription:** Transcribes candidates' audio files asynchronously using `whisper-large-v3-turbo`.
*   **Gemini 2.5 Evaluation:** Automatically evaluates transcripts against question rubrics for Technical Score, Communication, Strengths, Weaknesses, and overall matching.

### 2. 🔒 Enterprise Secure Document Viewer (Phase 4.5 DRM)
*   **Secure Storage:** All documents are stored outside the public directory context and access is protected via `.htaccess` rules (direct access returns a `403 Forbidden`).
*   **Stateless HMAC URL Signing:** Streaming links are signed dynamically with unique temporary keys (10-minute expiry) to prevent links from being shared.
*   **Udemy-Style Screen Capture Protection:** If the user attempts to capture the screen, tab out, or open DevTools, the viewer page immediately blurs and blacks out, preventing clean exfiltration.
*   **Watermarks Burned into Canvas:** Custom watermarks containing the user's name, email, ID, and live timestamp are rendered directly into the PDF.js page canvas.
*   **Progress Tracking:** Monitors the user's page index, percentage completed, and duration.
*   **Confidentiality Gate:** Requires explicit agreement to terms before viewing.

---

## 🗂️ Project Directory Structure

```
ai_interview_platform/
├── .env.example              ← Template file for API keys (never commit real keys)
├── .htaccess                 ← Apache configurations (denial of direct folder listings)
├── index.php                 ← Platform router (Redirects to dashboard or login)
├── login.php / register.php  ← Authentication endpoints
├── document_viewer.php       ← DRM document reader (PDF.js-based rendering)
├── document_stream.php       ← Secure document streamer (verifies HMAC signatures)
│
├── config/
│   ├── app.php               ← App configuration bootstrap (loads .env)
│   └── database.php          ← PDO singleton database controller
│
├── api/
│   ├── acknowledge.php       ← Stores document agreement records
│   ├── log_activity.php      ← Stores heartbeats and security violation logs
│   └── save_answer.php       ← Saves audio files & kicks off Whisper/Gemini evaluations
│
├── includes/
│   ├── auth.php              ← Authentication & Role gates
│   ├── documents.php         ← Document management & stream operations
│   ├── layout.php            ← Common layouts (HTML shells, headers, footers)
│   └── ai_services.php       ← API clients for Groq and Google Gemini APIs
│
├── admin/
│   ├── dashboard.php         ← Admin dashboard
│   ├── documents.php         ← Admin Document upload/assignment center
│   ├── document_analytics.php← 4-Tab security analytics dashboard (Overview, Risk Users, Logs)
│   └── attempt_review.php    ← Interactive review for candidates' AI scoring
│
├── candidate/
│   ├── dashboard.php         ← Candidate main control panel
│   ├── interview_engine.php  ← Live voice response interview center
│   └── documents.php         ← Shared resources view portal
│
├── database/
│   ├── schema.sql            ← Initial schema
│   └── migration_phase4_5.sql← DRM table migration scripts
│
└── uploads/
    ├── audio/                ← Audio voice response recordings (.webm)
    └── documents/            ← Encrypted PDF/Office files
```

---

## 🛠️ Step-by-Step Installation

### Prerequisites
1.  Install **XAMPP** (supporting PHP 8.1+ & MySQL).
2.  Clone this repository into your local XAMPP web root directory:
    ```bash
    cd C:\xampp\htdocs
    git clone https://github.com/BurelaBhavan/HireIQ.git ai_interview_platform
    ```

### 1. Database Setup
1.  Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
2.  Go to `http://localhost/phpmyadmin` in your web browser.
3.  Create a new database named `ai_interview_platform` using `utf8mb4_general_ci`.
4.  Import the database schemas:
    *   First, import `database/schema.sql`
    *   Then, import all migration scripts sequentially: `migration_phase2.sql` through `migration_phase4_5.sql`.

### 2. Environment Configurations
1.  Duplicate the `.env.example` file in the root folder and rename the copy to `.env`:
    ```bash
    cp .env.example .env
    ```
2.  Open `.env` and fill in your API credentials:
    ```env
    GROQ_API_KEY=your-actual-groq-api-key
    GEMINI_API_KEY=your-actual-gemini-api-key
    ```

### 3. Launching the App
Simply navigate to:
**`http://localhost/ai_interview_platform/`**

---

## 🔒 Security Infrastructure Detail

### 1. Direct Access Block
The `uploads/` directory contains a strict configuration inside `uploads/.htaccess`:
```apache
Order Deny,Allow
Deny from all
```
Any direct HTTP requests to files (e.g. `http://localhost/ai_interview_platform/uploads/documents/sample.pdf`) will return a **`403 Forbidden`**.

### 2. Signed Document Requests
To read a document, the application generates a signed URL containing:
*   `id`: The Document ID
*   `uid`: The User ID
*   `ts`: Expiry Unix timestamp
*   `sig`: SHA256 signature generated by hashing parameters against `STREAM_SECRET`
If the signature is tempered with, or has expired, access is blocked.

### 3. Capture & Defocus Blackouts
Whenever a user triggers a screenshot tool, blurs the browser window (e.g. clicks out to Snipping Tool, Win+Shift+S), switches tabs, or opens developer panels, the system immediately loads a full-screen blackout barrier to safeguard content.

---

## 📜 License
Internal Enterprise Use & Client Verification.
