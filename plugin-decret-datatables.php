<?php
/*
Plugin Name: Affichage Décrets Front (DataTables)
Description: Affiche les décrets en page d'accueil avec DataTables via le shortcode [liste_decrets].
Version: 1.0
Author: Torche Bilel
*/

// Enqueue DataTables + Bootstrap style
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('datatables-bootstrap-css', 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css');
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    
    wp_enqueue_script('jquery');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', ['jquery'], null, true);
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
    wp_enqueue_script('datatables-bootstrap-js', 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js', ['datatables-js'], null, true);

    wp_add_inline_script('datatables-bootstrap-js', "
        jQuery(document).ready(function($) {
            $('.decret-table').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                }
            });
        });
    ");
});

// Shortcode pour afficher les décrets
add_shortcode('liste_decrets', function () {
    $decrets = get_posts(['post_type' => 'decret', 'numberposts' => -1]);

    $service_map = [];
    $services = get_posts(['post_type' => 'service', 'numberposts' => -1]);
    foreach ($services as $s) {
        $service_map[$s->ID] = $s->post_title;
    }

    $grouped = [];

    foreach ($decrets as $decret) {
        $service_ids = get_post_meta($decret->ID, 'services_ids', true) ?: [];

        foreach ($service_ids as $sid) {
            $grouped[$sid][] = $decret;
        }
    }

    ob_start(); ?>

    <style>
        .decret-section { margin-bottom: 50px; }
        .decret-section h2 {
            border-left: 5px solid #0d6efd;
            padding-left: 10px;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        .table thead th {
            background-color: #f8f9fa;
        }
    </style>

    <div class="container">
        <?php foreach ($grouped as $service_id => $decrets_service): ?>
            <div class="decret-section">
                <h2><?= esc_html($service_map[$service_id] ?? 'Service inconnu') ?></h2>
                <table class="table table-bordered table-striped decret-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Référence</th>
                            <th>Catégorie</th>
                            <th>Date</th>
                            <th>Résumé</th>
                            <th>Lien</th>
                            <th>Fichier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($decrets_service as $decret): ?>
                            <tr>
                                <td><?= esc_html($decret->post_title) ?></td>
                                <td><?= esc_html(get_post_meta($decret->ID, 'reference', true)) ?></td>
                                <td><?= esc_html(get_post_meta($decret->ID, 'categorie', true)) ?></td>
                                <td><?= esc_html(get_post_meta($decret->ID, 'date_publication', true)) ?></td>
                                <td><?= wp_trim_words($decret->post_content, 20) ?></td>
                                <td><a href="<?= esc_url(get_post_meta($decret->ID, 'lien_decret', true)) ?>" target="_blank">Voir</a></td>
                                <td>
                                    <?php if ($url = get_post_meta($decret->ID, 'fichier_joint', true)): ?>
                                        <a href="<?= esc_url($url) ?>" target="_blank">Télécharger</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <?php return ob_get_clean();
});
