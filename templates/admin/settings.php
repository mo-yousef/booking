<!-- templates/admin/settings.php -->
<div class="wrap">
    <h1><?php _e('Vandel Booking Settings', 'vandel-booking'); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('vandel_booking_settings');
        do_settings_sections('vandel_booking_settings');
        ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Currency', 'vandel-booking'); ?></th>
                <td>
                    <select name="vandel_booking_currency">
                        <?php
                        $currencies = array(
                            'USD' => 'US Dollar',
                            'EUR' => 'Euro',
                            'GBP' => 'British Pound',
                            'AUD' => 'Australian Dollar',
                            'CAD' => 'Canadian Dollar'
                        );
                        $selected_currency = get_option('vandel_booking_currency', 'USD');
                        foreach ($currencies as $code => $name) {
                            echo sprintf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($code),
                                selected($selected_currency, $code, false),
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Stripe Test Mode', 'vandel-booking'); ?></th>
                <td>
                    <input type="checkbox" name="vandel_booking_stripe_test_mode" value="1" <?php checked(get_option('vandel_booking_stripe_test_mode', true)); ?>>
                    <span class="description"><?php _e('Enable Stripe test mode', 'vandel-booking'); ?></span>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Stripe Test Public Key', 'vandel-booking'); ?></th>
                <td>
                    <input type="text" name="vandel_booking_stripe_test_key" class="regular-text" value="<?php echo esc_attr(get_option('vandel_booking_stripe_test_key')); ?>">
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Stripe Test Secret Key', 'vandel-booking'); ?></th>
                <td>
                    <input type="password" name="vandel_booking_stripe_test_secret" class="regular-text" value="<?php echo esc_attr(get_option('vandel_booking_stripe_test_secret')); ?>">
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Stripe Live Public Key', 'vandel-booking'); ?></th>
                <td>
                    <input type="text" name="vandel_booking_stripe_live_key" class="regular-text" value="<?php echo esc_attr(get_option('vandel_booking_stripe_live_key')); ?>">
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Stripe Live Secret Key', 'vandel-booking'); ?></th>
                <td>
                    <input type="password" name="vandel_booking_stripe_live_secret" class="regular-text" value="<?php echo esc_attr(get_option('vandel_booking_stripe_live_secret')); ?>">
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Email Notifications', 'vandel-booking'); ?></th>
                <td>
                    <input type="checkbox" name="vandel_booking_email_notifications" value="1" <?php checked(get_option('vandel_booking_email_notifications', true)); ?>>
                    <span class="description"><?php _e('Enable email notifications', 'vandel-booking'); ?></span>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Admin Email', 'vandel-booking'); ?></th>
                <td>
                    <input type="email" name="vandel_booking_admin_email" class="regular-text" value="<?php echo esc_attr(get_option('vandel_booking_admin_email', get_option('admin_email'))); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
