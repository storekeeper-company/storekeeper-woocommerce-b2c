<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Helpers;

use StoreKeeper\WooCommerce\B2C\I18N;

class OverlayRenderer
{
    const ACTION_REDIRECT = 'action-redirect';
    const ACTION_BACK = 'action-back';

    private $active = false;

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
                            preg_match('/Allowed memory size of \d+ bytes exhausted/', $error['message']) ||
                            str_contains(strtolower($error['message']), 'out of memory')
                        ) {
                            $this->renderMemoryExhaustError(
                                $error['message'],
                                $error['file'].':'.$error['line']
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

    public function renderMemoryExhaustError($message, $description = '')
    {
        $message = esc_html($message);
        $description = esc_html($description);

        $firstExplanationText = __('Your allowed memory size has been exhausted. Here are some solution that may solve the issue.', I18N::DOMAIN);
        $secondExplanationText = __('But before trying some of these you can check what is the defined memory_limit of your site. You can check this by Clicking Tools > Site Health > Info > Server. Here you can check the current settings of your hosting.', I18N::DOMAIN);

        $serverInfoImagePath = plugin_dir_url(__FILE__).'../static/server-information.png';

        $instructionHeading = __('After identifying the value defined on memory and still the error persist.', I18N::DOMAIN);
        $firstInstruction = sprintf(
            __('You try can increasing the memory by adding %s on the wp-config.', I18N::DOMAIN),
            "<code>define('WP_MEMORY_LIMIT', '256M');</code>"
        );
        $firstInstructionFirstBullet = __('256M is a size sample you can put and then do a full sync.', I18N::DOMAIN);

        $secondInstruction = __('You can try executing this command via ssh.', I18N::DOMAIN);
        $secondInstructionFirstBullet = __('Go to the public_html folder of your webshop (e.g. /webshop/public_html/)', I18N::DOMAIN);
        $secondInstructionSecondBullet = sprintf(
            __('Execute %s.', I18N::DOMAIN),
            '<code>wp sk sync-woocommerce-full-sync</code>'
        );

        $thirdInstruction = __('Contact your hosting provider.', I18N::DOMAIN);
        $thirdInstructionFirstBullet = __('If you are not comfortable in trying the methods above, or it did not work for you. You can talk to your hosting provider about having them increase your memory limit.', I18N::DOMAIN);

        $content = <<<HTML
<strong style="color:red">$message</strong>
<br/>
<p style="color: red;white-space: pre-wrap;background: #f1f1f1;padding: 5px;" >$description</p>

<p style="padding: 5px;" >
$firstExplanationText
<br/>
$secondExplanationText
<br/>
<img src='$serverInfoImagePath' style="height: 100%; width: 100%; margin-top: 15px; margin-bottom: 15px;" alt="Server information">
<br/>
$instructionHeading
<br/>
1. $firstInstruction
<br/>
<span style="margin-left: 20px">&bull; $firstInstructionFirstBullet</span>
<br/>
2. $secondInstruction
<br/>
<span style="margin-left: 20px">&bull; $secondInstructionFirstBullet</span>
<br/>
<span style="margin-left: 20px">&bull; $secondInstructionSecondBullet</span>
<br/>
3. $thirdInstruction
<br/>
<span style="margin-left: 20px">&bull; $thirdInstructionFirstBullet</span>
</p>
HTML;

        $this->renderContentItem($content);
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
