<?php
require_once '../config.php';
requireLogin();

// Prix par nuit par hôtel/chambre
$tarifs = [
    'Seabel Rym Beach'    => ['Standard' => 120, 'Supérieure' => 160, 'Suite' => 250],
    'Seabel Aladin'       => ['Standard' => 90,  'Supérieure' => 130, 'Suite' => 200],
    'Seabel Alhambra'     => ['Standard' => 150, 'Supérieure' => 200, 'Suite' => 350],
];

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hotel       = $_POST['hotel'] ?? '';
    $chambre     = $_POST['chambre'] ?? '';
    $arrivee     = $_POST['date_arrivee'] ?? '';
    $depart      = $_POST['date_depart'] ?? '';
    $nb_pers     = (int)($_POST['nb_personnes'] ?? 1);

    if (!$hotel || !$chambre || !$arrivee || !$depart) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif ($depart <= $arrivee) {
        $error = 'La date de départ doit être après la date d\'arrivée.';
    } else {
        $nuits = (new DateTime($arrivee))->diff(new DateTime($depart))->days;
        $prix  = ($tarifs[$hotel][$chambre] ?? 100) * $nuits;

        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, hotel, chambre, date_arrivee, date_depart, nb_personnes, prix_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $hotel, $chambre, $arrivee, $depart, $nb_pers, $prix]);
        $success = "Réservation confirmée ! Total : <strong>{$prix} €</strong> pour {$nuits} nuit(s).";
    }
}

// Mes réservations
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$mes_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tarifs_json = json_encode($tarifs);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation - Seabel Hotels</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; color: #333; }

        .topbar {
            background: #0f3460;
            color: white;
            padding: 14px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar img { height: 35px; filter: brightness(0) invert(1); }
        .topbar-right { display: flex; align-items: center; gap: 20px; font-size: 13px; }
        .topbar-right a { color: rgba(255,255,255,0.8); text-decoration: none; }
        .topbar-right a:hover { color: white; }

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }

        h2 { font-family: 'Playfair Display', serif; font-size: 28px; color: #0f3460; margin-bottom: 25px; }

        .card { background: white; border-radius: 12px; padding: 35px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 35px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 12px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: #666; }
        select, input[type="date"], input[type="number"] {
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        select:focus, input:focus { border-color: #0f3460; }

        .prix-preview {
            background: #f0f4ff;
            border: 1.5px solid #0f3460;
            border-radius: 8px;
            padding: 15px 20px;
            font-size: 15px;
            font-weight: 600;
            color: #0f3460;
            text-align: center;
            display: none;
        }

        .btn { padding: 14px 30px; background: #0f3460; color: white; border: none; border-radius: 8px; font-family: 'Montserrat', sans-serif; font-size: 13px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #16213e; }

        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #efe; color: #060; border: 1px solid #9d9; }
        .alert-error { background: #fee; color: #c00; border: 1px solid #fcc; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #0f3460; color: white; padding: 12px 15px; text-align: left; font-weight: 500; letter-spacing: 0.5px; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover td { background: #f9f9f9; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-attente { background: #fff3cd; color: #856404; }
        .badge-confirmee { background: #d1e7dd; color: #0a3622; }
        .badge-annulee { background: #f8d7da; color: #842029; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: 1; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <img src="https://slelguoygbfzlpylpxfs.supabase.co/storage/v1/object/public/test-clones/bacaa8ed-efd0-432f-a0ac-5a712ea986ef-seabelhotels-com/assets/images/seabel_hotels_logo-11.svg" alt="Seabel">
        <div class="topbar-right">
            <span>Bonjour, <?= htmlspecialchars($_SESSION['prenom']) ?></span>
            <a href="../../index.html">← Site</a>
            <a href="../login/logout.php">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <h2>Nouvelle réservation</h2>

        <div class="card">
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" id="reservForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Hôtel</label>
                        <select name="hotel" id="hotel" required>
                            <option value="">-- Choisir un hôtel --</option>
                            <option>Seabel Rym Beach</option>
                            <option>Seabel Aladin</option>
                            <option>Seabel Alhambra</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type de chambre</label>
                        <select name="chambre" id="chambre" required>
                            <option value="">-- Choisir d'abord un hôtel --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date d'arrivée</label>
                        <input type="date" name="date_arrivee" id="date_arrivee" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date de départ</label>
                        <input type="date" name="date_depart" id="date_depart" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Nombre de personnes</label>
                        <input type="number" name="nb_personnes" id="nb_personnes" min="1" max="6" value="1">
                    </div>
                    <div class="form-group" style="justify-content: flex-end;">
                        <div class="prix-preview" id="prixPreview">Estimation : --</div>
                    </div>
                    <div class="form-group full" style="align-items: flex-start;">
                        <button type="submit" class="btn">Confirmer la réservation</button>
                    </div>
                </div>
            </form>
        </div>

        <h2>Mes réservations</h2>
        <div class="card">
            <?php if (empty($mes_reservations)): ?>
                <p style="color:#999; text-align:center; padding: 20px;">Aucune réservation pour le moment.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
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
                    <?php foreach ($mes_reservations as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['hotel']) ?></td>
                        <td><?= htmlspecialchars($r['chambre']) ?></td>
                        <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
                        <td><?= $r['nb_personnes'] ?></td>
                        <td><?= number_format($r['prix_total'], 0) ?> €</td>
                        <td>
                            <span class="badge badge-<?= $r['statut'] ?>">
                                <?= str_replace('_', ' ', $r['statut']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const tarifs = <?= $tarifs_json ?>;

        const hotelSel   = document.getElementById('hotel');
        const chambreSel = document.getElementById('chambre');
        const arriveeIn  = document.getElementById('date_arrivee');
        const departIn   = document.getElementById('date_depart');
        const preview    = document.getElementById('prixPreview');

        hotelSel.addEventListener('change', () => {
            const hotel = hotelSel.value;
            chambreSel.innerHTML = '<option value="">-- Choisir une chambre --</option>';
            if (tarifs[hotel]) {
                Object.keys(tarifs[hotel]).forEach(ch => {
                    chambreSel.innerHTML += `<option value="${ch}">${ch} — ${tarifs[hotel][ch]} €/nuit</option>`;
                });
            }
            updatePreview();
        });

        [chambreSel, arriveeIn, departIn].forEach(el => el.addEventListener('change', updatePreview));

        arriveeIn.addEventListener('change', () => {
            const next = new Date(arriveeIn.value);
            next.setDate(next.getDate() + 1);
            departIn.min = next.toISOString().split('T')[0];
            if (departIn.value && departIn.value <= arriveeIn.value) {
                departIn.value = next.toISOString().split('T')[0];
            }
            updatePreview();
        });

        function updatePreview() {
            const hotel   = hotelSel.value;
            const chambre = chambreSel.value;
            const arr     = arriveeIn.value;
            const dep     = departIn.value;

            if (hotel && chambre && arr && dep && dep > arr) {
                const nuits = Math.round((new Date(dep) - new Date(arr)) / 86400000);
                const total = tarifs[hotel][chambre] * nuits;
                preview.style.display = 'block';
                preview.textContent   = `Estimation : ${total} € (${nuits} nuit${nuits > 1 ? 's' : ''})`;
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
