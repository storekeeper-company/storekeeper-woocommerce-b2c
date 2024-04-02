<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapperDev\DumpFile\Context;
use StoreKeeper\ApiWrapperDev\DumpFile\Writer;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Debug\HookDumpFile;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressRestRequestWrapper;

abstract class AbstractEndpoint
{
    use LoggerAwareTrait;

    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    /**
     * @var \Throwable
     */
    protected $lastError;
    /**
     * @var WordpressRestRequestWrapper
     */
    protected $wrappedRequest;

    /**
     * @param \WP_REST_Request $wrappedRequest
     */
    public function setWrappedRequest($wrappedRequest)
    {
        $this->wrappedRequest = new WordpressRestRequestWrapper($wrappedRequest);
    }

    /**
     * @return WordpressRestRequestWrapper
     */
    public function getWrappedRequest()
    {
        return $this->wrappedRequest;
    }

    final public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $this->setWrappedRequest($request);

            if (Core::isDataDump()) {
                $writer = new Writer(Core::getDumpDir());
                $writer->addExtraFileDumpType(HookDumpFile::HOOK_TYPE, HookDumpFile::class);
                $data = $writer->withDump(
                    HookDumpFile::HOOK_TYPE,
                    function (Context $context) {
                        $context['request'] = $this->wrappedRequest->toArray();
                        $context['hook_action'] = $this->wrappedRequest->getAction();

                        return $this->handle();
                    }
                );
            } else {
                $data = $this->handle();
            }
            if (empty($data) || is_scalar($data)) {
                $data = [
                    'response' => $data,
                ];
            } else {
                if (!is_array($data)) {
                    $data = [
                        'response' => (string) $data,
                    ];
                }
            }
            $response = new \WP_REST_Response(
                [
                    'success' => true,
                ] + $data
            );
            $this->lastError = null;
        } catch (\Throwable $e) {
            $this->setLastError($e);
            $code = 500;
            $message = 'Something went wrong';
            if ($e instanceof WpRestException) {
                $message = $e->getMessage();
                $code = $e->getHttpCode();
            }
            $details = $this->logEndpointError($e, $message);
            $response = new \WP_REST_Response(
                [
                    'success' => false,
                    'error' => $message,
                    'details' => Core::isDebug() ? $details : null,
                ]
            );
            $response->set_status($code);
        }

        return $response;
    }

    abstract protected function handle();

    protected function setLastError($e): void
    {
        $this->lastError = $e;
    }

    /**
     * @return \Throwable
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    protected function logEndpointError($e, string $message): string
    {
        $details = BaseException::getAsString($e);
        $data = [
            'success' => false,
            'error' => $message,
            'details' => $details,
        ];
        if (!empty($this->wrappedRequest)) {
            $data['request'] = $this->wrappedRequest->toArray();
            $data['hook_action'] = $this->wrappedRequest->getAction();
        }
        $this->logger->error('Endpoint error', $data);

        return $details;
    }
}
