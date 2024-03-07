<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Helpers;

use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class OverlayRenderer
{
    public const ACTION_REDIRECT = 'action-redirect';
    public const ACTION_BACK = 'action-back';

    private $active = false;
    private $commandName;

    public function __construct(?string $class = null)
    {
        $this->commandName = $this->getCommandName($class);
    }

    private function getCommandName(?string $class)
    {
        if (!is_null($class)) {
            return call_user_func("$class::getCommandName");
        }

        return null;
    }

    public function start(string $title, string $description = '')
    {
        $this->active = true;

        $this->registerShutdownFunction();

        $this->renderOverlay($title, $description);
    }

    public function end()
    {
        $this->active = false;
    }

    public function endWithRedirect($url)
    {
        $this->end();

        $this->doRedirect($url);
    }

    public function endWithRedirectBack()
    {
        $this->end();

        $this->doRedirectBack();
    }

    private function registerShutdownFunction()
    {
        register_shutdown_function(
            function () {
                if ($this->active) {
                    $error = error_get_last();
                    if (null !== $error) {
                        if (
                            preg_match('/Allowed memory size of \d+ bytes exhausted/', $error['message'])
                            || str_contains(strtolower($error['message']), 'out of memory')
                        ) {
                            $this->renderMemoryExhaustError(
                                $error['message'],
                                $error['file'].':'.$error['line'],
                            );
                        } else {
                            $this->renderError(
                                $error['message'],
                                $error['file'].':'.$error['line']
                            );
                        }
                    }
                }
            }
        );
    }

    public function renderMessage(string $message, $description = '')
    {
        $message = esc_html($message);
        $description = esc_html($description);
        $content = <<<HTML
<strong>$message</strong>
<p>$description</p>
HTML;

        $this->renderContentItem($content);
    }

    public function renderError($message, $description = '')
    {
        $message = esc_html($message);
        $description = esc_html($description);
        $content = <<<HTML
<strong style="color:red">$message</strong>
<br/>
<p style="color: red;white-space: pre-wrap;background: #f1f1f1;padding: 5px;" >$description</p>
HTML;

        $this->renderContentItem($content);
    }

    public function renderMemoryExhaustError($message, $description = ''): void
    {
        $message = esc_html($message);
        $description = esc_html($description);

        $firstExplanationText = esc_html(__('Your allowed memory size has been exhausted.', I18N::DOMAIN));
        $secondExplanationText = wp_kses(
            sprintf(
                __('Current memory limit configured: %s'),
                '<strong>'.IniHelper::getIni('memory_limit').'</strong>'
            ),
            HtmlEscape::ALLOWED_COMMON
        );

        $instructionHeading = esc_html(__('Below are actions you can do to increase PHP memory limit:', I18N::DOMAIN));
        $instructionsHtml = $this->getMemoryLimitInstructions();

        $content = <<<HTML
<strong style="color:red">$message</strong>
<br/>
<p style="color: red;white-space: pre-wrap;background: #f1f1f1;padding: 5px;" >$description</p>

<p style="padding: 5px;" >
$firstExplanationText
<br/>
$secondExplanationText
<br/>
<br/>
$instructionHeading
<br/>
$instructionsHtml
</p>
HTML;

        $this->renderContentItem($content);
    }

    private function getMemoryLimitInstructions(): string
    {
        $instructions = [
            [
                'message' => sprintf(
                    __('You can increase the memory by adding %s on the wp-config.', I18N::DOMAIN),
                    "<code>define('WP_MEMORY_LIMIT', '1G');</code>"
                ),
                'bullets' => [
                    __('Suggested memory limit is 1G and then do a full sync.', I18N::DOMAIN),
                ],
            ],
        ];

        if ($this->commandName) {
            $instructions[] = [
                 'message' => __('You can try executing this command via ssh.', I18N::DOMAIN),
                 'bullets' => [
                     sprintf(
                         __('Go to the public_html folder of your webshop: %s', I18N::DOMAIN),
                         '<code>'.ABSPATH.'</code>',
                     ),
                     sprintf(
                         __('Execute %s.', I18N::DOMAIN),
                         '<code>wp sk '.$this->commandName.'</code>'
                     ),
                 ],
            ];
        }

        $instructions[] = [
            'message' => __('Contact your hosting provider.', I18N::DOMAIN),
            'bullets' => [
                __('If you are not comfortable in trying the methods above, or it did not work for you. You can talk to your hosting provider about having them increase your memory limit.', I18N::DOMAIN),
            ],
        ];

        $instructionsHtml = '';
        $instructionsCount = count($instructions);
        for ($counter = 1; $counter <= $instructionsCount; ++$counter) {
            $instruction = $instructions[$counter - 1];
            $message = $instruction['message'];
            $bullets = $instruction['bullets'];

            $instructionsHtml .= wp_kses(
                <<<HTML
$counter. $message
<br/>
HTML,
                HtmlEscape::ALLOWED_COMMON);

            foreach ($bullets as $bullet) {
                $instructionsHtml .= wp_kses(
                    <<<HTML
<span style="margin-left: 20px">&bull; $bullet</span>
<br/>
HTML,
                    HtmlEscape::ALLOWED_COMMON);
            }
        }

        return $instructionsHtml;
    }

    private function renderContentItem(string $content)
    {
        echo <<<HTML
<script type="text/javascript">
(function () {
    const overlayContent = document
        .querySelector('body.has-storekeeper-overlay #storekeeper-overlay #storekeeper-overlay-modal-content')
    
    if (overlayContent) {
         const contentItem = document.createElement('div');
        contentItem.classList.add('storekeeper-overlay-item');
        contentItem.innerHTML = `$content`;
        
        overlayContent.appendChild(contentItem);   
        overlayContent.classList.add('has-content');
    } else {
        console.error('Unable to find the overlay content');
    }
})()
</script>
HTML;

        $this->flush();
    }

    private function doRedirect($redirectTo)
    {
        $redirectTo = esc_url_raw($redirectTo);
        echo <<<HTML
<script type="text/javascript">
(function (){
    setTimeout(() => window.location.replace(`$redirectTo`), 1000);
})();
</script>
HTML;
    }

    private function doRedirectBack()
    {
        echo <<<HTML
<script type="text/javascript">
(function (){
    setTimeout(() => window.history.back(), 1000)
})();
</script>
HTML;
    }

    private function renderOverlay(string $title, string $description)
    {
        $title = esc_html($title);
        $description = esc_html($description);
        $content = <<<HTML
<div id="storekeeper-overlay-modal">
    <div id="storekeeper-overlay-modal-wrapper">
        <p id="storekeeper-overlay-modal-title">$title <small>$description</small></p>
        <div id="storekeeper-overlay-modal-content"></div> 
    </div>
</div>
HTML;

        echo <<<HTML
<script type="text/javascript">
(function (){
    const body = document.querySelector('body');
    body.classList.add('has-storekeeper-overlay');
    
    if (!body.querySelector('#storekeeper-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'storekeeper-overlay';
        overlay.innerHTML = `$content`;
        body.appendChild(overlay);
    }
   
})();
</script>
HTML;

        $this->flush();
    }

    private function flush()
    {
        // Flush the current html to the page.
        ob_flush();
        flush();
    }
}
