<?php

namespace Tests\Unit;

use App\Support\FormulaEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    private FormulaEvaluator $eval;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eval = new FormulaEvaluator;
    }

    public function test_basic_arithmetic(): void
    {
        $this->assertSame(7.0, $this->eval->evaluate('3 + 4'));
        $this->assertSame(2.0, $this->eval->evaluate('10 - 8'));
        $this->assertSame(12.0, $this->eval->evaluate('3 * 4'));
        $this->assertSame(2.5, $this->eval->evaluate('5 / 2'));
    }

    public function test_operator_precedence_and_parentheses(): void
    {
        $this->assertSame(14.0, $this->eval->evaluate('2 + 3 * 4'));
        $this->assertSame(20.0, $this->eval->evaluate('(2 + 3) * 4'));
        $this->assertSame(2.0, $this->eval->evaluate('8 / 2 / 2'));
    }

    public function test_variables_and_decimals(): void
    {
        $vars = ['ext_wall_lft' => 166.5, 'slab_depth' => 0.33, 'house_sqft' => 1103.42];
        $this->assertSame(20.8125, $this->eval->evaluate('ext_wall_lft * 2 / 16', $vars));
        $this->assertEqualsWithDelta(13.48, $this->eval->evaluate('house_sqft * slab_depth / 27', $vars), 0.01);
    }

    public function test_unary_minus(): void
    {
        $this->assertSame(-5.0, $this->eval->evaluate('-5'));
        $this->assertSame(1.0, $this->eval->evaluate('3 + -2'));
    }

    public function test_empty_formula_is_zero(): void
    {
        $this->assertSame(0.0, $this->eval->evaluate('   '));
    }

    public function test_unknown_variable_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluate('nope * 2', ['ext_wall_lft' => 10]);
    }

    public function test_division_by_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluate('5 / 0');
    }

    public function test_invalid_character_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluate('5 % 2');
    }

    public function test_unbalanced_parenthesis_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval->evaluate('(2 + 3');
    }
}
