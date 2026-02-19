<?php
/**
 * SAWARI — Landing Page
 * 
 * Entry point for the application. Introduces users to Sawari
 * and provides navigation to the map, agent dashboard, and admin panel.
 */

require_once __DIR__ . '/api/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sawari — Navigate Nepal's Public Transport</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        .landing-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Hero */
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary-600) 0%, var(--color-primary-800) 100%);
            color: var(--color-white);
            text-align: center;
            padding: var(--space-12) var(--space-6);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1.5" fill="rgba(255,255,255,0.08)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="40" cy="80" r="1.2" fill="rgba(255,255,255,0.06)"/><circle cx="60" cy="10" r="0.8" fill="rgba(255,255,255,0.04)"/><circle cx="90" cy="90" r="1.3" fill="rgba(255,255,255,0.07)"/></svg>');
            background-size: 200px 200px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 600px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-full);
            padding: var(--space-1) var(--space-4);
            font-size: var(--text-xs);
            font-weight: var(--font-medium);
            margin-bottom: var(--space-6);
        }

        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: var(--font-bold);
            line-height: var(--leading-tight);
            margin: 0 0 var(--space-4);
            letter-spacing: var(--tracking-tight);
        }

        .hero p {
            font-size: var(--text-lg);
            opacity: 0.9;
            margin: 0 0 var(--space-8);
            line-height: var(--leading-relaxed);
        }

        .hero-actions {
            display: flex;
            gap: var(--space-4);
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-actions .btn {
            padding: var(--space-3) var(--space-8);
            font-size: var(--text-base);
            border-radius: var(--radius-lg);
        }

        .btn-hero-primary {
            background: var(--color-white);
            color: var(--color-primary-700);
            border: none;
            font-weight: var(--font-semibold);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        }

        .btn-hero-primary:hover {
            background: var(--color-neutral-50);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.12);
            color: var(--color-white);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Features */
        .features {
            padding: var(--space-16) var(--space-6);
            background: var(--color-white);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-8);
            max-width: 900px;
            margin: 0 auto;
        }

        .feature-card {
            text-align: center;
            padding: var(--space-6);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-xl);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-4);
        }

        .feature-card h3 {
            font-size: var(--text-base);
            font-weight: var(--font-semibold);
            color: var(--color-neutral-900);
            margin: 0 0 var(--space-2);
        }

        .feature-card p {
            font-size: var(--text-sm);
            color: var(--color-neutral-500);
            margin: 0;
            line-height: var(--leading-relaxed);
        }

        /* Footer */
        .landing-footer {
            padding: var(--space-6);
            text-align: center;
            background: var(--color-neutral-50);
            border-top: 1px solid var(--color-neutral-200);
        }

        .landing-footer p {
            font-size: var(--text-sm);
            color: var(--color-neutral-500);
            margin: 0;
        }

        .landing-footer a {
            color: var(--color-primary-600);
            text-decoration: none;
        }

        .landing-footer a:hover {
            text-decoration: underline;
        }

        .footer-links {
            display: flex;
            gap: var(--space-6);
            justify-content: center;
            margin-bottom: var(--space-3);
        }

        @media (max-width: 640px) {
            .hero {
                padding: var(--space-8) var(--space-4);
            }

            .hero-actions {
                flex-direction: column;
                align-items: center;
            }

            .hero-actions .btn {
                width: 100%;
                max-width: 280px;
            }

            .features {
                padding: var(--space-10) var(--space-4);
            }
        }
    </style>
</head>

<body class="landing-page">

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i data-feather="map" style="width:14px;height:14px;"></i>
                Nepal's Public Transport Navigator
            </div>

            <h1>Navigate with <span style="color:var(--color-accent-300);">Sawari</span></h1>

            <p>Find bus routes, track vehicles in real-time, and navigate Nepal's public transport system with confidence. No more asking strangers for directions.</p>

            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/pages/map.php" class="btn btn-hero-primary">
                    <i data-feather="navigation" style="width:20px;height:20px;"></i>
                    Find My Route
                </a>
                <a href="<?= BASE_URL ?>/pages/agent/login.php" class="btn btn-hero-secondary">
                    <i data-feather="users" style="width:18px;height:18px;"></i>
                    Agent Portal
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon" style="background:var(--color-primary-50);color:var(--color-primary-600);">
                    <i data-feather="search" style="width:24px;height:24px;"></i>
                </div>
                <h3>Smart Route Finding</h3>
                <p>Enter your start and destination — Sawari finds the best bus routes, including transfers when needed.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:var(--color-accent-50);color:var(--color-accent-600);">
                    <i data-feather="radio" style="width:24px;height:24px;"></i>
                </div>
                <h3>Live Bus Tracking</h3>
                <p>See real-time positions of GPS-enabled buses on the map with ETA to your stop.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:var(--color-success-50);color:var(--color-success-600);">
                    <i data-feather="wind" style="width:24px;height:24px;"></i>
                </div>
                <h3>Carbon Calculator</h3>
                <p>See how much CO₂ you save by choosing public transport over private vehicles.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-links">
            <a href="<?= BASE_URL ?>/pages/map.php">Map</a>
            <a href="<?= BASE_URL ?>/pages/agent/login.php">Agent Login</a>
            <a href="<?= BASE_URL ?>/pages/admin/login.php">Admin</a>
        </div>
        <p>&copy; 2026 Sawari — Community-powered public transport navigation for Nepal.</p>
    </footer>

    <script>feather.replace({ 'stroke-width': 1.75 });</script>
</body>

</html>
