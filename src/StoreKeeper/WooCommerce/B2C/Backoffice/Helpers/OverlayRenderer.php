<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Helpers;

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
                        $this->renderError(
                            $error['message'],
                            $error['file'].':'.$error['line']
                        );
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
