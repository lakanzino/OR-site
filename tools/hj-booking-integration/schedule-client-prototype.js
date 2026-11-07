/* Isolated learning prototype; NOT loaded by the plugin or connected to a live API. */
(function (global) {
  'use strict';
  const STORE = 'hj-ci-schedule-pending-key';
  const keyPattern = /^[0-9a-f]{32}$/;
  const days = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
  const validNum = (v, min, max) => Number.isSafeInteger(v) && v >= min && v <= max;
  const keysAre = (obj, keys) => !!obj && typeof obj === 'object' && !Array.isArray(obj) && Object.keys(obj).sort().join('|') === keys.slice().sort().join('|');
  function serialize(payload) {
    if (!keysAre(payload, ['revision','generation','enabled','rows']) || !validNum(payload.revision,0,Number.MAX_SAFE_INTEGER) || !validNum(payload.generation,0,Number.MAX_SAFE_INTEGER) || typeof payload.enabled !== 'boolean' || !Array.isArray(payload.rows) || payload.rows.length > 7) throw new Error('invalid');
    const seen = new Set();
    const rows = payload.rows.map(row => {
      if (!keysAre(row,['day','closed','start','end','duration','buffer','capacity']) || !days.includes(row.day) || seen.has(row.day) || typeof row.closed !== 'boolean' || typeof row.start !== 'string' || typeof row.end !== 'string' || !validNum(row.duration,1,240) || !validNum(row.buffer,0,120) || !validNum(row.capacity,1,10)) throw new Error('invalid');
      seen.add(row.day);
      const pattern = /^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/;
      const start = row.start.length === 5 ? row.start + ':00' : row.start, end = row.end.length === 5 ? row.end + ':00' : row.end;
      if (row.closed ? (row.start !== '' || row.end !== '') : (!pattern.test(row.start) || !pattern.test(row.end) || start >= end)) throw new Error('invalid');
      return {day:row.day,closed:row.closed,start:row.start,end:row.end,duration:row.duration,buffer:row.buffer,capacity:row.capacity};
    });
    return JSON.stringify({revision:payload.revision,generation:payload.generation,enabled:payload.enabled,rows});
  }
  function createScheduleClient({storage, random, api, onState = () => {}, timeoutMs = 15000}) {
    if (!validNum(timeoutMs,1,120000)) throw new Error('invalid timeout');
    let key = null, serialized = null, busy = false, phase = 'idle';
    const emit = value => { phase = value; onState(value, {canRetry:!!key && !!serialized}); };
    const pending = () => emit('uncertain');
    try {
      const existing = storage.getItem(STORE);
      if (existing !== null) {
        if (typeof existing !== 'string' || !keyPattern.test(existing)) emit('storage_error');
        else { key = existing; emit('uncertain'); }
      } else emit('idle');
    } catch (_) { emit('storage_error'); }
    function acquire() { if (busy || phase === 'storage_error') return false; busy = true; return true; }
    async function timed(operation) {
      let timer;
      try {
        return await Promise.race([Promise.resolve().then(operation), new Promise((_,reject) => { timer = setTimeout(() => reject(new Error('timeout')),timeoutMs); })]);
      } finally { clearTimeout(timer); }
    }
    function validReceipt(result) { return !!result && (result.status === 'saved' || result.status === 'replayed' || result.status === 'completed') && validNum(result.revision,1,Number.MAX_SAFE_INTEGER); }
    async function reconcile(result) {
      if (!validReceipt(result)) { pending(); return false; }
      emit('refreshing');
      let current;
      try { current = await timed(() => api.refresh()); } catch (_) { pending(); return false; }
      // A stale read is not sufficient; current revision may legitimately be newer.
      if (!current || !validNum(current.revision,result.revision,Number.MAX_SAFE_INTEGER)) { pending(); return false; }
      try {
        if (storage.getItem(STORE) !== key) { emit('storage_error'); return false; }
        storage.removeItem(STORE);
        if (storage.getItem(STORE) !== null) { emit('storage_error'); return false; }
      } catch (_) { emit('storage_error'); return false; }
      key = null; serialized = null; emit('resolved'); return true;
    }
    async function submit() {
      emit('sending');
      try { return await reconcile(await timed(() => api.submit({key, payload:JSON.parse(serialized)}))); }
      catch (_) { pending(); return false; }
    }
    return {
      getState: () => phase,
      async start(payload) {
        if (key !== null || phase !== 'idle' || !acquire()) return false;
        try {
          let candidate, body;
          try { body = serialize(payload); } catch (_) { emit('invalid'); return false; }
          try {
            const bytes = new Uint8Array(16); random.getRandomValues(bytes);
            candidate = Array.from(bytes, n => n.toString(16).padStart(2,'0')).join('');
            if (!keyPattern.test(candidate)) throw new Error('invalid key');
          } catch (_) { emit('storage_error'); return false; }
          try {
            // Do not overwrite a pending or tampered value from another handler.
            if (storage.getItem(STORE) !== null) { emit('storage_error'); return false; }
            storage.setItem(STORE,candidate);
            if (storage.getItem(STORE) !== candidate) { emit('storage_error'); return false; }
          } catch (_) { emit('storage_error'); return false; }
          key = candidate; serialized = body;
          return await submit();
        } finally { busy = false; }
      },
      async retry() {
        if (!key || !serialized || phase !== 'uncertain' || !acquire()) return false;
        try { return await submit(); } finally { busy = false; }
      },
      async check() {
        if (!key || phase !== 'uncertain' || !acquire()) return false;
        try {
          emit('checking');
          try { return await reconcile(await timed(() => api.status(key))); }
          catch (_) { pending(); return false; }
        } finally { busy = false; }
      },
      // Input correction is only possible before a request key exists.
      resetInvalid() { if (!busy && key === null && phase === 'invalid') emit('idle'); }
    };
  }
  const result = {createScheduleClient, STORE};
  if (typeof module === 'object' && module.exports) module.exports = result;
  else global.HJTestScheduleClient = result;
})(globalThis);
