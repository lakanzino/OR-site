/**
 * Symptom checker engine (test space). Pure functions, no DOM.
 *
 * Order of work for every visit:
 *   1. Age and sex are collected first (filters, not evidence).
 *   2. All red-flag questions are asked before anything else. A "yes" is a hard
 *      override: the result is an emergency and no disease ranking is shown.
 *      "unknown" never hides an emergency; it only adds a caution notice.
 *   3. Tag questions: first every tag of the body region the visitor chose
 *      (vocabulary order), then the question that best splits the remaining
 *      candidates.
 *   4. Candidates are diseases that pass the age/sex filter and are rankable
 *      (enough tags to judge). Each candidate's score is the weight of its tags
 *      the visit confirmed, minus a penalty for core tags the visit denied.
 *      Core tags weigh 2, supporting tags weigh 1.
 *
 * Output is a relative match level, not a probability. The engine never
 * claims a diagnosis; it says which known conditions the answers resemble and
 * what is still unconfirmed.
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

  var MAX_QUESTIONS = 15;
  var TOP_N = 3;
  var CORE_WEIGHT = 2;
  var SUPPORT_WEIGHT = 1;
  var DENY_PENALTY = 0.5; // share of a denied core tag's weight subtracted
  var UNEXPLAINED_PENALTY = 1; // share of a confirmed-but-unlisted tag's weight subtracted
  var MIN_CONFIRMED = 2; // a candidate needs at least this many confirmed tags
  var CONFIDENT_SCORE = 0.6;
  var CONFIDENT_MARGIN = 0.15;
  var TEMPERATURE = 4;
  var MATCH_LABELS = {
    high: 'همخوانی بالا با علائم',
    moderate: 'همخوانی نسبی',
    low: 'احتمال ضعیف',
  };

  function weightOf(disease, tag) {
    var role = (disease.tagRoles || {})[tag];
    // red_flag marks a tag that signals urgency; it weighs like a core tag.
    return role === 'core' || role === 'red_flag' ? CORE_WEIGHT : SUPPORT_WEIGHT;
  }

  /** Answers → sets. Only 'yes' and 'no' carry information. */
  function evidenceOf(tagAnswers) {
    var present = new Set();
    var absent = new Set();
    Object.keys(tagAnswers || {}).forEach(function (t) {
      if (tagAnswers[t] === 'yes') present.add(t);
      if (tagAnswers[t] === 'no') absent.add(t);
    });
    return { present: present, absent: absent };
  }

  /** Age and sex are filters. Unknown values never exclude a disease. */
  function passesFilters(disease, answers) {
    if (answers.age && disease.ageGroups && disease.ageGroups.indexOf(answers.age) === -1) {
      return false;
    }
    if (answers.sex && disease.sex && disease.sex !== 'any' && disease.sex !== answers.sex) {
      return false;
    }
    return true;
  }

  /**
   * Score one disease. Returns null when it cannot be a candidate.
   * score = (confirmed weight − penalty for denied core weight) / total weight
   */
  function scoreOne(disease, present, absent) {
    var tags = disease.tags || [];
    if (!tags.length) return null;
    var total = 0;
    var confirmed = [];
    var missingCore = [];
    var deniedCoreW = 0;
    var confirmedW = 0;
    tags.forEach(function (t) {
      var w = weightOf(disease, t);
      total += w;
      if (present.has(t)) {
        confirmed.push(t);
        confirmedW += w;
      } else if (absent.has(t)) {
        if (w === CORE_WEIGHT) deniedCoreW += w;
      } else if (w === CORE_WEIGHT) {
        missingCore.push(t);
      }
    });
    if (confirmed.length < MIN_CONFIRMED) return null;
    // A symptom the visitor has but this disease never lists is evidence against it.
    var unexplainedW = 0;
    present.forEach(function (t) {
      if (tags.indexOf(t) === -1) unexplainedW += CORE_WEIGHT;
    });
    var raw = (confirmedW - DENY_PENALTY * deniedCoreW - UNEXPLAINED_PENALTY * unexplainedW) / total;
    return {
      disease: disease,
      score: Math.max(0, raw),
      confirmed: confirmed,
      missingCore: missingCore,
    };
  }

  /** Rank candidates, highest first. Only rankable, filter-passing diseases. */
  function rank(diseases, answers) {
    var tagAnswers = (answers && answers.tags) || {};
    var ev = evidenceOf(tagAnswers);
    var scored = [];
    diseases.forEach(function (d) {
      if (!d.rankable) return;
      if (!passesFilters(d, answers || {})) return;
      var s = scoreOne(d, ev.present, ev.absent);
      if (s) scored.push(s);
    });
    // Softmax weights give a relative ordering for the question picker only.
    var total = 0;
    scored.forEach(function (s) {
      s.weight = Math.exp(TEMPERATURE * s.score);
      total += s.weight;
    });
    scored.forEach(function (s) {
      s.p = total > 0 ? s.weight / total : 0;
    });
    scored.sort(function (a, b) {
      return b.score - a.score || a.disease.id.localeCompare(b.disease.id);
    });
    return scored;
  }

  function levelOf(score) {
    if (score >= CONFIDENT_SCORE) return 'high';
    if (score >= 0.35) return 'moderate';
    return 'low';
  }

  /** Stop only when one candidate clearly leads. */
  function isConfident(scored) {
    if (!scored.length) return false;
    var top = scored[0];
    var second = scored[1];
    var margin = top.score - (second ? second.score : 0);
    return top.score >= CONFIDENT_SCORE && margin >= CONFIDENT_MARGIN;
  }

  /**
   * The chosen body area's most relevant tags, first. Relevance = how many
   * eligible rankable diseases use the tag. Capped, so the area never crowds
   * out the entropy-based questions that cover other body areas.
   */
  var REGION_FIRST = 4;
  function regionQueue(vocab, diseases, answers, asked) {
    if (!answers.region) return [];
    var eligible = diseases.filter(function (d) {
      return d.rankable && passesFilters(d, answers);
    });
    var count = {};
    eligible.forEach(function (d) {
      (d.tags || []).forEach(function (t) { count[t] = (count[t] || 0) + 1; });
    });
    // Top N by relevance are fixed first; then only the unasked ones among them.
    return (vocab.tags || [])
      .filter(function (t) { return t.region === answers.region && count[t.id]; })
      .sort(function (a, b) { return count[b.id] - count[a.id]; })
      .slice(0, REGION_FIRST)
      .filter(function (t) { return !asked.has(t.id); });
  }

  /**
   * Basis for choosing the next question. Before any disease has enough
   * confirmed tags, every eligible rankable disease counts equally, so the
   * first questions cover the broadest set of conditions.
   */
  function questionBasis(diseases, answers) {
    var scored = rank(diseases, answers);
    if (scored.length) return scored.slice(0, 10);
    var ev = evidenceOf(answers.tags);
    var eligible = diseases.filter(function (d) {
      return d.rankable && passesFilters(d, answers);
    });
    // Nothing reaches the candidate threshold yet: diseases that share at
    // least one confirmed symptom are the next best basis, weighted by overlap.
    var partial = eligible.filter(function (d) {
      return (d.tags || []).some(function (t) { return ev.present.has(t); });
    });
    var pool = partial.length ? partial : eligible;
    if (!pool.length) return [];
    return pool.map(function (d) {
      var w = (d.tags || []).reduce(function (acc, t) {
        return acc + (ev.present.has(t) ? weightOf(d, t) : 0);
      }, 0);
      return { disease: d, p: 1 + w };
    });
  }

  /**
   * Pick the unasked tag whose yes/no answer would split the basis most
   * evenly (highest entropy of mass). Null when nothing helps.
   */
  function nextTag(pool, asked, vocabTags) {
    var best = null;
    var bestH = 0.2;
    (vocabTags || []).forEach(function (tag) {
      if (asked.has(tag.id)) return;
      var mass = 0;
      var norm = 0;
      pool.forEach(function (s) {
        norm += s.p;
        if ((s.disease.tags || []).indexOf(tag.id) !== -1) mass += s.p;
      });
      if (norm <= 0) return;
      var m = mass / norm;
      if (m <= 0 || m >= 1) return;
      var h = -(m * Math.log2(m) + (1 - m) * Math.log2(1 - m));
      if (h > bestH) {
        bestH = h;
        best = tag;
      }
    });
    return best;
  }

  /**
   * Replay all answers and decide the next step.
   * answers: { age, sex, redFlags: {id: 'yes'|'no'|'unknown'}, tags: {id: 'yes'|'no'|'unknown'} }
   * Returns { type: 'redFlag' | 'tag' | 'result', ... }
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
    var asked = new Set(Object.keys(tagAnswers));
    var scored = rank(diseases, answers);

    if (isConfident(scored) || asked.size >= MAX_QUESTIONS) {
      return { type: 'result', result: buildResult(vocab, diseases, answers, []) };
    }
    // The main body area the visitor chose is asked first, in vocabulary order.
    var regionTags = regionQueue(vocab, diseases, answers, asked);
    if (regionTags.length) {
      return { type: 'tag', tag: regionTags[0] };
    }
    var tag = nextTag(questionBasis(diseases, answers), asked, vocab.tags || []);
    if (!tag) {
      return { type: 'result', result: buildResult(vocab, diseases, answers, []) };
    }
    return { type: 'tag', tag: tag };
  }

  function buildResult(vocab, diseases, answers, emergencyIds) {
    var redAnswers = answers.redFlags || {};
    var unknownRed = (vocab.redFlags || []).some(function (rf) {
      return redAnswers[rf.id] === 'unknown';
    });

    if (emergencyIds.length) {
      return {
        emergency: true,
        status: 'emergency',
        cautionUnknown: false,
        emergencyIds: emergencyIds,
        candidates: [],
      };
    }

    var labels = {};
    (vocab.tags || []).forEach(function (t) { labels[t.id] = t.label; });
    var ev = evidenceOf(answers.tags);
    var pool = diseases.filter(function (d) { return passesFilters(d, answers); });
    var rankablePool = pool.filter(function (d) { return d.rankable; });
    var scored = rank(diseases, answers);

    var status = 'ok';
    if (!rankablePool.length) status = 'insufficient';
    else if (!scored.length) status = 'nomatch';

    return {
      emergency: false,
      status: status,
      cautionUnknown: unknownRed,
      confident: isConfident(scored),
      askedCount: Object.keys(answers.tags || {}).length,
      unrankableCount: pool.length - rankablePool.length,
      candidates: scored.slice(0, TOP_N).map(function (s) {
        var level = levelOf(s.score);
        return {
          id: s.disease.id,
          nameFa: s.disease.nameFa,
          nameEn: s.disease.nameEn,
          specialty: s.disease.specialty,
          reviewStatus: s.disease.reviewStatus,
          // No numeric probability: the score is relative, not calibrated.
          matchLevel: level,
          matchLabelFa: MATCH_LABELS[level],
          matched: s.confirmed.map(function (t) { return labels[t] || t; }),
          missingCore: s.missingCore.map(function (t) { return labels[t] || t; }),
          symptoms: (s.disease.symptoms || []).slice(),
          whenToSeeDoctor: (s.disease.whenToSeeDoctor || []).slice(),
          nextStep: s.disease.nextStep || '',
          sources: s.disease.sources || [],
        };
      }),
    };
  }

  return {
    MAX_QUESTIONS: MAX_QUESTIONS,
    questionBasis: questionBasis,
    rank: rank,
    nextStep: nextStep,
    passesFilters: passesFilters,
    scoreOne: scoreOne,
    levelOf: levelOf,
    isConfident: isConfident,
  };
});
