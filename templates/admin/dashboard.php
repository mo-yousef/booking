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
<?php
public function render_dashboard_widgets() {
    ?>
    <div class="vb-dashboard-widgets">
        <!-- Booking Statistics -->
        <div class="vb-widget">
            <h3><?php _e('Booking Overview', 'vandel-booking'); ?></h3>
            <div class="vb-widget-content">
                <?php $this->render_booking_stats(); ?>
            </div>
            <div class="vb-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=vandel-bookings'); ?>" class="button">
                    <?php _e('View All Bookings', 'vandel-booking'); ?>
                </a>
            </div>
        </div>

        <!-- Revenue Insights -->
        <div class="vb-widget">
            <h3><?php _e('Revenue Insights', 'vandel-booking'); ?></h3>
            <div class="vb-widget-content">
                <?php $this->render_revenue_chart(); ?>
            </div>
            <div class="vb-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=vandel-reports'); ?>" class="button">
                    <?php _e('Detailed Reports', 'vandel-booking'); ?>
                </a>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="vb-widget">
            <h3><?php _e('Recent Bookings', 'vandel-booking'); ?></h3>
            <div class="vb-widget-content">
                <?php $this->render_recent_bookings(); ?>
            </div>
        </div>

        <!-- Service Performance -->
        <div class="vb-widget">
            <h3><?php _e('Service Performance', 'vandel-booking'); ?></h3>
            <div class="vb-widget-content">
                <?php $this->render_service_performance(); ?>
            </div>
        </div>
    }

    private function render_booking_stats() {
        global $wpdb;
        
        // Get booking counts by status
        $status_counts = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM {$wpdb->prefix}vb_bookings 
            GROUP BY status
        ");

        // Calculate total bookings and revenue
        $total_bookings = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}vb_bookings
        ");

        $total_revenue = $wpdb->get_var("
            SELECT SUM(total_amount) 
            FROM {$wpdb->prefix}vb_bookings 
            WHERE status = 'completed'
        ");

        ?>
        <div class="vb-stat-grid">
            <?php foreach ($status_counts as $stat): ?>
                <div class="vb-stat-item">
                    <span class="vb-stat-label"><?php echo ucfirst($stat->status); ?> Bookings</span>
                    <span class="vb-stat-value"><?php echo number_format($stat->count); ?></span>
                </div>
            <?php endforeach; ?>
            
            <div class="vb-stat-item">
                <span class="vb-stat-label"><?php _e('Total Bookings', 'vandel-booking'); ?></span>
                <span class="vb-stat-value"><?php echo number_format($total_bookings); ?></span>
            </div>
            
            <div class="vb-stat-item">
                <span class="vb-stat-label"><?php _e('Total Revenue', 'vandel-booking'); ?></span>
                <span class="vb-stat-value">
                    <?php echo number_format($total_revenue, 2, '.', ',') . ' ' . get_option('vandel_booking_currency', 'USD'); ?>
                </span>
            </div>
        </div>
        <?php
    }

    private function render_revenue_chart() {
        global $wpdb;
        
        // Get monthly revenue for the last 12 months
        $monthly_revenue = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(booking_date, '%Y-%m') as month,
                SUM(total_amount) as total_revenue
            FROM {$wpdb->prefix}vb_bookings
            WHERE status = 'completed'
            AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month
        ");

        // Prepare data for Chart.js
        $labels = [];
        $revenues = [];
        
        foreach ($monthly_revenue as $data) {
            $labels[] = date('M Y', strtotime($data->month . '-01'));
            $revenues[] = round($data->total_revenue, 2);
        }
        ?>
        <canvas id="vb-revenue-chart" width="400" height="200"></canvas>
        <script>
        jQuery(document).ready(function($) {
            var ctx = $('#vb-revenue-chart')[0].getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: <?php echo json_encode($revenues); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
        </script>
        <?php
    }

    private function render_recent_bookings() {
        global $wpdb;
        
        $recent_bookings = $wpdb->get_results("
            SELECT b.*, s.post_title as service_name, u.display_name as customer_name
            FROM {$wpdb->prefix}vb_bookings b
            LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID
            LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
            ORDER BY b.created_at DESC
            LIMIT 5
        ");

        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Booking ID', 'vandel-booking'); ?></th>
                    <th><?php _e('Customer', 'vandel-booking'); ?></th>
                    <th><?php _e('Service', 'vandel-booking'); ?></th>
                    <th><?php _e('Date', 'vandel-booking'); ?></th>
                    <th><?php _e('Status', 'vandel-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_bookings as $booking): ?>
                    <tr>
                        <td><?php echo $booking->id; ?></td>
                        <td><?php echo esc_html($booking->customer_name); ?></td>
                        <td><?php echo esc_html($booking->service_name); ?></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($booking->booking_date)); ?></td>
                        <td>
                            <span class="vb-status vb-status-<?php echo esc_attr($booking->status); ?>">
                                <?php echo esc_html(ucfirst($booking->status)); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_service_performance() {
        global $wpdb;
        
        $service_performance = $wpdb->get_results("
            SELECT 
                p.ID, 
                p.post_title as service_name, 
                COUNT(b.id) as total_bookings,
                SUM(b.total_amount) as total_revenue
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}vb_bookings b ON p.ID = b.service_id
            WHERE p.post_type = 'vb_service'
            AND b.status = 'completed'
            GROUP BY p.ID
            ORDER BY total_revenue DESC
            LIMIT 5
        ");

        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Service', 'vandel-booking'); ?></th>
                    <th><?php _e('Total Bookings', 'vandel-booking'); ?></th>
                    <th><?php _e('Total Revenue', 'vandel-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($service_performance as $service): ?>
                    <tr>
                        <td><?php echo esc_html($service->service_name); ?></td>
                        <td><?php echo number_format($service->total_bookings); ?></td>
                        <td>
                            <?php 
                            echo number_format($service->total_revenue, 2) . ' ' . 
                                 get_option('vandel_booking_currency', 'USD'); 
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}