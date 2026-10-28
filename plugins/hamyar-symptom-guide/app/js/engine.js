/* Educational matching only: no clinical scoring, diagnosis or probability. */
class DecisionEngine {
  constructor(db) { this.db = db; }
  isUrgentCase(state) {
    const symptoms = state.selectedSymptoms || [];
    return Number(state.painLevel) >= 9 || this.db.urgentRules.some(rule => (!rule.region || rule.region === state.region) && (!rule.any || rule.any.some(s => symptoms.includes(s))) && (!rule.all || rule.all.every(s => symptoms.includes(s))));
  }
  getFinalResult(state) {
    const symptoms = state.selectedSymptoms || [];
    const resources = this.db.diseases
      .map(d => ({ ...d, matchedSymptoms: d.symptoms.filter(s => symptoms.includes(s)) }))
      .filter(d => d.matchedSymptoms.length > 0)
      .sort((a, b) => b.matchedSymptoms.length - a.matchedSymptoms.length || a.name.localeCompare(b.name, 'fa'))
      .slice(0, 3);
    return { urgent: this.isUrgentCase(state), resources };
  }
}
if (typeof module !== 'undefined') module.exports = DecisionEngine;
