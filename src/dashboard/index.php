<?php
require_once '../config.php';
requireAdmin();

// Stats globales
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) AS confirmees,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) AS annulees,
        SUM(CASE WHEN statut = 'confirmee' THEN prix_total ELSE 0 END) AS revenus
    FROM reservations
")->fetch(PDO::FETCH_ASSOC);

// Réservations par hôtel
$par_hotel = $pdo->query("
    SELECT hotel, COUNT(*) AS nb, SUM(prix_total) AS total
    FROM reservations GROUP BY hotel
")->fetchAll(PDO::FETCH_ASSOC);

// Réservations par mois (12 derniers mois)
$par_mois = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS mois, COUNT(*) AS nb
    FROM reservations
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mois ORDER BY mois
")->fetchAll(PDO::FETCH_ASSOC);

// Action: changer statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['statut'], $_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
    $stmt->execute([$_POST['statut'], $_POST['id']]);
    header('Location: index.php');
    exit;
}

// Toutes les réservations avec infos client
$reservations = $pdo->query("
    SELECT r.*, u.nom, u.prenom, u.email
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$mois_labels = json_encode(array_column($par_mois, 'mois'));
$mois_data   = json_encode(array_column($par_mois, 'nb'));
$hotel_labels = json_encode(array_column($par_hotel, 'hotel'));
$hotel_data   = json_encode(array_column($par_hotel, 'nb'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Seabel Hotels</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f0f2f5; color: #333; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background: #0f3460;
            color: white;
            padding: 30px 0;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }
        .sidebar-logo { padding: 0 25px 30px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo img { height: 35px; filter: brightness(0) invert(1); }
        .sidebar-logo p { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 5px; letter-spacing: 1px; text-transform: uppercase; }
        .sidebar nav { padding: 20px 0; flex: 1; }
        .sidebar nav a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.1); color: white; border-left: 3px solid #e94560; padding-left: 22px; }
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(255,255,255,0.1); }
        .sidebar-footer a { color: rgba(255,255,255,0.5); font-size: 12px; text-decoration: none; }
        .sidebar-footer a:hover { color: white; }

        /* Main */
        .main { flex: 1; padding: 35px; overflow-y: auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 28px; color: #0f3460; margin-bottom: 30px; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border-top: 4px solid #0f3460;
        }
        .kpi-card.green { border-top-color: #28a745; }
        .kpi-card.orange { border-top-color: #fd7e14; }
        .kpi-card.red { border-top-color: #dc3545; }
        .kpi-card.gold { border-top-color: #ffc107; }
        .kpi-label { font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #999; margin-bottom: 10px; }
        .kpi-value { font-size: 32px; font-weight: 700; color: #0f3460; }
        .kpi-card.green .kpi-value { color: #28a745; }
        .kpi-card.orange .kpi-value { color: #fd7e14; }
        .kpi-card.red .kpi-value { color: #dc3545; }
        .kpi-card.gold .kpi-value { color: #856404; }

        /* Charts */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-bottom: 35px; }
        .chart-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .chart-title { font-size: 14px; font-weight: 600; color: #0f3460; margin-bottom: 20px; }

        /* Table */
        .table-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-input { padding: 9px 15px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-family: 'Montserrat', sans-serif; font-size: 13px; outline: none; width: 250px; }
        .search-input:focus { border-color: #0f3460; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; color: #666; padding: 12px 15px; text-align: left; font-weight: 600; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; border-bottom: 2px solid #e0e0e0; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover td { background: #fafafa; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-attente { background: #fff3cd; color: #856404; }
        .badge-confirmee { background: #d1e7dd; color: #0a3622; }
        .badge-annulee { background: #f8d7da; color: #842029; }

        .statut-form select { padding: 5px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; cursor: pointer; }
        .statut-form button { padding: 5px 10px; background: #0f3460; color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer; margin-left: 5px; }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .charts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="https://slelguoygbfzlpylpxfs.supabase.co/storage/v1/object/public/test-clones/bacaa8ed-efd0-432f-a0ac-5a712ea986ef-seabelhotels-com/assets/images/seabel_hotels_logo-11.svg" alt="Seabel">
            <p>Administration</p>
        </div>
        <nav>
            <a href="index.php" class="active">📊 Tableau de bord</a>
            <a href="../reservation/reservation.php">🏨 Réservations</a>
            <a href="../../index.html">🌐 Site web</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../login/logout.php">⬅ Déconnexion</a>
        </div>
    </aside>

    <main class="main">
        <div class="page-title">Tableau de bord</div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total réservations</div>
                <div class="kpi-value"><?= $stats['total'] ?></div>
            </div>
            <div class="kpi-card green">
                <div class="kpi-label">Confirmées</div>
                <div class="kpi-value"><?= $stats['confirmees'] ?></div>
            </div>
            <div class="kpi-card orange">
                <div class="kpi-label">En attente</div>
                <div class="kpi-value"><?= $stats['en_attente'] ?></div>
            </div>
            <div class="kpi-card red">
                <div class="kpi-label">Annulées</div>
                <div class="kpi-value"><?= $stats['annulees'] ?></div>
            </div>
            <div class="kpi-card gold">
                <div class="kpi-label">Revenus confirmés</div>
                <div class="kpi-value"><?= number_format($stats['revenus'] ?? 0, 0, ',', ' ') ?> €</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Réservations par mois</div>
                <canvas id="chartMois" height="100"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Répartition par hôtel</div>
                <canvas id="chartHotel" height="200"></canvas>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="chart-title" style="margin:0">Toutes les réservations</div>
                <input type="text" class="search-input" id="searchInput" placeholder="Rechercher client, hôtel...">
            </div>
            <table id="reservTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Hôtel</th>
                        <th>Chambre</th>
                        <th>Arrivée</th>
                        <th>Départ</th>
                        <th>Pers.</th>
                        <th>Total</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?></strong><br>
                            <small style="color:#999"><?= htmlspecialchars($r['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['hotel']) ?></td>
                        <td><?= htmlspecialchars($r['chambre']) ?></td>
                        <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
                        <td><?= $r['nb_personnes'] ?></td>
                        <td><?= number_format($r['prix_total'], 0) ?> €</td>
                        <td>
                            <form method="POST" class="statut-form" style="display:flex;align-items:center;">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <select name="statut">
                                    <option value="en_attente" <?= $r['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="confirmee" <?= $r['statut'] === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                                    <option value="annulee" <?= $r['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                </select>
                                <button type="submit">✓</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Chart par mois
        new Chart(document.getElementById('chartMois'), {
            type: 'bar',
            data: {
                labels: <?= $mois_labels ?>,
                datasets: [{
                    label: 'Réservations',
                    data: <?= $mois_data ?>,
                    backgroundColor: '#0f3460',
                    borderRadius: 6
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        // Chart par hôtel
        new Chart(document.getElementById('chartHotel'), {
            type: 'doughnut',
            data: {
                labels: <?= $hotel_labels ?>,
                datasets: [{
                    data: <?= $hotel_data ?>,
                    backgroundColor: ['#0f3460', '#e94560', '#16213e']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        // Recherche dans le tableau
        document.getElementById('searchInput').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#reservTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
