// Run with: npm run test:js
// The same cases as tests/Unit/TotalsCalculatorTest.php, so PHP and the browser can't disagree.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { calculateTotals, formatMinor, toMinor } from '../../resources/js/lib/totals.js';

const cases = JSON.parse(readFileSync(new URL('../fixtures/totals.json', import.meta.url), 'utf8'));

/** BigInts → strings, so they compare with the fixture (whose huge values are strings). */
const strings = (value) => JSON.parse(JSON.stringify(value, (_, v) => (typeof v === 'bigint' || typeof v === 'number' ? String(v) : v)));

for (const { name, input, expected } of cases) {
    test(name, () => {
        const result = calculateTotals(input.lines, input.discount);
        assert.deepEqual(strings(result), strings(expected));
    });
}

test('toMinor / formatMinor', () => {
    assert.equal(toMinor('1,250.5', 2), 125050n);
    assert.equal(toMinor('١٢٥٠٫٥', 2), 125050n);
    assert.equal(toMinor('1.005', 2), 0n);
    assert.equal(formatMinor(125050n, 2), '1,250.50');
    assert.equal(formatMinor(12345n, 3), '12.345');
    assert.equal(formatMinor(5n, 2), '0.05');
});
