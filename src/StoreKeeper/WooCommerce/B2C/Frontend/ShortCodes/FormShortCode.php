<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes;

use StoreKeeper\WooCommerce\B2C\I18N;

/**
 * This class registers the form shortcode, this short code is used for a subscription form.
 *
 * Example usage:
 * [b2c_subscribe_form]
 * <input name="firstname" type="text">
 * <input name="lastname" type="text">
 * <input name="email" type="email">
 * <input type="submit">
 * [/b2c_subscribe_form]
 *
 * Class FormShortCode
 */
class FormShortCode extends AbstractShortCode
{
    public const ID = 'b2c-subscription';

    private static function getMessageByState($state = 'error')
    {
        $messages = [
            'success' => ['message' => __('You are now subscribed.', I18N::DOMAIN), 'isError' => false],
            'already_subscribed' => [
                'message' => __('You are already subscribed.', I18N::DOMAIN),
                'isError' => false,
            ],
            'error' => ['message' => __('An error occurred!', I18N::DOMAIN), 'isError' => true],
        ];
        if (!array_key_exists($state, $messages)) {
            return $messages['error'];
        }

        return $messages[$state];
    }

    /**
     * @return string
     */
    protected function getShortCode()
    {
        return 'b2c_subscribe_form';
    }

    public function render($attributes = null, $content = null)
    {
        $id = self::ID;

        $message = '';
        $scrollScript = '';
        if (isset($_GET['state'])) {
            $state = self::getMessageByState(sanitize_key($_GET['state']));
            $className = self::ID.'-'.($state['isError'] ? 'error' : 'notice');
            $message = '<span class="'.$className.'">'.$state['message'].'</span>';
            $target = esc_js($id);
            $scrollScript =
                <<<HTML
                    <script>document.getElementsByClassName('$target')[0].scrollIntoView();</script>
HTML;
        }

        $id = esc_html($id);

        return <<<HTML
            $message
            <form class="$id" method="POST">
                <input name="form_id" type="hidden" value="$id">
                $content 
            </form>
            $scrollScript
HTML;
    }
}

// "You are now subscribed."
// "An error occurred!"
// "You are already subscribed."
