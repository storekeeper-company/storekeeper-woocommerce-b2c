<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class WordpressRestRequestWrapper
{
    public const TO_ARRAY_KEYS = [
        'headers',
        'body',
        'route',
        'method',
        'query_params',
        'file_params',
    ];
    /**
     * @var \WP_REST_Request
     */
    protected $request;

    /**
     * WordpressRestRequestWrapper constructor.
     */
    public function __construct(\WP_REST_Request $request)
    {
        $this->request = $request;
    }

    public function getRequest(): \WP_REST_Request
    {
        return $this->request;
    }

    /**
     * @return string|null
     */
    public function getAction()
    {
        return $this->getBodyParam('action');
    }

    /**
     * @return object|null
     *
     * @since 0.1.0
     */
    public function getBodyObject()
    {
        $bodyRaw = $this->request->get_body();

        return json_decode($bodyRaw, true);
    }

    /**
     * @return null
     */
    public function getBodyParam($param, $fallback = null)
    {
        $body = $this->getBodyObject();

        if (isset($body[$param])) {
            return $body[$param];
        }

        return $fallback;
    }

    public function getPayloadObject()
    {
        return $this->getBodyParam('payload');
    }

    public function getPayloadParam($param)
    {
        $payload = $this->getPayloadObject();

        if (isset($payload[$param])) {
            return $payload[$param];
        }

        return null;
    }

    public function toArray(): array
    {
        $array = [];
        foreach (self::TO_ARRAY_KEYS as $k) {
            $array[$k] = call_user_func([$this->request, 'get_'.$k]);
        }

        return $array;
    }
}
