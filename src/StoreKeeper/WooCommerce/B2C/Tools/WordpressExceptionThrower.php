<?php
/**
 * This is a helper that contains function that are here to
 * help with common WordPress related behaviours.
 */

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;

class WordpressExceptionThrower
{
    /**
     * This function is here to throw errors if the value is an WP_Error.
     *
     * @param mixed $maybe_wp_error
     * @param bool  $check_for_false if set to true, it will throw also an error
     *
     * @return mixed
     *
     * @throws WordpressException
     */
    public static function throwExceptionOnWpError($maybe_wp_error, $check_for_false = false)
    {
        if (is_wp_error($maybe_wp_error)) {
            throw new WordpressException($maybe_wp_error->get_error_message());
        } else {
            if ($check_for_false && false === $maybe_wp_error) {
                throw new WordpressException(__('Function returned false', I18N::DOMAIN));
            }
        }

        // Yay it was not an WP_Error.. For now..
        return $maybe_wp_error;
    }
}
