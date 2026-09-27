<?php

// ============================================================
// MODUS CBT — About Us, Engineering Team & Product Ecosystem
// ============================================================

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
requireTeacher();

$base = app_base_url();

layout_header('About & Ecosystem — MODUS CBT', 'settings');
?>

<div class="settings-header">
    <div class="settings-header-title">
        <h1><span>ℹ</span> About Us & The Modus Ecosystem</h1>
        <p>Meet the engineers and discover the interconnected academic operating platforms of Modus.</p>
    </div>
    <div class="settings-actions">
        <a href="<?= $base ?>settings/index.php" class="btn btn-secondary">
            ← Back to Settings
        </a>
    </div>
</div>

<!-- ============================================================
     SECTION 1: CORE ENGINEERING TEAM
     ============================================================ -->
<div style="margin-bottom: 40px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <span style="font-size: 20px;">👥</span>
        <h2 style="font-size: 19px; font-weight: 800; color: var(--text); letter-spacing: -0.3px;">Core Engineering Team</h2>
    </div>

    <div class="developer-cards-grid">

        <!-- Developer 1: Swastik Bhattacharjee -->
        <div class="dev-card">
            <span class="dev-order-tag">Lead #1</span>
            <div class="dev-header">
                <div class="dev-avatar-circle">
                    SB
                </div>
                <div class="dev-info">
                    <h3>Swastik Bhattacharjee</h3>
                    <span class="dev-role-badge">Systems and Backend Developer</span>
                </div>
            </div>

            <div class="dev-bio-quote">
                "My code doesn't always work, but when it does, I have no idea why."
            </div>

            <div class="dev-links">
                <a href="https://github.com/SwastikBhattacharjee" target="_blank" rel="noopener noreferrer" class="dev-link-btn">
                    <span>🐙</span> GitHub: @SwastikBhattacharjee
                </a>
                <a href="mailto:swastik.bhattacharjee@outlook.com" class="dev-link-btn">
                    <span>✉</span> swastik.bhattacharjee@outlook.com
                </a>
            </div>
        </div>

        <!-- Developer 2: Arif Hasan -->
        <div class="dev-card">
            <span class="dev-order-tag">Core #2</span>
            <div class="dev-header">
                <div class="dev-avatar-circle" style="background: linear-gradient(135deg, #3b82f6, #06b6d4);">
                    AH
                </div>
                <div class="dev-info">
                    <h3>Arif Hasan</h3>
                    <span class="dev-role-badge" style="color: #38bdf8;">Frontend Developer</span>
                </div>
            </div>

            <div class="dev-bio-quote">
                "I have 99 problems, and a missing semicolon is probably 98 of them."
            </div>

            <div class="dev-links">
                <span class="dev-link-btn" style="cursor: default;">
                    <span>🎨</span> UI/UX & Client Architecture
                </span>
                <span class="dev-link-btn" style="cursor: default;">
                    <span>⚡</span> Design Engineering
                </span>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================
     SECTION 2: PRODUCT ECOSYSTEM SHOWCASE
     ============================================================ -->
<div style="margin-bottom: 30px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <span style="font-size: 20px;">🚀</span>
        <h2 style="font-size: 19px; font-weight: 800; color: var(--text); letter-spacing: -0.3px;">The Modus Suite of Products</h2>
    </div>

    <div class="products-stack-grid">

        <!-- Product 1: Faculty by Modus -->
        <div class="product-detail-card">
            <div class="product-head">
                <div class="product-title-group">
                    <h2><span>🏫</span> Faculty by Modus</h2>
                    <div class="product-tagline">The Academic Operating System for NEET and JEE Educators</div>
                </div>
                <span class="tech-pill" style="background: rgba(139, 92, 246, 0.15); color: #c084fc; border-color: rgba(139, 92, 246, 0.3);">
                    React 19 • Supabase
                </span>
            </div>

            <p class="product-overview-text">
                <strong>Faculty by Modus</strong> is a focused workspace built specifically for NEET and JEE tutors, coaching rooms, and academy faculty. Rather than wrestling with disorganized spreadsheets and disconnected grading tools, Faculty brings batch health, rapid marking (+4/−1), syllabus taxonomy, and student vulnerability diagnostics into a single cohesive interface. The web application runs in <strong>standalone client mode</strong>, communicating directly with Supabase via PostgreSQL Row-Level Security (RLS) and Go-True Auth.
            </p>

            <div class="product-features-title">Key Capabilities</div>
            <div class="product-feature-chips">
                <div class="product-feature-chip"><span>👥</span> Cohort & Batch Management</div>
                <div class="product-feature-chip"><span>⚡</span> Rapid Marking Engine (C, W, U, M keypad)</div>
                <div class="product-feature-chip"><span>📊</span> NCERT Taxonomy & Vulnerability Index (0–100)</div>
                <div class="product-feature-chip"><span>📄</span> Discrete Multi-Page PDF Scorecards</div>
                <div class="product-feature-chip"><span>🔒</span> Teacher-Isolated Row-Level Security</div>
                <div class="product-feature-chip"><span>💎</span> Academy License Redemption System</div>
            </div>

            <div class="product-tech-pills">
                <span class="tech-pill">React 19</span>
                <span class="tech-pill">Vite</span>
                <span class="tech-pill">TypeScript</span>
                <span class="tech-pill">Tailwind CSS v4</span>
                <span class="tech-pill">Radix UI</span>
                <span class="tech-pill">TanStack Query</span>
                <span class="tech-pill">Framer Motion</span>
                <span class="tech-pill">Recharts</span>
                <span class="tech-pill">Supabase Auth & RLS</span>
            </div>
        </div>

        <!-- Product 2: Tracker by Modus -->
        <div class="product-detail-card" style="border-left-color: #f59e0b;">
            <div class="product-head">
                <div class="product-title-group">
                    <h2><span>📱</span> Tracker by Modus</h2>
                    <div class="product-tagline" style="color: #f59e0b;">Android-First Study Operating System for Aspirants</div>
                </div>
                <span class="tech-pill" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border-color: rgba(245, 158, 11, 0.3);">
                    Flutter 3.x • Firebase • Drift
                </span>
            </div>

            <p class="product-overview-text">
                <strong>Tracker by Modus</strong> unifies planning, syllabus tracking, attempts, tests, mistake retention, habits, focus sessions, discipline scoring, XP, Action Reports, daily time logging, AI guidance, prediction analytics, Drive-backed study assets, rooms, friends, plugins (NEET Prep & Allen DLP), device governance, adaptive notifications, Android widgets, and parent oversight into one Flutter application with 100% offline Chapter Performance Analytics.
            </p>

            <div class="product-features-title">Key Capabilities</div>
            <div class="product-feature-chips">
                <div class="product-feature-chip"><span>🎯</span> Dynamic Syllabus Catalog v3 (NEET vs JEE)</div>
                <div class="product-feature-chip"><span>📴</span> 100% Offline Drift / SQLite Analytics Cache</div>
                <div class="product-feature-chip"><span>🔁</span> Spaced Mistakes Review & Carousel Deck</div>
                <div class="product-feature-chip"><span>🧠</span> AI Mentor (Groq Whisper Voice, Gemini, OpenRouter)</div>
                <div class="product-feature-chip"><span>🤝</span> Study Rooms & RTDB Real-Time Leaderboard</div>
                <div class="product-feature-chip"><span>🎨</span> 10 Curated Academic Color Palettes</div>
                <div class="product-feature-chip"><span>🔌</span> Plugins Hub (NEET Prep & Allen DLP)</div>
            </div>

            <div class="product-tech-pills">
                <span class="tech-pill">Flutter 3.x</span>
                <span class="tech-pill">Dart ^3.10.7</span>
                <span class="tech-pill">Firebase Auth & Firestore</span>
                <span class="tech-pill">Drift / SQLite</span>
                <span class="tech-pill">Riverpod 2.x</span>
                <span class="tech-pill">Google Drive API</span>
                <span class="tech-pill">Android Kotlin Bridge</span>
            </div>
        </div>

        <!-- Product 3: CBT by Modus -->
        <div class="product-detail-card" style="border-left-color: #20e596;">
            <div class="product-head">
                <div class="product-title-group">
                    <h2><span>💻</span> CBT by Modus (This System)</h2>
                    <div class="product-tagline">High-Concurrency Computer Based Examination Engine</div>
                </div>
                <span class="tech-pill">
                    PHP 8.x • MariaDB • Vanilla JS
                </span>
            </div>

            <p class="product-overview-text">
                <strong>CBT by Modus</strong> is an institutional examination engine designed for real-time exam administration, fraud deterrence, and instant automated grading. Featuring TCS-style exam navigation, strict session timing, live teacher heartbeat controls, LaTeX mathematical notation, and encrypted QR verification, CBT provides a resilient test delivery platform that functions smoothly even across low-bandwidth campus intranets.
            </p>

            <div class="product-features-title">Key Capabilities</div>
            <div class="product-feature-chips">
                <div class="product-feature-chip"><span>⏱</span> TCS-Style Palette & Non-Bypassing Timer Lock</div>
                <div class="product-feature-chip"><span>📡</span> Teacher Live Exam Control & Heartbeat Monitor</div>
                <div class="product-feature-chip"><span>∑</span> LaTeX Math Rendering with KaTeX & Formula Support</div>
                <div class="product-feature-chip"><span>📊</span> Automated Instant Scoring & Leaderboard Analytics</div>
                <div class="product-feature-chip"><span>📋</span> Smart CSV Student Importer with Fuzzy Column Detection</div>
                <div class="product-feature-chip"><span>🔲</span> Local QR Code Generation & Dompdf Scorecard Generator</div>
            </div>

            <div class="product-tech-pills">
                <span class="tech-pill">PHP 8.x</span>
                <span class="tech-pill">MariaDB / MySQL (InnoDB Locks)</span>
                <span class="tech-pill">Vanilla ES6+</span>
                <span class="tech-pill">Modus Adaptive Design Tokens</span>
                <span class="tech-pill">Dompdf</span>
                <span class="tech-pill">Chillerlan QR</span>
            </div>
        </div>

    </div>
</div>

<!-- Mission Footer Card -->
<div style="background: var(--panel2); border: 1px solid var(--line); border-radius: 16px; padding: 22px; text-align: center;">
    <p style="font-size: 13.5px; color: var(--muted); margin-bottom: 6px;">
        © 2026 <strong>Modus Academy & Technologies</strong>. Built for educators and aspirants who look twice.
    </p>
    <p style="font-size: 12px; color: #64748b;">
        Engineered with privacy, modularity, and offline resilience as first-class citizens.
    </p>
</div>

<?php
layout_footer();
