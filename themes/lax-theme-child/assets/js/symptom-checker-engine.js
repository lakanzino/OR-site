/**
 * Symptom checker engine (test space). Pure functions, no DOM.
 *
 * Reasoning: abductive. Each disease is a hypothesis explained by its tags.
 * Evidence (yes/no answers to tag questions) raises or lowers hypotheses.
 * The next question is the tag whose answer would split the current
 * probability mass most evenly (maximum entropy), which approximates the
 * information gain of the question.
 *
 * Red flags are asked first and override everything else.
 * "Unknown" answers never count as evidence. Red flags answered "unknown"
 * only add a caution notice; they never hide an emergency.
 *
 * Loaded in the browser as a plain script (window.SymptomEngine) and in node
 * for tests (module.exports).
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.SymptomEngine = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var MAX_QUESTIONS = 12;
  var TEMPERATURE = 4;
  var TOP_N = 3;

  function tagSetOf(disease) {
    return new Set(disease.tags || []);
  }

  /** Score every disease against the current evidence. */
  function scoreDiseases(diseases, present, absent) {
    var scored = [];
    diseases.forEach(function (d) {
      var tags = tagSetOf(d);
      if (tags.size === 0) return;
      var matched = [];
      var denied = 0;
      tags.forEach(function (t) {
        if (present.has(t)) matched.push(t);
        else if (absent.has(t)) denied += 1;
      });
      if (matched.length === 0) return;
      var base = matched.length / Math.sqrt(tags.size + 1);
      var penalty = 1 - 0.15 * Math.min(denied, 3);
      scored.push({ disease: d, matched: matched, score: base * penalty });
    });
    // Softmax turns scores into a probability mass over hypotheses.
    var total = 0;
    scored.forEach(function (s) {
      s.weight = Math.exp(TEMPERATURE * s.score);
      total += s.weight;
    });
    scored.forEach(function (s) {
      s.p = total > 0 ? s.weight / total : 0;
    });
    scored.sort(function (a, b) { return b.p - a.p; });
    return scored;
  }

  /** Confidence band of the leading hypothesis. Conservative by design. */
  function bandOf(top) {
    if (!top) return 'none';
    if (top.p >= 0.5 && top.matched.length >= 3) return 'high';
    if (top.p >= 0.3 && top.matched.length >= 2) return 'medium';
    return 'low';
  }

  /** Pick the unasked tag with the most even split across the hypotheses. */
  function nextTag(scored, present, absent, asked, vocabTags) {
    var best = null;
    var bestEntropy = 0;
    vocabTags.forEach(function (tag) {
      if (asked.has(tag.id)) return;
      var mass = 0;
      scored.forEach(function (s) {
        if (s.disease.tags && s.disease.tags.indexOf(tag.id) !== -1) mass += s.p;
      });
      if (mass <= 0 || mass >= 1) return;
      var h = -(mass * Math.log2(mass) + (1 - mass) * Math.log2(1 - mass));
      if (h > bestEntropy) {
        bestEntropy = h;
        best = tag;
      }
    });
    return bestEntropy > 0.2 ? best : null;
  }

  /**
   * Replay the answers and decide the next step.
   * answers: { region, redFlags: {id: 'yes'|'no'|'unknown'}, tags: {id: 'yes'|'no'|'unknown'} }
   * vocab: parsed vocab.json; diseases: parsed diseases.json array.
   */
  function nextStep(vocab, diseases, answers) {
    var redFlags = vocab.redFlags || [];
    var redAnswers = answers.redFlags || {};

    var pendingRed = redFlags.filter(function (rf) { return !redAnswers[rf.id]; });
    if (pendingRed.length) {
      return { type: 'redFlag', question: pendingRed[0] };
    }

    var emergencyIds = redFlags
      .filter(function (rf) { return redAnswers[rf.id] === 'yes'; })
      .map(function (rf) { return rf.id; });
    if (emergencyIds.length) {
      return { type: 'result', result: buildResult(vocab, diseases, answers, emergencyIds) };
    }

    var tagAnswers = answers.tags || {};
    var present = new Set();
    var absent = new Set();
    Object.keys(tagAnswers).forEach(function (t) {
      if (tagAnswers[t] === 'yes') present.add(t);
      if (tagAnswers[t] === 'no') absent.add(t);
    });
    var asked = new Set(Object.keys(tagAnswers));

    // Ask the chosen region's tags first, in vocabulary order.
    var regionTags = (vocab.tags || []).filter(function (t) {
      return t.region === answers.region && !asked.has(t.id);
    });
    if (regionTags.length) {
      return { type: 'tag', tag: regionTags[0] };
    }

    var scored = scoreDiseases(diseases, present, absent);
    var band = bandOf(scored[0]);
    var askedCount = asked.size;
    if (band === 'high' || askedCount >= MAX_QUESTIONS) {
      return { type: 'result', result: buildResult(vocab, diseases, answers, []) };
    }
    var tag = nextTag(scored, present, absent, asked, vocab.tags || []);
    if (!tag) {
      return { type: 'result', result: buildResult(vocab, diseases, answers, []) };
    }
    return { type: 'tag', tag: tag };
  }

  var MATCH_LABELS = {
    high: 'همخوانی بالا با علائم',
    moderate: 'همخوانی نسبی',
    low: 'احتمال ضعیف',
  };
  function matchLevelOf(matchedCount, tagCount) {
    if (!tagCount) return 'low';
    var share = matchedCount / tagCount;
    if (share >= 0.5) return 'high';
    if (share >= 0.25) return 'moderate';
    return 'low';
  }

  function buildResult(vocab, diseases, answers, emergencyIds) {
    var tagAnswers = answers.tags || {};
    var present = new Set();
    var absent = new Set();
    Object.keys(tagAnswers).forEach(function (t) {
      if (tagAnswers[t] === 'yes') present.add(t);
      if (tagAnswers[t] === 'no') absent.add(t);
    });
    var labels = {};
    (vocab.tags || []).forEach(function (t) { labels[t.id] = t.label; });

    var redAnswers = answers.redFlags || {};
    var unknownRed = (vocab.redFlags || []).some(function (rf) { return redAnswers[rf.id] === 'unknown'; });

    if (emergencyIds.length) {
      return {
        emergency: true,
        cautionUnknown: false,
        emergencyIds: emergencyIds,
        candidates: [],
      };
    }

    var scored = scoreDiseases(diseases, present, absent).slice(0, TOP_N);
    return {
      emergency: false,
      cautionUnknown: unknownRed,
      band: bandOf(scored[0]),
      candidates: scored.map(function (s) {
        return {
          id: s.disease.id,
          nameFa: s.disease.nameFa,
          nameEn: s.disease.nameEn,
          specialty: s.disease.specialty,
          // No numeric probability: the score is relative, not calibrated.
          // Level = share of this disease's tags that the visit matched.
          matchLevel: matchLevelOf(s.matched.length, tagSetOf(s.disease).size),
          matchLabelFa: MATCH_LABELS[matchLevelOf(s.matched.length, tagSetOf(s.disease).size)],
          matched: s.matched.map(function (t) { return labels[t] || t; }),
          symptoms: (s.disease.symptoms || []).slice(),
          whenToSeeDoctor: (s.disease.whenToSeeDoctor || []).slice(),
          sources: s.disease.sources || [],
        };
      }),
    };
  }

  return {
    scoreDiseases: scoreDiseases,
    bandOf: bandOf,
    nextTag: nextTag,
    nextStep: nextStep,
    buildResult: buildResult,
    MAX_QUESTIONS: MAX_QUESTIONS,
  };
});
