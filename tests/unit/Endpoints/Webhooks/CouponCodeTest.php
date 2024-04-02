<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Imports\CouponCodeImport;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class CouponCodeTest extends AbstractTest
{
    use CommandRunnerTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpRunner();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    public const ROOT_DIR = 'events/couponCode';
    public const CREATE_DIR = 'create';
    public const UPDATE_DIR = 'update';
    public const DELETE_DIR = 'delete';
    public const DUMP_DIR = 'dump';
    public const WEBHOOK_FILE = 'hook.events.createCouponCode.json';
    public const LIST_COUPON_CODE_FILE = 'moduleFunction.ShopModule::listCouponCodesForHook.success.json';

    public function testCreate()
    {
        $this->initApiConnection();

        list($dumpFilePath) = $this->createCouponCode();

        $expected = $this->getDumpFileData($dumpFilePath);
        $actual = new \WC_Coupon($expected->get('code'));
        $this->assertCouponCode($expected, $actual);
    }

    public function testUpdate()
    {
        $this->initApiConnection();

        $this->createCouponCode();
        list($dumpFilePath) = $this->updateCouponCode();

        $expected = $this->getDumpFileData($dumpFilePath);
        $actual = new \WC_Coupon($expected->get('code'));
        $this->assertCouponCode($expected, $actual);
    }

    public function testDelete()
    {
        $this->initApiConnection();

        list($__, $webhookPath) = $this->createCouponCode();
        $storekeeperId = $this->getStorekeeperIdFromWebhook($webhookPath);
        $couponCode = CouponCodeImport::getCouponCodeByStorekeeperId($storekeeperId);

        $this->assertNotNull(
            $couponCode,
            'The coupon code was not created'
        );

        $webhookPath = $this->deleteCouponCode();
        $storekeeperId = $this->getStorekeeperIdFromWebhook($webhookPath);
        $couponCode = CouponCodeImport::getCouponCodeByStorekeeperId($storekeeperId);

        $this->assertNull(
            $couponCode,
            'The coupon code was not deleted'
        );
    }

    public function testOrderOnlySyncMode()
    {
        $this->initApiConnection();
        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        list($__, $webhookPath) = $this->createCouponCode();
        $storekeeperId = $this->getStorekeeperIdFromWebhook($webhookPath);
        $couponCode = CouponCodeImport::getCouponCodeByStorekeeperId($storekeeperId);

        $this->assertNull(
            $couponCode,
            'The coupon code should not been created'
        );
    }

    private function getDumpFileData(string $dumpFilePath): Dot
    {
        $dumpFile = $this->getDataDump($dumpFilePath);
        $dumpFileReturn = $dumpFile->getReturn();
        $dumpFileData = current($dumpFileReturn['data']);

        return new Dot($dumpFileData);
    }

    private function executeWebhook(string $hookFile): \WP_REST_Response
    {
        $dumpFile = $this->getHookDataDump($hookFile);
        $request = $this->getRestWithToken($dumpFile);

        return $this->handleRequest($request);
    }

    private function assertWebhookResponse(string $hookFile, \WP_REST_Response $response)
    {
        $dumpFile = $this->getHookDataDump($hookFile);
        list($webhookType) = StoreKeeperApi::extractMainTypeAndOptions($dumpFile->getEventBackref());
        $data = $response->get_data();

        $this->assertTrue(
            $data['success'],
            'Hook call successfull'
        );
        $this->assertEquals(
            'events',
            $dumpFile->getHookAction(),
            'Hook actions'
        );
        $this->assertEquals(
            'ShopModule::CouponCode',
            $webhookType,
            'Correct hook type'
        );
    }

    protected function executeAssertWebhook(string $webhookPath): void
    {
        $response = $this->executeWebhook($webhookPath);

        $this->assertWebhookResponse($webhookPath, $response);
    }

    protected function createCouponCode(): array
    {
        $dumpDirPath = self::ROOT_DIR.DIRECTORY_SEPARATOR.self::CREATE_DIR.DIRECTORY_SEPARATOR.self::DUMP_DIR;
        $dumpFilePath = $dumpDirPath.DIRECTORY_SEPARATOR.self::LIST_COUPON_CODE_FILE;
        $webhookPath = self::ROOT_DIR.DIRECTORY_SEPARATOR.self::CREATE_DIR.DIRECTORY_SEPARATOR.self::WEBHOOK_FILE;

        $this->mockApiCallsFromDirectory($dumpDirPath);

        $this->executeAssertWebhook($webhookPath);

        $this->runner->execute(ProcessAllTasks::getCommandName());

        return [$dumpFilePath, $webhookPath];
    }

    protected function updateCouponCode(): array
    {
        $dumpDirPath = self::ROOT_DIR.DIRECTORY_SEPARATOR.self::UPDATE_DIR.DIRECTORY_SEPARATOR.self::DUMP_DIR;
        $dumpFilePath = $dumpDirPath.DIRECTORY_SEPARATOR.self::LIST_COUPON_CODE_FILE;
        $webhookPath = self::ROOT_DIR.DIRECTORY_SEPARATOR.self::UPDATE_DIR.DIRECTORY_SEPARATOR.self::WEBHOOK_FILE;

        $this->mockApiCallsFromDirectory($dumpDirPath);

        $this->executeAssertWebhook($webhookPath);

        $this->runner->execute(ProcessAllTasks::getCommandName());

        return [$dumpFilePath, $webhookPath];
    }

    protected function deleteCouponCode(): string
    {
        $webhookPath = self::ROOT_DIR.DIRECTORY_SEPARATOR.self::DELETE_DIR.DIRECTORY_SEPARATOR.self::WEBHOOK_FILE;

        $this->executeAssertWebhook($webhookPath);

        $this->runner->execute(ProcessAllTasks::getCommandName());

        return $webhookPath;
    }

    protected function getStorekeeperIdFromWebhook(string $webhookPath)
    {
        $dumpFile = $this->getHookDataDump($webhookPath);
        list($__, $options) = StoreKeeperApi::extractMainTypeAndOptions($dumpFile->getEventBackref());

        return $options['id'];
    }
}
