
<!-- templates/admin/coupons.php -->
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Coupons', 'vandel-booking'); ?></h1>
    <a href="#" class="page-title-action vb-add-coupon"><?php _e('Add New', 'vandel-booking'); ?></a>

    <div id="vb-coupon-form" style="display: none;" class="vb-modal">
        <div class="vb-modal-content">
            <h2><?php _e('Add New Coupon', 'vandel-booking'); ?></h2>
            <form id="vb-new-coupon-form">
                <p>
                    <label><?php _e('Coupon Code', 'vandel-booking'); ?></label>
                    <input type="text" name="code" required>
                </p>

                <p>
                    <label><?php _e('Discount Type', 'vandel-booking'); ?></label>
                    <select name="discount_type" required>
                        <option value="percentage"><?php _e('Percentage', 'vandel-booking'); ?></option>
                        <option value="fixed"><?php _e('Fixed Amount', 'vandel-booking'); ?></option>
                    </select>
                </p>

                <p>
                    <label><?php _e('Discount Amount', 'vandel-booking'); ?></label>
                    <input type="number" name="discount_amount" step="0.01" required>
                </p>

                <p>
                    <label><?php _e('Start Date', 'vandel-booking'); ?></label>
                    <input type="date" name="start_date">
                </p>

                <p>
                    <label><?php _e('End Date', 'vandel-booking'); ?></label>
                    <input type="date" name="end_date">
                </p>

                <p>
                    <label><?php _e('Usage Limit', 'vandel-booking'); ?></label>
                    <input type="number" name="usage_limit">
                </p>

                <p>
                    <label><?php _e('Applicable Services', 'vandel-booking'); ?></label>
                    <select name="services[]" multiple required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo esc_attr($service->ID); ?>">
                                <?php echo esc_html($service->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Coupon', 'vandel-booking'); ?>">
                    <button type="button" class="button vb-modal-close"><?php _e('Cancel', 'vandel-booking'); ?></button>
                </p>
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('Code', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Discount', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Services', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Usage / Limit', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Valid From', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Valid Until', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Status', 'vandel-booking'); ?></th>
                <th scope="col"><?php _e('Actions', 'vandel-booking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($coupons as $coupon): ?>
                <tr>
                    <td><?php echo esc_html($coupon->code); ?></td>
                    <td>
                        <?php
                        if ($coupon->discount_type === 'percentage') {
                            echo esc_html($coupon->discount_amount) . '%';
                        } else {
                            echo esc_html(number_format($coupon->discount_amount, 2));
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $service_names = array();
                        $coupon_services = $wpdb->get_col($wpdb->prepare(
                            "SELECT service_id FROM {$wpdb->prefix}vb_coupon_services WHERE coupon_id = %d",
                            $coupon->id
                        ));
                        foreach ($services as $service) {
                            if (in_array($service->ID, $coupon_services)) {
                                $service_names[] = $service->post_title;
                            }
                        }
                        echo esc_html(implode(', ', $service_names));
                        ?>
                    </td>
                    <td>
                        <?php
                        echo esc_html($coupon->usage_count);
                        if ($coupon->usage_limit) {
                            echo ' / ' . esc_html($coupon->usage_limit);
                        } else {
                            echo ' / ∞';
                        }
                        ?>
                    </td>
                    <td><?php echo $coupon->start_date ? esc_html(date_i18n(get_option('date_format'), strtotime($coupon->start_date))) : '—'; ?></td>
                    <td><?php echo $coupon->end_date ? esc_html(date_i18n(get_option('date_format'), strtotime($coupon->end_date))) : '—'; ?></td>
                    <td>
                        <span class="vb-status vb-status-<?php echo esc_attr($coupon->status); ?>">
                            <?php echo esc_html(ucfirst($coupon->status)); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="button vb-toggle-coupon" data-coupon-id="<?php echo esc_attr($coupon->id); ?>" data-status="<?php echo esc_attr($coupon->status); ?>">
                            <?php echo $coupon->status === 'active' ? esc_html__('Deactivate', 'vandel-booking') : esc_html__('Activate', 'vandel-booking'); ?>
                        </button>
                        <button type="button" class="button vb-delete-coupon" data-coupon-id="<?php echo esc_attr($coupon->id); ?>">
                            <?php _e('Delete', 'vandel-booking'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
