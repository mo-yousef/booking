<!-- templates/admin/bookings.php -->
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Bookings', 'vandel-booking'); ?></h1>
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=vb_export_bookings'), 'vb_export_bookings')); ?>" class="page-title-action">
        <?php _e('Export CSV', 'vandel-booking'); ?>
    </a>

    <form method="get">
        <input type="hidden" name="page" value="vandel-booking-bookings">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                    <option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>>
                        <?php _e('Pending', 'vandel-booking'); ?>
                    </option>
                    <option value="confirmed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'confirmed'); ?>>
                        <?php _e('Confirmed', 'vandel-booking'); ?>
                    </option>
                    <option value="completed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'completed'); ?>>
                        <?php _e('Completed', 'vandel-booking'); ?>
                    </option>
                    <option value="cancelled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'cancelled'); ?>>
                        <?php _e('Cancelled', 'vandel-booking'); ?>
                    </option>
                </select>

                <select name="service">
                    <option value=""><?php _e('All Services', 'vandel-booking'); ?></option>
                    <?php
                    $services = get_posts(array('post_type' => 'vb_service', 'posts_per_page' => -1));
                    foreach ($services as $service) {
                        echo sprintf(
                            '<option value="%d" %s>%s</option>',
                            $service->ID,
                            selected(isset($_GET['service']) ? $_GET['service'] : '', $service->ID, false),
                            esc_html($service->post_title)
                        );
                    }
                    ?>
                </select>

                <input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>" placeholder="<?php esc_attr_e('From Date', 'vandel-booking'); ?>">
                <input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>" placeholder="<?php esc_attr_e('To Date', 'vandel-booking'); ?>">

                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'vandel-booking'); ?>">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox" />
                    </td>
                    <th scope="col"><?php _e('ID', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('Service', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('Customer', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('Date', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('Status', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('Amount', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                    <th scope="col"><?php _e('Actions', 'vandel-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings['items'] as $booking): ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="booking[]" value="<?php echo esc_attr($booking->id); ?>" />
                        </th>
                        <td><?php echo esc_html($booking->id); ?></td>
                        <td><?php echo esc_html($booking->service_name); ?></td>
                        <td><?php echo esc_html($booking->customer_name); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date))); ?></td>
                        <td>
                            <select class="vb-status-select" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                                <?php
                                $statuses = array('pending', 'confirmed', 'completed', 'cancelled');
                                foreach ($statuses as $status) {
                                    echo sprintf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($status),
                                        selected($booking->status, $status, false),
                                        esc_html(ucfirst($status))
                                    );
                                }
                                ?>
                            </select>
                        </td>
                        <td><?php echo esc_html(number_format($booking->total_amount, 2)); ?></td>
                        <td><?php echo esc_html($booking->zip_code); ?></td>
                        <td>
                            <a href="#" class="vb-view-booking" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                                <?php _e('View', 'vandel-booking'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                    <option value="delete"><?php _e('Delete', 'vandel-booking'); ?></option>
                    <option value="complete"><?php _e('Mark as Completed', 'vandel-booking'); ?></option>
                    <option value="cancel"><?php _e('Mark as Cancelled', 'vandel-booking'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'vandel-booking'); ?>">
            </div>

            <div class="tablenav-pages">
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => ceil($bookings['total_items'] / $bookings['items_per_page']),
                    'current' => $bookings['current_page']
                ));

                if ($page_links) {
                    echo '<span class="pagination-links">' . $page_links . '</span>';
                }
                ?>
            </div>
        </div>
    </form>
</div>
