<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace tests\verification\Tests;

use Magento\FunctionalTestingFramework\Test\Handlers\CestObjectHandler;
use Magento\FunctionalTestingFramework\Util\TestGenerator;
use PHPUnit\Framework\TestCase;

class DataExtensionTest extends TestCase
{
    const DATA_EXTENSION_CEST = 'DataExtensionCest';
    const RESOURCES_PATH = __DIR__ . '/../Resources';

    /**
     * Validates that a test using an extended data entity will populate successfully.
     */
    public function testExtendedDataReferences()
    {
        $cest = CestObjectHandler::getInstance()->getObject(self::DATA_EXTENSION_CEST);
        $test = TestGenerator::getInstance(null, [$cest]);
        $test->createAllCestFiles();

        $cestFile = $test->getExportDir() .
            DIRECTORY_SEPARATOR .
            self::DATA_EXTENSION_CEST .
            ".php";

        $this->assertTrue(file_exists($cestFile));

        $this->assertFileEquals(
            self::RESOURCES_PATH . DIRECTORY_SEPARATOR . self::DATA_EXTENSION_CEST . ".txt",
            $cestFile
        );
    }

}
