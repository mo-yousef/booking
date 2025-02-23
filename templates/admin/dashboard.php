<!-- templates/admin/dashboard.php -->
<div class="wrap">
    <h1><?php _e('Vandel Booking Dashboard', 'vandel-booking'); ?></h1>

    <div class="vb-dashboard-widgets">
        <div class="vb-widget">
            <h3><?php _e('Booking Statistics', 'vandel-booking'); ?></h3>
            <div class="vb-widget-content">
                <div class="vb-stat-item">
                    <span class="vb-stat-label"><?php _e('Total Bookings', 'vandel-booking'); ?></span>
                    <span class="vb-stat-value"><?php echo esc_html($stats['total_bookings']); ?></span>
                </div>
                <div class="vb-stat-item">
                    <span class="vb-stat-label"><?php _e('Pending Bookings', 'vandel-booking'); ?></span>
                    <span class="vb-stat-value"><?php echo esc_html($stats['pending_bookings']); ?></span>
                </div>
                <div class="vb-stat-item">
                    <span class="vb-stat-label"><?php _e('Completed Bookings', 'vandel-booking'); ?></span>
                    <span class="vb-stat-value"><?php echo esc_html($stats['completed_bookings']); ?></span>
                </div>
                <div class="vb-stat-item">
                    <span class="vb-stat-label"><?php _e('Total Revenue', 'vandel-booking'); ?></span>
                    <span class="vb-stat-value"><?php echo esc_html(number_format($stats['total_revenue'], 2)); ?></span>
                </div>
            </div>
        </div>

        <div class="vb-widget">
            <h3><?php _e('Recent Bookings', 'vandel-booking'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Service', 'vandel-booking'); ?></th>
                        <th><?php _e('Customer', 'vandel-booking'); ?></th>
                        <th><?php _e('Date', 'vandel-booking'); ?></th>
                        <th><?php _e('Status', 'vandel-booking'); ?></th>
                        <th><?php _e('Amount', 'vandel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <tr>
                            <td><?php echo esc_html($booking->service_name); ?></td>
                            <td><?php echo esc_html($booking->customer_name); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date))); ?></td>
                            <td>
                                <span class="vb-status vb-status-<?php echo esc_attr($booking->status); ?>">
                                    <?php echo esc_html(ucfirst($booking->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(number_format($booking->total_amount, 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="vb-widget-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-booking-bookings')); ?>" class="button">
                    <?php _e('View All Bookings', 'vandel-booking'); ?>
                </a>
            </p>
        </div>
    </div>
</div>

