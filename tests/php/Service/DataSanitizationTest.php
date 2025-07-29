<?php

namespace SilverStripe\Laboratory\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Laboratory\Service\TrafficSplitService;

class DataSanitizationTest extends SapphireTest
{
    /**
     * @param string|null $data
     * @param string|null $expected
     * @return void
     */
    #[DataProvider('sanitizationDataCasesProvider')]
    public function testSanitiseVariationIdentifier(?string $data, ?string $expected): void
    {
        $value = TrafficSplitService::singleton()->sanitiseVariationIdentifier($data);

        if ($expected === null) {
            $this->assertNull($value, 'We expect null as identifier value');

            return;
        }

        $this->assertEquals($expected, $value, 'We expect a specific identifier value');
    }

    public static function sanitizationDataCasesProvider(): array
    {
        return [
            'null value' => [
                null,
                null,
            ],
            'empty string' => [
                '',
                '',
            ],
            'empty list' => [
                '',
                '',
            ],
            'list in wrong order' => [
                'red' . PHP_EOL . 'blue' . PHP_EOL . 'primary',
                'primary' . PHP_EOL . 'blue' . PHP_EOL . 'red',
            ],
            'list with invalid values' => [
                'blue' . PHP_EOL . 'red@',
                'blue' . PHP_EOL . 'red',
            ],
            'list with duplicated values' => [
                'red' . PHP_EOL . 'blue' . PHP_EOL . 'red',
                'blue' . PHP_EOL . 'red',
            ],
        ];
    }
}
