<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class SsoHandlerTest extends AbstractTest
{
    /**
     * test if init works.
     *
     * @throws \Throwable
     */
    public function testHandleOk()
    {
        $file = $this->getHookDataDump('hook.sso.json');

        $rest = $this->getRestWithToken($file);
        $this->assertEquals('sso', $file->getHookAction());

        $response = $this->handleRequest($rest);
        $data = $response->get_data();
        $this->assertNotEmpty($data['url'], 'got url back');
    }
}
