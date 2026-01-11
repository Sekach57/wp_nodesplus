<?php
$node_name = $args['node_name'] ?? __('Project', 'incrypted');
$node_id = $args['node_id'] ?? '';
$unique_key = $args['unique_key'] ?? '';
$product_id = $args['product_id'] ?? '';
$price_html = $args['price_html'] ?? '';
$created_at = $args['created_at'] ?? '--';
$due_date = $args['due_date'] ?? '--';
$status_label = __('Active', 'incrypted');
$status_class = 'node-card--status-active';
$due_date_obj = null;
if ($due_date !== '' && $due_date !== '--') {
    $due_date_obj = DateTimeImmutable::createFromFormat('d.m.Y', $due_date, wp_timezone());
}
if ($due_date_obj instanceof DateTimeImmutable) {
    $now = new DateTimeImmutable('now', wp_timezone());
    $end_of_day = $due_date_obj->setTime(23, 59, 59);
    $start_of_day = $due_date_obj->setTime(0, 0, 0);
    if ( $start_of_day <= $now ) {
        $status_label = __('Overdue', 'incrypted');
        $status_class = 'node-card--status-overdue';
    }
}
$checkbox_label = sprintf(__('Select node %s', 'incrypted'), $node_name);
$help_label = sprintf(__('Help about %s', 'incrypted'), $node_name);
$node_id_attr = $node_id !== '' ? ' data-node-id="' . esc_attr($node_id) . '"' : '';
$product_id_attr = $product_id !== '' ? ' data-product-id="' . esc_attr($product_id) . '"' : '';
$extra_id = 'node-extra-' . preg_replace('/[^a-zA-Z0-9\-_]/', '', $unique_key);
$extra_text = $node_id !== '' ? sprintf(__('Node ID: %s', 'incrypted'), $node_id) : __('Node ID: --', 'incrypted');
?>

<label class="node-card <?php echo esc_attr($status_class); ?>"<?php echo $node_id_attr; ?><?php echo $product_id_attr; ?>>
    <input
        type="checkbox"
        class="node-card__checkbox"
        name="renewal_products[]"
        value="<?php echo esc_attr($unique_key); ?>"
        data-product-id="<?php echo esc_attr($product_id); ?>"
        data-node="<?php echo esc_attr($node_id); ?>"
        aria-label="<?php echo esc_attr($checkbox_label); ?>"
    />

    <div class="node-card__header">
        <div class="node-card__title">
            <span class="node-card__project"><?php echo esc_html($node_name); ?></span>
            <button
                type="button"
                class="node-card__help"
                aria-label="<?php echo esc_attr($help_label); ?>"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($extra_id); ?>"
            >?</button>
        </div>
        <span class="node-card__status"><?php echo esc_html($status_label); ?></span>
    </div>

    <div class="node-card__body">
        <div class="node-card__meta node-meta">
            <div class="node-card__meta-row node-meta__row">
                <span class="node-card__meta-left">
                    <svg class="node-card__icon" width="12" height="12" viewBox="0 0 20 20" aria-hidden="true" focusable="false" style="width:12px;height:12px;flex:0 0 12px;">
                        <path d="M4 7.5h5.5L15 13a2 2 0 0 1-2.8 2.8L4 7.5ZM11.5 7.5l2-2a2 2 0 0 1 2.8 2.8l-2 2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="node-card__meta-label"><?php echo esc_html__('Price', 'incrypted'); ?></span>
                </span>
                <span class="node-card__meta-value"><?php echo wp_kses_post($price_html); ?></span>
            </div>
            <div class="node-card__meta-row node-meta__row">
                <span class="node-card__meta-left">
                    <svg class="node-card__icon" width="12" height="12" viewBox="0 0 20 20" aria-hidden="true" focusable="false" style="width:12px;height:12px;flex:0 0 12px;">
                        <path d="M10 5v5l3 2M10 18a8 8 0 1 0-8-8 8 8 0 0 0 8 8Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="node-card__meta-label"><?php echo esc_html__('Created at', 'incrypted'); ?></span>
                </span>
                <span class="node-card__meta-value"><?php echo esc_html($created_at); ?></span>
            </div>
        </div>

        <div class="node-card__due-block">
            <span class="node-card__label"><?php echo esc_html__('Due date', 'incrypted'); ?></span>
            <span class="node-card__due">
                <svg class="node-card__icon" width="12" height="12" viewBox="0 0 20 20" aria-hidden="true" focusable="false" style="width:12px;height:12px;flex:0 0 12px;">
                    <path d="M6 2.5V5M14 2.5V5M3.5 8H16.5M4 4h12a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?php echo esc_html($due_date); ?></span>
            </span>
        </div>
    </div>

    <div class="node-card__extra" id="<?php echo esc_attr($extra_id); ?>" hidden>
        <?php echo esc_html($extra_text); ?>
    </div>
</label>
