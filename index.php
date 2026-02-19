<?php
/**
 * Landing Page — Sawari
 * 
 * Public landing page with hero, features, how-it-works,
 * agents leaderboard, and call-to-action.
 */

$pageTitle = 'Sawari — Navigate Nepal\'s Public Transport';
$pageCss = ['landing.css'];
$bodyClass = 'page-landing';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// ─── Fetch top 10 agents by approved contribution count ───
$db = getDBConnection();
$leaderboardStmt = $db->prepare("
    SELECT a.agent_id, a.name, a.image_path, a.joined_at,
           COUNT(c.contribution_id) AS total_contributions,
           SUM(CASE WHEN c.type = 'location' THEN 1 ELSE 0 END) AS location_count,
           SUM(CASE WHEN c.type = 'route' THEN 1 ELSE 0 END) AS route_count,
           SUM(CASE WHEN c.type = 'vehicle' THEN 1 ELSE 0 END) AS vehicle_count
    FROM agents a
    INNER JOIN contributions c ON c.proposed_by = a.agent_id AND c.status = 'accepted'
    GROUP BY a.agent_id
    ORDER BY total_contributions DESC
    LIMIT 10
");
$leaderboardStmt->execute();
$topAgents = $leaderboardStmt->fetchAll();

// ─── Fetch quick stats ───────────────────────────────────
$statsStmt = $db->query("
    SELECT
        (SELECT COUNT(*) FROM locations WHERE status = 'approved') AS total_locations,
        (SELECT COUNT(*) FROM routes WHERE status = 'approved') AS total_routes,
        (SELECT COUNT(*) FROM vehicles WHERE status = 'approved') AS total_vehicles,
        (SELECT COUNT(*) FROM agents) AS total_agents
");
$stats = $statsStmt->fetch();
?>

<!-- ═══ Hero Section ═══════════════════════════════════════ -->
<section class="hero-section">
    <div class="hero-bg-pattern"></div>
    <div class="hero-content">
        <h1 class="hero-title">Navigate Nepal's Public Transport <span class="hero-highlight">with Ease</span></h1>
        <p class="hero-subtitle">
            Find bus routes, estimate fares, get walking directions, and navigate
            Kathmandu Valley's public transportation — all in one place.
        </p>
        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/pages/map.php" class="btn btn-hero-primary">
                <span class="btn-icon"><i class="fa-duotone fa-solid fa-map-location-dot"></i></span>
                Find Your Route <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="<?= BASE_URL ?>/pages/auth/register.php" class="btn btn-hero-secondary">
                Become an Agent
            </a>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat-number"><?= (int) $stats['total_locations'] ?></span>
                <span class="hero-stat-label">Bus Stops</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number"><?= (int) $stats['total_routes'] ?></span>
                <span class="hero-stat-label">Routes</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number"><?= (int) $stats['total_vehicles'] ?></span>
                <span class="hero-stat-label">Vehicles</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number"><?= (int) $stats['total_agents'] ?></span>
                <span class="hero-stat-label">Agents</span>
            </div>
        </div>
    </div>
</section>

<!-- ═══ How It Works ═══════════════════════════════════════ -->
<section class="section how-it-works">
    <div class="container">
        <h2 class="section-title">How It Works</h2>
        <p class="section-subtitle">Three simple steps to navigate Kathmandu Valley</p>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-icon"><i class="fa-duotone fa-solid fa-location-dot"></i></div>
                <h3 class="step-title">Enter Start Point</h3>
                <p class="step-desc">
                    Type your starting location or use GPS to detect it automatically.
                </p>
            </div>
            <div class="step-connector"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-icon"><i class="fa-duotone fa-solid fa-flag-checkered"></i></div>
                <h3 class="step-title">Enter Destination</h3>
                <p class="step-desc">
                    Search for your destination from hundreds of mapped bus stops and landmarks.
                </p>
            </div>
            <div class="step-connector"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-icon"><i class="fa-duotone fa-solid fa-map-location-dot"></i></div>
                <h3 class="step-title">Get Directions</h3>
                <p class="step-desc">
                    View step-by-step directions with bus routes, fares, and walking paths.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══ Features Highlight ═════════════════════════════════ -->
<section class="section features-section">
    <div class="container">
        <h2 class="section-title">Why Sawari?</h2>
        <p class="section-subtitle">Everything you need to navigate public transport in Nepal</p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-duotone fa-solid fa-bus"></i></div>
                <h3 class="feature-title">Real Routes</h3>
                <p class="feature-desc">
                    Actual bus routes mapped by local volunteer agents who ride these routes daily.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-duotone fa-solid fa-money-bill-wave"></i></div>
                <h3 class="feature-title">Fare Estimates</h3>
                <p class="feature-desc">
                    Know how much your ride will cost before you board — including student and elderly discounts.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-duotone fa-solid fa-arrows-rotate"></i></div>
                <h3 class="feature-title">Bus Switching</h3>
                <p class="feature-desc">
                    Smart multi-bus route suggestions when no direct bus connects your start and end points.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-duotone fa-solid fa-person-walking"></i></div>
                <h3 class="feature-title">Walking Directions</h3>
                <p class="feature-desc">
                    Step-by-step walking guidance to the nearest bus stop and from your drop-off to the destination.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-duotone fa-solid fa-triangle-exclamation"></i></div>
                <h3 class="feature-title">Emergency Alerts</h3>
                <p class="feature-desc">
                    Stay informed about route disruptions, road closures, and transport strikes.
                </p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-duotone fa-solid fa-handshake"></i></div>
                <h3 class="feature-title">Community Driven</h3>
                <p class="feature-desc">
                    Powered by volunteer agents who contribute and maintain route data for everyone.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══ Agents Leaderboard ═════════════════════════════════ -->
<section class="section leaderboard-section">
    <div class="container">
        <h2 class="section-title">Top Contributors</h2>
        <p class="section-subtitle">
            Our amazing volunteer agents who map Nepal's public transport
        </p>

        <?php if (!empty($topAgents)): ?>
            <div class="leaderboard-grid">
                <?php foreach ($topAgents as $rank => $agent): ?>
                    <div class="leaderboard-card <?= $rank < 3 ? 'leaderboard-top' : '' ?>">
                        <div class="leaderboard-rank">
                            <?php if ($rank === 0): ?>
                                <span class="rank-medal"><i class="fa-sharp-duotone fa-solid fa-trophy"
                                        style="color:#FFD700;"></i></span>
                            <?php elseif ($rank === 1): ?>
                                <span class="rank-medal"><i class="fa-sharp-duotone fa-solid fa-medal"
                                        style="color:#C0C0C0;"></i></span>
                            <?php elseif ($rank === 2): ?>
                                <span class="rank-medal"><i class="fa-sharp-duotone fa-solid fa-medal"
                                        style="color:#CD7F32;"></i></span>
                            <?php else: ?>
                                <span class="rank-number">#<?= $rank + 1 ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="leaderboard-avatar">
                            <?php if ($agent['image_path']): ?>
                                <img src="<?= BASE_URL ?>/assets/images/uploads/<?= sanitize($agent['image_path']) ?>"
                                    alt="<?= sanitize($agent['name']) ?>">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= strtoupper(substr($agent['name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="leaderboard-info">
                            <span class="leaderboard-name"><?= sanitize($agent['name']) ?></span>
                            <span class="leaderboard-count">
                                <?= (int) $agent['total_contributions'] ?>
                                contribution<?= $agent['total_contributions'] != 1 ? 's' : '' ?>
                            </span>
                            <span class="leaderboard-breakdown">
                                <?php if ($agent['location_count'] > 0): ?>
                                    <span class="breakdown-tag" title="Locations"><i
                                            class="fa-duotone fa-solid fa-location-dot"></i>
                                        <?= (int) $agent['location_count'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($agent['route_count'] > 0): ?>
                                    <span class="breakdown-tag" title="Routes"><i class="fa-duotone fa-solid fa-route"></i>
                                        <?= (int) $agent['route_count'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($agent['vehicle_count'] > 0): ?>
                                    <span class="breakdown-tag" title="Vehicles"><i class="fa-duotone fa-solid fa-bus"></i>
                                        <?= (int) $agent['vehicle_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="leaderboard-since">Since
                                <?= date('M Y', strtotime($agent['joined_at'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="leaderboard-empty">
                <p>No contributions yet. Be the first to contribute!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ═══ Call to Action ═════════════════════════════════════ -->
<section class="section cta-section">
    <div class="container">
        <div class="cta-card">
            <h2 class="cta-title">Want to Help Map Nepal's Transport?</h2>
            <p class="cta-desc">
                Become a Sawari agent and contribute bus stops, routes, and vehicle information
                to help thousands of commuters navigate the streets of Nepal.
            </p>
            <a href="<?= BASE_URL ?>/pages/auth/register.php" class="btn btn-cta">
                <i class="fa-duotone fa-solid fa-rocket"></i> Become an Agent — It's Free
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>