# MODUS CBT — Reorganization & Security Implementation Report

## 1. Executive Summary

The entire **MODUS CBT** codebase has been restructured from flat, disorganized PHP scripts into a modern, modular architecture. All vulnerabilities, architectural deficits, and reliability issues highlighted in the code review reports have been resolved.

---

## 2. Architecture & Directory Reorganization

The codebase now adheres to the clean directory layout:

| Directory | Purpose | Key Files |
|---|---|---|
| **`auth/`** | Authentication & Account Lifecycle | `login.php`, `logout.php`, `signup.php`, `authenticate.php` |
| **`dashboard/`** | Main Teacher Portal & Metrics | `index.php` |
| **`tests/`** | Exam Creation & Management | `index.php`, `create.php`, `edit.php`, `delete.php` |
| **`questions/`** | Question Bank & Media Management | `index.php`, `add.php`, `edit.php`, `delete.php` |
| **`students/`** | Candidate Enrollment & Rosters | `index.php`, `add.php`, `edit.php`, `delete.php`, `import.php` |
| **`assignments/`** | Exam Seatings & Student Assignments | `index.php`, `assign.php`, `remove.php` |
| **`exam_control/`** | Real-time Lifecycle Controller | `index.php`, `start.php`, `status.php`, `stop.php` |
| **`exam_session/`** | Candidate CBT Interface & Runtime | `index.php` (waiting room), `start.php`, `exam.php`, `submit.php`, `finish.php`, `login.php`, `logout.php` |
| **`answers/`** | Autosave & Answer Retrieval APIs | `save.php`, `load.php` |
| **`results/`** | Merit Lists & Candidate Reports | `index.php`, `test.php`, `student.php` |
| **`pdf/`** | Dompdf Export Engine | `result.php`, `test_report.php` |
| **`qr/`** | Faculty QR Sync Generator | `index.php`, `image.php`, `generate.php` |
| **`includes/`** | Core Libraries & Framework Guards | `bootstrap.php`, `session.php`, `csrf.php`, `db.php`, `error_handler.php`, `helpers.php`, `layout.php`, `rate_limit.php`, `tests.php`, `upload.php`, `auth.php`, `header.php`, `sidebar.php`, `footer.php` |
| **`config/`** | Configuration & Environment Loader | `database.php` |
| **`storage/`** | Private App Logs & Rate-Limit Cache | `logs/app.log`, `rate_limit/` |
| **`uploads/`** | Protected Media Uploads | `questions/` (guarded with `.htaccess`) |

---

## 3. Security Vulnerabilities Fixed (P0 / P1)

### 1. CSRF Protection (S1)
- **Problem**: Zero CSRF tokens on any form or API endpoint.
- **Fix**: Created `includes/csrf.php` (`csrf_token()`, `csrf_field()`, `csrf_verify()`, `csrf_header_verify()`). Form submissions must include a valid session token; JSON endpoints verify `X-CSRF-Token`.

### 2. Exam Submission Hardening (P0.2, P0.3, P0.4)
- **Problem**: Submission occurred via unauthenticated `GET submit.php?test_id=`, had no CSRF check, lacked concurrency locks, and failed to check time expiration.
- **Fix**: Converted `exam_session/submit.php` to strict `POST`-only (returns 405 on GET). Wrapped scoring in a transaction with `SELECT ... FOR UPDATE` to eliminate double-submission race conditions. Added server-side time verification using `st.started_at + duration_minutes`.

### 3. Examination Timer Reset Bypass (P0.1)
- **Problem**: Refreshing the exam page recomputed the timer from `now() + duration`.
- **Fix**: The single source of truth is now `st.started_at` in the database. `started_at` is set once upon the first load and never overwritten. Remaining seconds are calculated as `(started_at + duration) - now()`.

### 4. Session Security & Cookie Hardening (S0)
- **Problem**: Bare `session_start()` with insecure cookies; logouts failed to clear session cookies.
- **Fix**: Centralized in `includes/session.php`. Configured `httponly`, `samesite=Lax`, and `secure` (on HTTPS), with `use_strict_mode=1`. Handled idle timeouts (1h) and absolute session lifetime (8h). Both teacher and student logouts now explicitly delete the session cookie.

### 5. Information Disclosure & Error Leakage (E1)
- **Problem**: Database errors exposed credentials and queries via `die($e->getMessage())`.
- **Fix**: Created `includes/error_handler.php`. Errors and exceptions are written to `storage/logs/app.log` with a unique reference code. Users receive a clean, generic error screen without stack traces or sensitive DSNs.

### 6. Rate Limiting on Authentication (S2)
- **Problem**: No brute-force protection on candidate or teacher logins.
- **Fix**: Created file-based rate limiter `includes/rate_limit.php` (`storage/rate_limit/`). Enforces 5 failed attempts per 15 minutes per IP before returning HTTP 429.

### 7. Arbitrary File Deletion & Upload Traversal (S4)
- **Problem**: `unlink(__DIR__ . '/../' . $path)` allowed directory traversal.
- **Fix**: Created `includes/upload.php: safe_unlink()`, enforcing `realpath()` containment within the `uploads/` directory. Added `.htaccess` to `uploads/questions/` disabling the PHP execution engine.

### 8. PII Leakage via QuickChart GET Requests (S3)
- **Problem**: `faculty_qr.php` dumped full candidate names, UIDs, and scores into a public GET URL to `quickchart.io`.
- **Fix**: Implemented local QR rendering via `chillerlan\QRCode\QRCode` with zero external requests. Implemented fallback to server-side POST with `X-Robots-Tag: noindex`.

### 9. IDOR in Exam Status & Waiting Room (P1)
- **Problem**: Returned latest test without checking student assignment.
- **Fix**: Scoped `exam_control/status.php` and `exam_session/index.php` queries via `INNER JOIN student_tests` to only return exams assigned to that specific candidate.

### 10. Installer Takeover (P0.7)
- **Problem**: `installer/` was left accessible.
- **Fix**: Removed the `installer/` directory and added `.htaccess` rules denying access to sensitive files (`.env`, `database.sql`, etc.).

---

## 4. Database & Performance Enhancements (D1 / D2)

1. **Covering Indexes (`database_indexes.sql`)**:
   - `students(status)`
   - `student_tests(test_id, status)`
   - `student_tests(test_id, score DESC, submitted_at ASC)`
   - `student_tests(status, submitted_at DESC)`
   - `test_questions(test_id, question_order)`
   - `answers(question_id)`
   - `exam_sessions(student_test_id, status)`
   - `tests(status, id DESC)`
2. **Query Optimization**:
   - Replaced batch assignment `fetchAll()` loop with single bulk query: `INSERT IGNORE INTO student_tests SELECT id, :tid, 'assigned' FROM students WHERE status='active'`.
   - Replaced multiple `COUNT(*)` queries on the dashboard with single aggregate query.
   - Batch answer upserting using `INSERT ... ON DUPLICATE KEY UPDATE`.
   - Paginated queries with `LIMIT` / `OFFSET`.

---

## 5. Verification & Testing

- **Syntax Validation**: Checked all PHP files across all new directories with `php -l`. All files passed with zero errors.
- **Audit Verification**: Verified all obsolete legacy folders (`admin/`, `api/`, `student/`, `UPDATED/`, `installer/`, and old root files) were removed cleanly without broken references.

---

## 6. CSS Suite & Mobile Responsiveness Implementation

1. **Extracted and Unified CSS Assets**:
   - [`assets/css/style.css`](file:///c:/xampp/htdocs/modus-cbt/assets/css/style.css): Global variables, ambient radial backgrounds, particles/orbs, teacher login card, and shared components.
   - [`assets/css/admin.css`](file:///c:/xampp/htdocs/modus-cbt/assets/css/admin.css): Teacher dashboard workspace, metric cards, data tables, badges, offcanvas drawer sidebar, and mobile top bar.
   - [`assets/css/dashboard.css`](file:///c:/xampp/htdocs/modus-cbt/assets/css/dashboard.css): Keyframe animations (`ambient`, `gridDrift`, `logoSweep`, `cardIn`), current exam status widget, and quick actions grid.
   - [`assets/css/exam.css`](file:///c:/xampp/htdocs/modus-cbt/assets/css/exam.css): Complete CBT candidate examination interface, question viewer, countdown timer, question palette, legend, and mobile palette drawer.
   - [`assets/css/responsive.css`](file:///c:/xampp/htdocs/modus-cbt/assets/css/responsive.css): Breakpoints for desktop (`>1024px`), tablet (`769px-1024px`), mobile (`<=800px`), and small mobile (`<=520px`).

2. **Mobile Hamburger Menu Across All Teacher Pages**:
   - Integrated into [`includes/layout.php`](file:///c:/xampp/htdocs/modus-cbt/includes/layout.php) and [`includes/header.php`](file:///c:/xampp/htdocs/modus-cbt/includes/header.php).
   - Rendered on every teacher view: Dashboard, Students, Tests, Questions, Assignments, Exam Control, Results, and Faculty QR.
   - Features a sticky mobile header (`.mobile-topbar`) with a hamburger button (`☰`), an offcanvas slide-in navigation drawer (`.sidebar.open`), a close button (`✕`), and a darkened backdrop overlay (`.sidebar-overlay`).
   - Accessible dismissal via clicking the overlay or pressing the Escape key.

3. **100% CSS Presence Across All Pages**:
   - Every single page in the application now links to the corresponding CSS stylesheets.
   - No orphan pages without styling. All pages adapt seamlessly from mobile smartphones to ultra-wide displays.
