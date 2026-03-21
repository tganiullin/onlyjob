import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldTreatAsSpeech, sumSpeechDurationSeconds } from '../useFrontendVad.js';

test('sumSpeechDurationSeconds returns total seconds for valid segments', () => {
    const total = sumSpeechDurationSeconds([
        { start: 0, end: 300 },
        { start: 500, end: 1700 },
    ]);

    assert.equal(total, 1.5);
});

test('sumSpeechDurationSeconds ignores invalid segments', () => {
    const total = sumSpeechDurationSeconds([
        { start: 100, end: 100 },
        { start: 200, end: 150 },
        { start: 'x', end: 900 },
        { start: 0, end: 500 },
    ]);

    assert.equal(total, 0.5);
});

test('shouldTreatAsSpeech respects threshold', () => {
    assert.equal(shouldTreatAsSpeech(0.6, 0.5), true);
    assert.equal(shouldTreatAsSpeech(0.4, 0.5), false);
    assert.equal(shouldTreatAsSpeech(Number.NaN, 0.5), false);
});
