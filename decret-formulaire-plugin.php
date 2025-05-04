<?php
/*
Plugin Name: Formulaire Décret + Services Multiples
Description: Ajoute un formulaire dans le tableau de bord pour publier des décrets, les modifier, les supprimer, et notifier plusieurs services.
Version: 2.2
Author: Torche bilel
*/

// 1. Type de contenu Décret + Service
add_action('init', function () {
    register_post_type('decret', [
        'label' => 'Décrets',
        'public' => true,
        'show_in_menu' => false,
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);

    register_post_type('service', [
        'label' => 'Services',
        'public' => false,
        'show_ui' => true,
        'menu_position' => 26,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-groups',
    ]);
});

// 2. Email des services
add_action('add_meta_boxes', function () {
    add_meta_box('email_service', 'Email du Service', function ($post) {
        $email = get_post_meta($post->ID, 'email_service', true);
        echo '<input type="email" name="email_service" value="' . esc_attr($email) . '" class="widefat">';
    }, 'service', 'normal');
});

add_action('save_post_service', function ($post_id) {
    if (isset($_POST['email_service'])) {
        update_post_meta($post_id, 'email_service', sanitize_email($_POST['email_service']));
    }
});

// 3. Menu admin
add_action('admin_menu', function () {
    add_menu_page('Ajouter Décret', 'Ajouter Décret', 'edit_posts', 'ajouter-decret', 'formulaire_decret_page', 'dashicons-media-document', 25);
});

// 4. Formulaire décret + liste
function formulaire_decret_page()
{
    $categories = [
        'Journal Officiel', 'Ministère de l’Intérieur', 'Présidence de la République',
        'Ministère de la Justice', 'Assemblée Nationale', 'Conseil des Ministres',
        'Légal doctrine', 'Autre'
    ];

    $services = get_posts(['post_type' => 'service', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);

    // MODIFIER / SUPPRIMER
    if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
        wp_delete_post((int) $_GET['id'], true);
        echo '<div class="notice notice-success"><p>Décret supprimé.</p></div>';
    }

    $decret_en_edition = null;
    if (isset($_GET['action']) && $_GET['action'] === 'modifier' && isset($_GET['id'])) {
        $decret_en_edition = get_post((int) $_GET['id']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('ajouter_decret', 'decret_nonce')) {
        $id_modif = isset($_POST['decret_id']) ? (int) $_POST['decret_id'] : 0;

        $titre = sanitize_text_field($_POST['titre']);
        $reference = sanitize_text_field($_POST['reference']);
        $categorie = sanitize_text_field($_POST['categorie']);
        $date_publication = sanitize_text_field($_POST['date_publication']);
        $resume = sanitize_textarea_field($_POST['resume']);
        $lien = esc_url_raw($_POST['lien_decret']);
        $service_ids = array_map('intval', $_POST['services'] ?? []);

        $fichier_url = get_post_meta($id_modif, 'fichier_joint', true);
        if (!empty($_FILES['fichier_joint']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['fichier_joint'], ['test_form' => false]);
            if (!isset($upload['error'])) {
                $fichier_url = $upload['url'];
            }
        }

        $post_args = [
            'post_type' => 'decret',
            'post_title' => $titre,
            'post_content' => $resume,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];

        if ($id_modif) {
            $post_args['ID'] = $id_modif;
        }

        $post_id = wp_insert_post($post_args);

        if ($post_id) {
            update_post_meta($post_id, 'reference', $reference);
            update_post_meta($post_id, 'categorie', $categorie);
            update_post_meta($post_id, 'date_publication', $date_publication);
            update_post_meta($post_id, 'fichier_joint', $fichier_url);
            update_post_meta($post_id, 'lien_decret', $lien);
            update_post_meta($post_id, 'services_ids', $service_ids);

            if (!$id_modif) {
                $url_decret = get_permalink($post_id);
                $message = "Un nouvel article (décret) a été publié. Vous pouvez le consulter ici : $url_decret";
                $subject = "Nouveau décret publié";

                foreach ($service_ids as $sid) {
                    $email = get_post_meta($sid, 'email_service', true);
                    if (is_email($email)) {
                        wp_mail($email, $subject, $message);
                    }
                }
            }

            echo '<div class="notice notice-success"><p>Décret enregistré avec succès.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de l\'enregistrement.</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?= $decret_en_edition ? 'Modifier un Décret' : 'Ajouter un Décret' ?></h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('ajouter_decret', 'decret_nonce'); ?>
            <?php if ($decret_en_edition): ?><input type="hidden" name="decret_id" value="<?= esc_attr($decret_en_edition->ID) ?>"><?php endif; ?>
            <table class="form-table">
                <tr><th><label>Titre</label></th><td><input type="text" name="titre" class="regular-text" value="<?= esc_attr($decret_en_edition->post_title ?? '') ?>" required></td></tr>
                <tr><th><label>Référence</label></th><td><input type="text" name="reference" class="regular-text" value="<?= esc_attr(get_post_meta($decret_en_edition->ID ?? 0, 'reference', true)) ?>" required></td></tr>
                <tr><th><label>Catégorie</label></th><td><select name="categorie" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= esc_attr($cat) ?>" <?= selected($cat, get_post_meta($decret_en_edition->ID ?? 0, 'categorie', true), false) ?>><?= esc_html($cat) ?></option>
                    <?php endforeach; ?>
                </select></td></tr>
                <tr><th><label>Date de publication</label></th><td><input type="date" name="date_publication" value="<?= esc_attr(get_post_meta($decret_en_edition->ID ?? 0, 'date_publication', true)) ?>" required></td></tr>
                <tr><th><label>Fichier joint</label></th><td><input type="file" name="fichier_joint" accept=".pdf">
                <?php if ($url = get_post_meta($decret_en_edition->ID ?? 0, 'fichier_joint', true)): ?><br><a href="<?= esc_url($url) ?>" target="_blank">Fichier existant</a><?php endif; ?></td></tr>
                <tr><th><label>Résumé</label></th><td><textarea name="resume" rows="5" class="large-text" required><?= esc_textarea($decret_en_edition->post_content ?? '') ?></textarea></td></tr>
                <tr><th><label>Lien vers le décret</label></th><td><input type="url" name="lien_decret" class="regular-text" value="<?= esc_url(get_post_meta($decret_en_edition->ID ?? 0, 'lien_decret', true)) ?>"></td></tr>
                <tr><th><label>Services concernés</label></th><td>
                    <select name="services[]" multiple size="5" required>
                        <?php
                        $selected_services = get_post_meta($decret_en_edition->ID ?? 0, 'services_ids', true) ?: [];
                        foreach ($services as $service): ?>
                            <option value="<?= esc_attr($service->ID) ?>" <?= in_array($service->ID, $selected_services) ? 'selected' : '' ?>><?= esc_html($service->post_title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
            </table>
            <p><input type="submit" class="button button-primary" value="<?= $decret_en_edition ? 'Mettre à jour' : 'Enregistrer le décret' ?>"></p>
        </form>

        <h2>Liste des décrets existants</h2>
        <table class="widefat">
            <thead><tr><th>Titre</th><th>Catégorie</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach (get_posts(['post_type' => 'decret', 'numberposts' => -1]) as $decret): ?>
                <tr>
                    <td><?= esc_html($decret->post_title) ?></td>
                    <td><?= esc_html(get_post_meta($decret->ID, 'categorie', true)) ?></td>
                    <td><?= esc_html(get_post_meta($decret->ID, 'date_publication', true)) ?></td>
                    <td>
                        <a href="<?= admin_url('admin.php?page=ajouter-decret&action=modifier&id=' . $decret->ID) ?>" class="button">Modifier</a>
                        <a href="<?= admin_url('admin.php?page=ajouter-decret&action=supprimer&id=' . $decret->ID) ?>" class="button delete" onclick="return confirm('Supprimer ce décret ?');">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}