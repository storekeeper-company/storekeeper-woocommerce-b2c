<?php

namespace StoreKeeper\WooCommerce\B2C\Debug;

use StoreKeeper\ApiWrapperDev\DumpFile;
use StoreKeeper\ApiWrapperDev\DumpFile\Context;

class HookDumpFile extends DumpFile
{
    public const HOOK_TYPE = 'hook';

    protected $hook_action;
    protected $request;
    protected $body;

    protected function setDataForType(string $type, array $data): void
    {
        if (self::HOOK_TYPE === $type) {
            $this->hook_action = $data['hook_action'];
            $this->request = $data['request'];
        } else {
            parent::setDataForType($type, $data);
        }
    }

    public function getHookAction()
    {
        return $this->hook_action;
    }

    public static function getFilenamePartForType(string $type, Context $context): string
    {
        if (self::HOOK_TYPE === $type) {
            return "{$context['hook_action']}.";
        }

        return parent::getFilenamePartForType($type, $context);
    }

    public function getRestRequest(?callable $finetune = null): \WP_REST_Request
    {
        $rest = new \WP_REST_Request();
        foreach ($this->request as $k => $v) {
            if (!is_null($finetune)) {
                $v = $finetune($k, $v);
            }
            call_user_func([$rest, 'set_'.$k], $v);
        }

        return $rest;
    }

    /**
     * @return array|void
     */
    public static function cleanContextFromSecretsForType(string $type, Context $context)
    {
        parent::cleanContextFromSecretsForType($type, $context);

        if (!empty($context['request']['headers'])) {
            self::cleanSecretValues($context['request']['headers'], ['upxhooktoken']);
        }
        if (!empty($context['request']['body'])) {
            $body = json_decode($context['request']['body'], true);
            if (!empty($body)) {
                self::cleanSecretValues($body, ['upxhooktoken']);
                $context['request']['body'] = json_encode($body);
            }
        }
    }

    public function getBody()
    {
        if (is_null($this->body) && !empty($this->request['body'])) {
            $this->body = json_decode($this->request['body'], true);
        }

        return $this->body;
    }

    /**
     * @throws \Exception
     */
    public function getEventBackref(): string
    {
        $body = $this->getBody();
        if (!empty($body['payload']['backref'])) {
            $backref = $body['payload']['backref'];
        } else {
            throw new \Exception('Cannot find backref in request');
        }

        return $backref;
    }
}
