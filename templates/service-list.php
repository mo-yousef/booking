<?php
/**
 * Service List Template
 */
?>
<div class="vandel-service-list">
    <h2><?php esc_html_e( 'Our Services', 'vandel-booking' ); ?></h2>
    <?php if ( ! empty( $services ) ) : ?>
        <ul>
            <?php foreach ( $services as $service ) : ?>
                <li>
                    <strong><?php echo esc_html( $service->service_name ); ?></strong><br/>
                    <?php esc_html_e( 'Price: ', 'vandel-booking' ); ?>
                    <?php echo esc_html( number_format( $service->regular_price, 2 ) ); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p><?php esc_html_e( 'No services available.', 'vandel-booking' ); ?></p>
    <?php endif; ?>
</div>
