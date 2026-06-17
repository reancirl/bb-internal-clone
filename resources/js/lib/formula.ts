// Mirror of app/Support/FormulaEvaluator.php for live client-side previews.
// Supports + - * / ( ), decimals, unary minus, and whitelisted variables.
// Returns null on any error (unknown variable, bad syntax, divide by zero).

type Token = { type: 'op' | 'number' | 'ident'; value: string };

function tokenize(formula: string): Token[] | null {
    const tokens: Token[] = [];
    let i = 0;
    while (i < formula.length) {
        const ch = formula[i];
        if (ch === ' ' || ch === '\t') {
            i++;
            continue;
        }
        if ('+-*/()'.includes(ch)) {
            tokens.push({ type: 'op', value: ch });
            i++;
            continue;
        }
        if (/[0-9.]/.test(ch)) {
            let num = '';
            while (i < formula.length && /[0-9.]/.test(formula[i])) num += formula[i++];
            tokens.push({ type: 'number', value: num });
            continue;
        }
        if (/[a-zA-Z_]/.test(ch)) {
            let name = '';
            while (i < formula.length && /[a-zA-Z0-9_]/.test(formula[i])) name += formula[i++];
            tokens.push({ type: 'ident', value: name });
            continue;
        }
        return null; // invalid character
    }
    return tokens;
}

export function evaluateFormula(formula: string, vars: Record<string, number>): number | null {
    const tokens = tokenize(formula);
    if (tokens === null) return null;
    if (tokens.length === 0) return null;

    let pos = 0;
    const peek = () => tokens[pos] ?? null;
    const isOp = (op: string) => {
        const t = peek();
        return t !== null && t.type === 'op' && t.value === op;
    };

    let failed = false;

    const parseExpr = (): number => {
        let value = parseTerm();
        while (isOp('+') || isOp('-')) {
            const op = tokens[pos++].value;
            const right = parseTerm();
            value = op === '+' ? value + right : value - right;
        }
        return value;
    };

    const parseTerm = (): number => {
        let value = parseFactor();
        while (isOp('*') || isOp('/')) {
            const op = tokens[pos++].value;
            const right = parseFactor();
            if (op === '/') {
                if (right === 0) {
                    failed = true;
                    return 0;
                }
                value /= right;
            } else {
                value *= right;
            }
        }
        return value;
    };

    const parseFactor = (): number => {
        if (isOp('-')) {
            pos++;
            return -parseFactor();
        }
        if (isOp('(')) {
            pos++;
            const value = parseExpr();
            if (!isOp(')')) {
                failed = true;
                return 0;
            }
            pos++;
            return value;
        }
        const token = peek();
        if (token === null) {
            failed = true;
            return 0;
        }
        if (token.type === 'number') {
            pos++;
            return parseFloat(token.value);
        }
        if (token.type === 'ident') {
            pos++;
            if (!(token.value in vars)) {
                failed = true;
                return 0;
            }
            return vars[token.value];
        }
        failed = true;
        return 0;
    };

    const result = parseExpr();
    if (failed || pos < tokens.length || Number.isNaN(result)) return null;
    return result;
}
