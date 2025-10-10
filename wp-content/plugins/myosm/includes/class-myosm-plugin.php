<?php

if (!defined('ABSPATH')) {
    exit;
}

class MyOsm_Plugin
{
    private static $instance = null;
    private $table_name;

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'myosm';

        register_activation_hook(MYOSM_PLUGIN_FILE, [$this, 'activate']);
        register_uninstall_hook(MYOSM_PLUGIN_FILE, ['MyOsm_Plugin', 'uninstall']);

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_public_assets']);
    }

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function activate()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            created_by BIGINT(20) UNSIGNED NULL,
            updated_by BIGINT(20) UNSIGNED NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function uninstall()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'myosm';

        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }

    public function register_admin_menu()
    {
        add_menu_page(
            __('MyOsm', 'myosm'),
            __('MyOsm', 'myosm'),
            'manage_options',
            'myosm',
            [$this, 'render_admin_list'],
            'dashicons-location-alt'
        );

        add_submenu_page(
            'myosm',
            __('Centres d\'intérêt', 'myosm'),
            __('Centres d\'intérêt', 'myosm'),
            'manage_options',
            'myosm',
            [$this, 'render_admin_list']
        );

        add_submenu_page(
            'myosm',
            __('Ajouter un centre', 'myosm'),
            __('Ajouter', 'myosm'),
            'manage_options',
            'myosm-add',
            [$this, 'render_admin_add']
        );

        add_submenu_page(
            null,
            __('Modifier', 'myosm'),
            __('Modifier', 'myosm'),
            'manage_options',
            'myosm-edit',
            [$this, 'render_admin_edit']
        );
    }

    public function enqueue_admin_assets($hook)
    {
        if (!isset($_GET['page']) || strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'myosm') === false) {
            return;
        }

        wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

        wp_register_script(
            'myosm-admin',
            plugins_url('assets/js/admin.js', MYOSM_PLUGIN_FILE),
            ['leaflet'],
            '1.0.0',
            true
        );

        wp_enqueue_script('myosm-admin');
    }

    public function register_public_assets()
    {
        wp_register_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);

        wp_register_script(
            'myosm-frontend',
            plugins_url('assets/js/frontend.js', MYOSM_PLUGIN_FILE),
            ['leaflet'],
            '1.0.0',
            true
        );
    }

    public function register_shortcode()
    {
        add_shortcode('myosm', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'myosm');

        $id = intval($atts['id']);

        if ($id <= 0) {
            return '<p>' . esc_html__('Centre d\'intérêt introuvable.', 'myosm') . '</p>';
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));

        if (!$item) {
            return '<p>' . esc_html__('Centre d\'intérêt introuvable.', 'myosm') . '</p>';
        }

        wp_enqueue_style('leaflet');
        wp_enqueue_script('leaflet');
        wp_enqueue_script('myosm-frontend');

        $map_id = 'myosm-map-' . $item->id . '-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="myosm-map" id="<?php echo esc_attr($map_id); ?>"
            data-lat="<?php echo esc_attr($item->latitude); ?>"
            data-lng="<?php echo esc_attr($item->longitude); ?>"
            data-name="<?php echo esc_attr($item->name); ?>"
            style="width: 100%; height: 400px;"></div>
        <?php
        return ob_get_clean();
    }

    private function render_errors($errors)
    {
        if (empty($errors)) {
            return;
        }

        echo '<div class="notice notice-error"><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }

    private function format_user_name($user_id)
    {
        if (empty($user_id)) {
            return __('Inconnu', 'myosm');
        }

        $user = get_userdata($user_id);

        if (!$user) {
            return __('Inconnu', 'myosm');
        }

        return $user->display_name ? $user->display_name : $user->user_login;
    }

    private function validate_form($data)
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = __('Le nom est obligatoire.', 'myosm');
        }

        if ($data['latitude'] === '' || !is_numeric($data['latitude'])) {
            $errors[] = __('La latitude doit être un nombre.', 'myosm');
        } elseif ($data['latitude'] < -90 || $data['latitude'] > 90) {
            $errors[] = __('La latitude doit être comprise entre -90 et 90.', 'myosm');
        }

        if ($data['longitude'] === '' || !is_numeric($data['longitude'])) {
            $errors[] = __('La longitude doit être un nombre.', 'myosm');
        } elseif ($data['longitude'] < -180 || $data['longitude'] > 180) {
            $errors[] = __('La longitude doit être comprise entre -180 et 180.', 'myosm');
        }

        return $errors;
    }

    private function sanitize_form($input)
    {
        return [
            'name' => isset($input['name']) ? sanitize_text_field($input['name']) : '',
            'latitude' => isset($input['latitude']) ? sanitize_text_field($input['latitude']) : '',
            'longitude' => isset($input['longitude']) ? sanitize_text_field($input['longitude']) : '',
        ];
    }

    public function render_admin_list()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Centres d\'intérêt', 'myosm'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=myosm-add')); ?>" class="page-title-action"><?php esc_html_e('Ajouter', 'myosm'); ?></a>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nom', 'myosm'); ?></th>
                        <th><?php esc_html_e('Latitude', 'myosm'); ?></th>
                        <th><?php esc_html_e('Longitude', 'myosm'); ?></th>
                        <th><?php esc_html_e('Date d\'ajout', 'myosm'); ?></th>
                        <th><?php esc_html_e('Ajouté par', 'myosm'); ?></th>
                        <th><?php esc_html_e('Dernière modification', 'myosm'); ?></th>
                        <th><?php esc_html_e('Modifié par', 'myosm'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items) : ?>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url(admin_url('admin.php?page=myosm-edit&id=' . $item->id)); ?>"><?php echo esc_html($item->name); ?></a></td>
                                <td><?php echo esc_html($item->latitude); ?></td>
                                <td><?php echo esc_html($item->longitude); ?></td>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item->created_at)); ?></td>
                                <td><?php echo esc_html($this->format_user_name($item->created_by)); ?></td>
                                <td><?php echo $item->updated_at ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item->updated_at)) : __('Jamais', 'myosm'); ?></td>
                                <td><?php echo $item->updated_by ? esc_html($this->format_user_name($item->updated_by)) : __('N/A', 'myosm'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('Aucun centre d\'intérêt enregistré.', 'myosm'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_admin_add()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $data = ['name' => '', 'latitude' => '', 'longitude' => ''];
        $errors = [];
        $created_id = isset($_GET['created']) ? intval($_GET['created']) : 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('myosm_add_center');
            $data = $this->sanitize_form($_POST);
            $errors = $this->validate_form($data);

            if (empty($errors)) {
                global $wpdb;
                $user_id = get_current_user_id();
                $result = $wpdb->insert(
                    $this->table_name,
                    [
                        'name' => $data['name'],
                        'latitude' => $data['latitude'],
                        'longitude' => $data['longitude'],
                        'created_at' => current_time('mysql'),
                        'created_by' => $user_id,
                    ],
                    ['%s', '%f', '%f', '%s', '%d']
                );

                if ($result !== false) {
                    $created_id = $wpdb->insert_id;
                    $url = add_query_arg([
                        'page' => 'myosm-add',
                        'created' => $created_id,
                    ], admin_url('admin.php'));
                    wp_safe_redirect($url);
                    exit;
                } else {
                    $errors[] = __('Une erreur est survenue lors de l\'enregistrement.', 'myosm');
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Ajouter un centre d\'intérêt', 'myosm'); ?></h1>
            <?php if ($created_id && empty($errors) && $_SERVER['REQUEST_METHOD'] !== 'POST') : ?>
                <div class="notice notice-success"><p><?php esc_html_e('Centre enregistré. Vous pouvez utiliser le shortcode ci-dessous.', 'myosm'); ?></p></div>
            <?php endif; ?>
            <?php $this->render_errors($errors); ?>
            <form method="post">
                <?php wp_nonce_field('myosm_add_center'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="myosm-name"><?php esc_html_e('Nom', 'myosm'); ?></label></th>
                            <td><input name="name" type="text" id="myosm-name" value="<?php echo esc_attr($data['name']); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myosm-latitude"><?php esc_html_e('Latitude', 'myosm'); ?></label></th>
                            <td><input name="latitude" type="text" id="myosm-latitude" value="<?php echo esc_attr($data['latitude']); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myosm-longitude"><?php esc_html_e('Longitude', 'myosm'); ?></label></th>
                            <td><input name="longitude" type="text" id="myosm-longitude" value="<?php echo esc_attr($data['longitude']); ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Shortcode', 'myosm'); ?></th>
                            <td><input type="text" class="regular-text" value="<?php echo $created_id ? esc_attr('[myosm id="' . $created_id . '"]') : esc_attr__('Disponible après enregistrement.', 'myosm'); ?>" readonly></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Enregistrer', 'myosm')); ?>
            </form>
        </div>
        <?php
    }

    public function render_admin_edit()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Identifiant invalide.', 'myosm') . '</p></div>';
            return;
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));

        if (!$item) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Centre d\'intérêt introuvable.', 'myosm') . '</p></div>';
            return;
        }

        $data = ['name' => $item->name, 'latitude' => $item->latitude, 'longitude' => $item->longitude];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['myosm_action']) && $_POST['myosm_action'] === 'delete') {
                check_admin_referer('myosm_delete_center');
                $wpdb->delete($this->table_name, ['id' => $id], ['%d']);
                wp_safe_redirect(admin_url('admin.php?page=myosm'));
                exit;
            } else {
                check_admin_referer('myosm_edit_center');
                $data = $this->sanitize_form($_POST);
                $errors = $this->validate_form($data);

                if (empty($errors)) {
                    $updated = $wpdb->update(
                        $this->table_name,
                        [
                            'name' => $data['name'],
                            'latitude' => $data['latitude'],
                            'longitude' => $data['longitude'],
                            'updated_at' => current_time('mysql'),
                            'updated_by' => get_current_user_id(),
                        ],
                        ['id' => $id],
                        ['%s', '%f', '%f', '%s', '%d'],
                        ['%d']
                    );

                    if ($updated !== false) {
                        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
                        $data = ['name' => $item->name, 'latitude' => $item->latitude, 'longitude' => $item->longitude];
                        echo '<div class="notice notice-success"><p>' . esc_html__('Centre mis à jour.', 'myosm') . '</p></div>';
                    } else {
                        $errors[] = __('Une erreur est survenue lors de la mise à jour.', 'myosm');
                    }
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Modifier le centre d\'intérêt', 'myosm'); ?></h1>
            <?php $this->render_errors($errors); ?>
            <div style="display:flex; gap:20px; align-items:flex-start;">
                <form method="post" style="flex:1;">
                    <?php wp_nonce_field('myosm_edit_center'); ?>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="myosm-name"><?php esc_html_e('Nom', 'myosm'); ?></label></th>
                                <td><input name="name" type="text" id="myosm-name" value="<?php echo esc_attr($data['name']); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="myosm-latitude"><?php esc_html_e('Latitude', 'myosm'); ?></label></th>
                                <td><input name="latitude" type="text" id="myosm-latitude" value="<?php echo esc_attr($data['latitude']); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="myosm-longitude"><?php esc_html_e('Longitude', 'myosm'); ?></label></th>
                                <td><input name="longitude" type="text" id="myosm-longitude" value="<?php echo esc_attr($data['longitude']); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Shortcode', 'myosm'); ?></th>
                                <td><input type="text" class="regular-text" value="<?php echo esc_attr('[myosm id="' . $item->id . '"]'); ?>" readonly></td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="myosm_action" value="save">
                    <?php submit_button(__('Mettre à jour', 'myosm')); ?>
                </form>
                <div style="flex:1;">
                    <div id="myosm-admin-map" data-lat="<?php echo esc_attr($item->latitude); ?>" data-lng="<?php echo esc_attr($item->longitude); ?>" data-name="<?php echo esc_attr($item->name); ?>" style="height:400px;"></div>
                    <div class="card" style="margin-top:20px;">
                        <p><strong><?php esc_html_e('Ajouté le', 'myosm'); ?> :</strong> <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item->created_at)); ?></p>
                        <p><strong><?php esc_html_e('Ajouté par', 'myosm'); ?> :</strong> <?php echo esc_html($this->format_user_name($item->created_by)); ?></p>
                        <p><strong><?php esc_html_e('Dernière modification', 'myosm'); ?> :</strong> <?php echo $item->updated_at ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item->updated_at)) : __('Jamais', 'myosm'); ?></p>
                        <p><strong><?php esc_html_e('Modifié par', 'myosm'); ?> :</strong> <?php echo $item->updated_by ? esc_html($this->format_user_name($item->updated_by)) : __('N/A', 'myosm'); ?></p>
                    </div>
                    <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Êtes-vous sûr de vouloir supprimer ?', 'myosm')); ?>');" style="margin-top:20px;">
                        <?php wp_nonce_field('myosm_delete_center'); ?>
                        <input type="hidden" name="myosm_action" value="delete">
                        <?php submit_button(__('Supprimer', 'myosm'), 'delete'); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
