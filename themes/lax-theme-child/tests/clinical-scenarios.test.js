// Run: node --test themes/lax-theme-child/tests/clinical-scenarios.test.js
// Simulated visits. Checks emergency routing and top-3 membership.
'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const E = require('../assets/js/symptom-checker-engine.js');

const dataDir = path.join(__dirname, '..', 'data', 'symptom-checker');
const vocab = JSON.parse(fs.readFileSync(path.join(dataDir, 'vocab.json'), 'utf8'));
const diseases = JSON.parse(fs.readFileSync(path.join(dataDir, 'diseases.json'), 'utf8')).diseases;
const scenarios = JSON.parse(fs.readFileSync(path.join(__dirname, 'clinical-scenarios.json'), 'utf8')).scenarios;

function runVisit(sc) {
  const answers = { region: sc.region, redFlags: {}, tags: {} };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = sc.red_flags_yes.indexOf(rf.id) !== -1 ? 'yes' : 'no'; });
  let guard = 0;
  let step = E.nextStep(vocab, diseases, answers);
  while (step.type !== 'result' && guard < 60) {
    guard += 1;
    if (step.type === 'tag') {
      answers.tags[step.tag.id] = sc.tags_yes.indexOf(step.tag.id) !== -1 ? 'yes' : 'no';
    } else {
      answers.redFlags[step.question.id] = 'no';
    }
    step = E.nextStep(vocab, diseases, answers);
  }
  return step.result;
}

for (const sc of scenarios) {
  test(`${sc.id}: ${sc.description}`, () => {
    const res = runVisit(sc);
    assert.strictEqual(res.emergency, sc.expected_emergency);
    if (sc.expected_top3_any) {
      const ids = res.candidates.map((c) => c.id);
      assert.ok(sc.expected_top3_any.some((e) => ids.indexOf(e) !== -1),
        `top candidates ${ids.join(',')} miss all of ${sc.expected_top3_any.join(',')}`);
    }
  });
}
