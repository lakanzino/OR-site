const assert = require('node:assert/strict');
const db = require('../data/medical_db.json');
const Engine = require('../js/engine.js');
const engine = new Engine(db);
assert.equal(db.diseases.length, 28);
assert.equal(db.encyclopedia.length, 6);
for (const e of db.encyclopedia) { assert.ok(db.diseases.some(d => d.id === e.diseaseId)); for (const key of ['nameFa','nameEn','category','cause','history','mainSymptoms','commonTreatment','reviewStatus','sourceCheckedAt']) assert.ok(e[key], `${e.diseaseId}: ${key}`); for (const source of [...e.clinicalSources,...e.historySources]) assert.equal(new URL(source.url).protocol, 'https:'); assert.ok(e.clinicalSources.length && e.historySources.length); }
const ids = new Set(), selectable = new Set(Object.values(db.symptomCategories).flat());
for (const d of db.diseases) {
  assert.ok(!ids.has(d.id), 'duplicate id: ' + d.id); ids.add(d.id);
  assert.ok(d.sources.length && d.symptoms.length);
  for (const s of d.sources) assert.equal(new URL(s.url).protocol, 'https:');
  for (const symptom of d.symptoms) assert.ok(selectable.has(symptom), `${d.id}: ${symptom} is not selectable`);
}
assert.equal(engine.isUrgentCase({region:'head',selectedSymptoms:['اختلال تکلم']}),true);
assert.equal(engine.isUrgentCase({region:'chest',selectedSymptoms:['عرق سرد']}),true);
assert.equal(engine.isUrgentCase({region:'abdomen',selectedSymptoms:['سفتی شکم']}),true);
assert.equal(engine.isUrgentCase({region:'head',selectedSymptoms:['تب','سفتی گردن']}),true);
assert.equal(engine.isUrgentCase({region:'head',selectedSymptoms:['سفتی گردن']}),false);
assert.equal(engine.isUrgentCase({region:'general',selectedSymptoms:['سرفه خونی']}),true);
assert.equal(engine.isUrgentCase({region:'head',selectedSymptoms:['سردرد'], painLevel:9}),true);
assert.equal(engine.isUrgentCase({region:'head',selectedSymptoms:['سردرد']}),false);
assert.equal(engine.getFinalResult({region:'head',selectedSymptoms:[]}).resources.length,0);
assert.ok(engine.getFinalResult({region:'chest',selectedSymptoms:['سرفه خونی','درد و تورم یک پا']}).resources.some(d=>d.id==='pulmonary_embolism'));
assert.ok(engine.getFinalResult({region:'general',selectedSymptoms:['احساس سرما','یبوست']}).resources.some(d=>d.id==='hypothyroidism'));
console.log('engine and data tests passed');
