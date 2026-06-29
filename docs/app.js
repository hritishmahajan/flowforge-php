// FlowForge dashboard — dual mode.
//  • LIVE:  served by the PHP backend  → calls the real REST API at /api/*
//  • DEMO:  served by GitHub Pages      → uses an embedded JS engine that mirrors
//           the PHP Engine, so the public link is clickable & fully interactive.
// Same UI, same behaviour — the PHP is the real thing, the mirror keeps Pages live.

const $ = (id) => document.getElementById(id);
let LIVE = false;

// ---- Embedded engine (mirrors lib/Engine.php) used in DEMO mode -------------
const mem = { workflows: [], runs: [] };
const matches = (conds, data) => conds.every((c) => {
  const a = data[c.field], v = c.value;
  switch (c.op) {
    case 'eq': return a == v;
    case 'neq': return a != v;
    case 'gt': return parseFloat(a) > parseFloat(v);
    case 'lt': return parseFloat(a) < parseFloat(v);
    case 'contains': return String(a ?? '').includes(v);
    default: return false;
  }
});
const demo = {
  async workflows() { return mem.workflows; },
  async addWorkflow(wf) { wf.id = Math.random().toString(16).slice(2, 8); mem.workflows.push(wf); return wf; },
  async runs() { return [...mem.runs].reverse(); },
  async ingest(ev) {
    const data = ev.data || {}, runs = [];
    for (const wf of mem.workflows) {
      if (wf.trigger !== ev.type || !matches(wf.conditions, data)) continue;
      const log = wf.actions.map((a) =>
        a.type === 'webhook' ? { type: 'webhook', url: a.url, sent: true, http: 200 }
        : a.type === 'tag' ? { type: 'tag', label: a.label }
        : { type: a.type });
      const run = { id: Math.random().toString(16).slice(2, 8), workflow: wf.name, event_type: ev.type, input: data, actions: log, status: 'success', created_at: new Date().toISOString() };
      mem.runs.push(run); runs.push(run);
    }
    return { event: ev.type, matched: runs.length, runs };
  },
};

// ---- Live backend client ----------------------------------------------------
const api = (path, opts) => fetch('/api' + path, opts).then((r) => r.json());
const live = {
  workflows: () => api('/workflows'),
  addWorkflow: (wf) => api('/workflows', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(wf) }),
  runs: () => api('/runs'),
  ingest: (ev) => api('/events', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(ev) }),
};

let store = demo;

async function detect() {
  try {
    const h = await fetch('/api/health').then((r) => r.json());
    if (h && h.ok) { LIVE = true; store = live;
      $('mode').textContent = `LIVE · PHP ${h.php} · ${h.storage} storage`;
      $('mode').className = 'badge live'; return;
    }
  } catch (_) {}
  $('mode').textContent = 'DEMO mode · embedded JS engine (run with PHP for live API)';
  $('mode').className = 'badge demo';
}

// ---- Rendering --------------------------------------------------------------
async function render() {
  const wfs = await store.workflows();
  $('wf-count').textContent = wfs.length;
  $('workflows').innerHTML = wfs.map((w) => `
    <div class="item"><b>${esc(w.name)}</b> · on <code>${esc(w.trigger)}</code>
      <div class="meta">if ${w.conditions.map((c) => `${esc(c.field)} ${esc(c.op)} ${esc(c.value)}`).join(' and ') || 'always'}
        → ${w.actions.map((a) => `<span class="tag">${esc(a.type)}${a.label ? ':' + esc(a.label) : ''}</span>`).join('')}</div>
    </div>`).join('') || '<div class="item meta">No workflows yet.</div>';

  const runs = await store.runs();
  $('runs').innerHTML = runs.map((r) => `
    <div class="item"><b>${esc(r.workflow)}</b> <span class="ok">✓ ${esc(r.status)}</span>
      <div class="meta">${esc(r.event_type)} · ${new Date(r.created_at).toLocaleTimeString()}</div>
      <pre>${esc(JSON.stringify(r.actions))}</pre></div>`).join('') || '<div class="item meta">No runs yet — fire an event.</div>';
}
const esc = (s) => String(s).replace(/[&<>"]/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));

// ---- Wiring -----------------------------------------------------------------
$('add-wf').onclick = async () => {
  const conditions = $('c-field').value ? [{ field: $('c-field').value, op: $('c-op').value, value: $('c-value').value }] : [];
  const actions = [];
  if ($('a-url').value) actions.push({ type: 'webhook', url: $('a-url').value });
  if ($('a-tag').value) actions.push({ type: 'tag', label: $('a-tag').value });
  await store.addWorkflow({ name: $('wf-name').value, trigger: $('wf-trigger').value, conditions, actions });
  render();
};

$('fire').onclick = async () => {
  let data; try { data = JSON.parse($('e-data').value); } catch { return alert('Payload is not valid JSON'); }
  const res = await store.ingest({ type: $('e-type').value, data });
  render();
  alert(`Event ingested — ${res.matched} workflow(s) matched and ran.`);
};

// Set source link to wherever this is hosted.
$('repo').href = 'https://github.com/hritishmahajan';

(async () => { await detect(); await seed(); render(); })();

// Pre-load one workflow so the dashboard isn't empty on first visit.
async function seed() {
  if ((await store.workflows()).length) return;
  await store.addWorkflow({
    name: 'Flag big EU payments', trigger: 'payment.succeeded',
    conditions: [{ field: 'amount', op: 'gt', value: '1000' }],
    actions: [{ type: 'webhook', url: 'https://httpbin.org/post' }, { type: 'tag', label: 'high-value' }],
  });
}
