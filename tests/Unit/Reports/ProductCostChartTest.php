<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\ProductCostChart;
use PHPUnit\Framework\TestCase;

class ProductCostChartTest extends TestCase
{
    private function rows(): array
    {
        return (require dirname(__DIR__, 2).'/Fixtures/product-cost-report.php')['compras'];
    }

    public function test_chart_orders_points_and_scales_both_cost_series_inside_its_plot(): void
    {
        $chart = (new ProductCostChart)->build($this->rows());
        $this->assertCount(3, $chart['points']);
        $this->assertSame(['COST-1', 'COST-2', 'COST-3'], array_column($chart['points'], 'invoice'));
        foreach ($chart['points'] as $point) {
            $this->assertGreaterThanOrEqual(84, $point['x']);
            $this->assertLessThanOrEqual(928, $point['x']);
            foreach (['y', 'final_y'] as $axis) {
                $this->assertTrue(is_finite($point[$axis]));
                $this->assertGreaterThanOrEqual(32, $point[$axis]);
                $this->assertLessThanOrEqual(276, $point[$axis]);
            }
        }
        $this->assertSame('Sí', $chart['points'][0]['tax']);
    }

    public function test_single_and_zero_cost_points_do_not_create_invalid_coordinates(): void
    {
        $row = array_replace($this->rows()[0], ['costo_unitario' => 0, 'costo_final_unitario' => 0]);
        $chart = (new ProductCostChart)->build([$row]);
        $this->assertSame(516.0, $chart['points'][0]['x']);
        $this->assertTrue(is_finite($chart['points'][0]['y']));
        $this->assertSame('L 0.0000', $chart['points'][0]['cost']);
    }

    public function test_bonus_rows_are_excluded_and_missing_costs_break_the_line_instead_of_becoming_zero(): void
    {
        $rows = $this->rows();
        $rows[1]['costo_unitario'] = $rows[1]['costo_final_unitario'] = null;
        $chart = (new ProductCostChart)->build($rows);
        $this->assertCount(2, $chart['points']);
        $this->assertCount(2, $chart['series']['costo_unitario']);
        $rows[0]['solo_bonificacion'] = $rows[2]['solo_bonificacion'] = true;
        $this->assertSame([], (new ProductCostChart)->build($rows)['points']);
        $this->assertSame('Sin dato', ProductCostChart::money(null));
        $this->assertSame('Mixto', ProductCostChart::taxLabel('mixto'));
    }
}
