<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Raikia\SeatMarketSeeding\Services\StockListParser;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class StockListParserTest extends TestCase
{
    public function test_it_parses_common_bulk_and_eft_lines_with_validation_report(): void
    {
        $this->seedSde();
        $this->seedType(621, 'Caracal', ['groupID' => 25, 'volume' => 112000]);
        $this->seedType(3244, 'Warp Scrambler II');
        $this->seedType(12729, 'Barrage M', ['groupID' => 83, 'volume' => 0.01]);

        $result = app(StockListParser::class)->parseWithReport(implode("\n", [
            '[Caracal, Fleet]',
            'Caracal 2',
            'Warp Scrambler II x 3',
            '4 x Barrage M',
            '[empty high slot]',
            'Totally Fake Thing 99',
        ]), 2);

        $this->assertSame(4, $result['validation']['processed_lines']);
        $this->assertSame(2, $result['validation']['ignored_lines']);
        $this->assertSame(3, $result['validation']['valid_lines']);
        $this->assertSame(1, $result['validation']['skipped_lines']);

        $items = collect($result['items'])->keyBy('type_name');

        $this->assertSame(4, $items['Caracal']['quantity']);
        $this->assertSame(6, $items['Warp Scrambler II']['quantity']);
        $this->assertSame(8, $items['Barrage M']['quantity']);
        $this->assertStringContainsString('Totally Fake Thing', $result['validation']['skipped'][0]['reason']);
    }
}
