<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Evaluates takeoff formulas safely — no eval(). Supports +, -, *, /,
 * parentheses, decimal numbers, and whitelisted variable names. Anything
 * else (unknown identifier, bad syntax, division by zero) throws.
 *
 * Grammar:
 *   expr   := term (('+' | '-') term)*
 *   term   := factor (('*' | '/') factor)*
 *   factor := '-' factor | '(' expr ')' | number | identifier
 */
class FormulaEvaluator
{
    /** @var list<array{type: string, value: string}> */
    private array $tokens = [];

    private int $pos = 0;

    /** @var array<string, float> */
    private array $vars = [];

    /**
     * @param  array<string, float|int|string>  $variables
     */
    public function evaluate(string $formula, array $variables = []): float
    {
        $this->vars = array_map(fn ($v) => (float) $v, $variables);
        $this->tokens = $this->tokenize($formula);
        $this->pos = 0;

        if ($this->tokens === []) {
            return 0.0;
        }

        $result = $this->parseExpr();

        if ($this->pos < count($this->tokens)) {
            throw new InvalidArgumentException("Unexpected token in formula: {$formula}");
        }

        return $result;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $formula): array
    {
        $tokens = [];
        $length = strlen($formula);
        $i = 0;

        while ($i < $length) {
            $char = $formula[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (str_contains('+-*/()', $char)) {
                $tokens[] = ['type' => 'op', 'value' => $char];
                $i++;

                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $number = '';
                while ($i < $length && (ctype_digit($formula[$i]) || $formula[$i] === '.')) {
                    $number .= $formula[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'number', 'value' => $number];

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $name = '';
                while ($i < $length && (ctype_alnum($formula[$i]) || $formula[$i] === '_')) {
                    $name .= $formula[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'ident', 'value' => $name];

                continue;
            }

            throw new InvalidArgumentException("Invalid character '{$char}' in formula.");
        }

        return $tokens;
    }

    private function parseExpr(): float
    {
        $value = $this->parseTerm();

        while ($this->isOp('+') || $this->isOp('-')) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseTerm();
            $value = $op === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while ($this->isOp('*') || $this->isOp('/')) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;
            $right = $this->parseFactor();

            if ($op === '/') {
                if ($right === 0.0) {
                    throw new InvalidArgumentException('Division by zero in formula.');
                }
                $value /= $right;
            } else {
                $value *= $right;
            }
        }

        return $value;
    }

    private function parseFactor(): float
    {
        if ($this->isOp('-')) {
            $this->pos++;

            return -$this->parseFactor();
        }

        if ($this->isOp('(')) {
            $this->pos++;
            $value = $this->parseExpr();
            if (! $this->isOp(')')) {
                throw new InvalidArgumentException('Missing closing parenthesis in formula.');
            }
            $this->pos++;

            return $value;
        }

        $token = $this->tokens[$this->pos] ?? null;

        if ($token === null) {
            throw new InvalidArgumentException('Unexpected end of formula.');
        }

        if ($token['type'] === 'number') {
            $this->pos++;

            return (float) $token['value'];
        }

        if ($token['type'] === 'ident') {
            $this->pos++;
            if (! array_key_exists($token['value'], $this->vars)) {
                throw new InvalidArgumentException("Unknown variable '{$token['value']}' in formula.");
            }

            return $this->vars[$token['value']];
        }

        throw new InvalidArgumentException("Unexpected token '{$token['value']}' in formula.");
    }

    private function isOp(string $op): bool
    {
        $token = $this->tokens[$this->pos] ?? null;

        return $token !== null && $token['type'] === 'op' && $token['value'] === $op;
    }
}
