<?php
/**
 * Booking Confirmation Template
 */
?>
<div class="vandel-booking-confirmation">
    <h2><?php esc_html_e( 'Booking Confirmed!', 'vandel-booking' ); ?></h2>
    <p><?php esc_html_e( 'Thank you for your booking. A confirmation email has been sent.', 'vandel-booking' ); ?></p>
    
    <?php if ( isset( $booking ) ) : ?>
        <ul>
            <li><strong><?php esc_html_e( 'Booking ID:', 'vandel-booking' ); ?></strong> <?php echo esc_html( $booking->id ); ?></li>
            <li><strong><?php esc_html_e( 'Service ID:', 'vandel-booking' ); ?></strong> <?php echo esc_html( $booking->service_id ); ?></li>
            <li><strong><?php esc_html_e( 'Date:', 'vandel-booking' ); ?></strong> <?php echo esc_html( $booking->booking_date ); ?></li>
            <li><strong><?php esc_html_e( 'Time:', 'vandel-booking' ); ?></strong> <?php echo esc_html( $booking->booking_time ); ?></li>
        </ul>
    <?php endif; ?>
</div>
