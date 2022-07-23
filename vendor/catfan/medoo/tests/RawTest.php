<?php

namespace Medoo\Tests;

use Medoo\Medoo;

/**
 * @coversDefaultClass \Medoo\Medoo
 */
class RawTest extends MedooTestCase
{
    /**
     * @covers ::raw()
     * @covers ::isRaw()
     * @covers ::buildRaw()
     * @dataProvider typesProvider
     */
    public function testRawWithPlaceholder($type)
    {
        $this->setType($type);

        $this->database->select('account', [
            'score' => Medoo::raw('SUM(<age> + <experience>)')
        ]);

        $this->assertQuery(
            <<<EOD
            SELECT SUM("age" + "experience") AS "score"
            FROM "account"
            EOD,
            $this->database->queryString
        );
    }
}
