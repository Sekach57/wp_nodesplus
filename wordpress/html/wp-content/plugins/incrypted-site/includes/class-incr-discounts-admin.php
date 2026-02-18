<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class INCR_Discounts_Admin {

    const OPTION_KEY = 'np_role_discounts';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_submenu' ] );
        add_action( 'admin_post_np_save_role_discounts', [ __CLASS__, 'handle_save' ] );
    }

    public static function add_submenu() {
        add_submenu_page(
            'incr-nodes-overview',
            'Знижки по ролях',
            'Знижки',
            'manage_options',
            'np-role-discounts',
            [ __CLASS__, 'render' ]
        );
    }

    /**
     * Get discounts config. Falls back to defaults on first run.
     * @return array  [['role'=>'team','label'=>'...','percent'=>40], ...]
     */
    public static function get_discounts() {
        $saved = get_option( self::OPTION_KEY, null );
        if ( $saved !== null ) {
            return (array) $saved;
        }
        // Default — mirrors old ACF / coupon config
        return [
            [ 'role' => 'team',              'label' => 'Знижка для команди', 'percent' => 40 ],
            [ 'role' => 'administrator',     'label' => 'Знижка для команди', 'percent' => 40 ],
            [ 'role' => 'customer_plus',     'label' => 'Знижка учасника',    'percent' => 10 ],
            [ 'role' => 'customer_plus_old', 'label' => 'Знижка учасника',    'percent' => 15 ],
        ];
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'np_save_role_discounts' );

        $roles    = array_values( $_POST['np_role']    ?? [] );
        $labels   = array_values( $_POST['np_label']   ?? [] );
        $percents = array_values( $_POST['np_percent'] ?? [] );

        $rows = [];
        foreach ( $roles as $i => $role ) {
            $role    = sanitize_key( $role );
            $label   = sanitize_text_field( $labels[ $i ] ?? '' );
            $percent = max( 0, min( 100, (int) ( $percents[ $i ] ?? 0 ) ) );
            if ( $role !== '' ) {
                $rows[] = [ 'role' => $role, 'label' => $label, 'percent' => $percent ];
            }
        }

        update_option( self::OPTION_KEY, $rows );
        wp_redirect( admin_url( 'admin.php?page=np-role-discounts&saved=1' ) );
        exit;
    }

    public static function render() {
        $discounts = self::get_discounts();
        $all_roles = wp_roles()->roles;
        ?>
        <div class="wrap">
            <h1>Знижки по ролях</h1>
            <p style="color:#666;max-width:600px;">
                Знижка застосовується автоматично як окремий рядок у кошику.
                Якщо у користувача кілька ролей — застосовується перша знайдена знижка.
            </p>

            <?php if ( ! empty( $_GET['saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>Збережено!</strong></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'np_save_role_discounts' ); ?>
                <input type="hidden" name="action" value="np_save_role_discounts">

                <table class="widefat striped" id="np-discounts-table" style="max-width:800px;margin-bottom:12px;">
                    <thead>
                        <tr>
                            <th style="width:30%">Роль WordPress</th>
                            <th style="width:40%">Назва знижки <small>(видна у кошику)</small></th>
                            <th style="width:15%">Знижка</th>
                            <th style="width:10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $discounts as $row ) : ?>
                        <tr>
                            <td>
                                <select name="np_role[]" style="width:100%">
                                    <?php foreach ( $all_roles as $slug => $data ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>"
                                            <?php selected( $row['role'], $slug ); ?>>
                                            <?php echo esc_html( $data['name'] ) . ' (' . esc_html( $slug ) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="np_label[]"
                                    value="<?php echo esc_attr( $row['label'] ); ?>"
                                    style="width:100%" placeholder="Назва у кошику">
                            </td>
                            <td>
                                <input type="number" name="np_percent[]"
                                    value="<?php echo (int) $row['percent']; ?>"
                                    min="0" max="100" style="width:70px"> %
                            </td>
                            <td style="text-align:center">
                                <button type="button" class="button np-remove-row"
                                    title="Видалити">&#x2715;</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="button" class="button" id="np-add-discount-row">+ Додати роль</button>
                <br><br>
                <?php submit_button( 'Зберегти', 'primary', 'submit', false ); ?>
            </form>
        </div>

        <script>
        (function() {
            var roles = <?php echo wp_json_encode( array_map( function( $slug, $data ) {
                return [ 'slug' => $slug, 'name' => $data['name'] ];
            }, array_keys( $all_roles ), array_values( $all_roles ) ) ); ?>;

            function makeOptions(selected) {
                return roles.map(function(r) {
                    var sel = r.slug === selected ? ' selected' : '';
                    return '<option value="' + r.slug + '"' + sel + '>' + r.name + ' (' + r.slug + ')</option>';
                }).join('');
            }

            document.getElementById('np-add-discount-row').addEventListener('click', function() {
                var tbody = document.querySelector('#np-discounts-table tbody');
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><select name="np_role[]" style="width:100%">' + makeOptions('') + '</select></td>' +
                    '<td><input type="text" name="np_label[]" value="Знижка" style="width:100%" placeholder="Назва у кошику"></td>' +
                    '<td><input type="number" name="np_percent[]" value="0" min="0" max="100" style="width:70px"> %</td>' +
                    '<td style="text-align:center"><button type="button" class="button np-remove-row" title="Видалити">&#x2715;</button></td>';
                tbody.appendChild(tr);
            });

            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('np-remove-row')) {
                    e.target.closest('tr').remove();
                }
            });
        })();
        </script>
        <?php
    }
}
