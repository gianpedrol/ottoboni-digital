# Workflow "IA ENVIO APROVADO" (n8n) — nós, na ordem

Recebe do painel o que um humano aprovou, envia no Instagram, ajusta a memória
da conversa e confirma no painel. Ativado em 11/09/2026 com a configuração no
nó `CONFIG ENVIO` (o n8n é Community: não tem Variables).

```
Webhook Aprovacao (POST /webhook/ia-envio-aprovado, raw body)
   → CONFIG ENVIO (valores copiados do CONFIG da Luna; só o segredo é colado)
   → Valida HMAC (401 lógico: erro = execução falha, nada é enviado)
   → Envia e Confirma (Graph API + materiais + memória + callback assinado)
   → Responde 200

Webhook Cards (POST /webhook/ia-cards, raw body)
   → CONFIG ENVIO → Valida HMAC Cards → Le Cards (luna_cards no Supabase) → Responde Cards
```

Os dois ramos passam pelo mesmo `CONFIG ENVIO`; cada `Valida HMAC` só segue
quando o webhook do seu ramo disparou (`$('Webhook X').isExecuted`).

Payload que o painel manda (ver `EnviarRespostaAprovadaJob`): além de
`comentario`/`dm`, vêm `rascunho_dm`, `mensagem_texto`, `comentario_texto`,
`intent` e `materiais: [{codigo, nome, tipo, url}]` já resolvidos em URL.

## CONFIG ENVIO

```javascript
// >>> EDITE AQUI <<<
// IA_WEBHOOK_SECRET: Configuracao da IA > "Ligacao com o n8n e com o cron" (mesmo valor
// que esta no no 'Extrai Comentario Meta' do fluxo da Luna v2).
// Os demais valores foram copiados do no CONFIG do fluxo da Luna.
return [{ json: {
  IA_WEBHOOK_SECRET: 'COLE_AQUI_O_IA_WEBHOOK_SECRET',
  PAINEL_URL: 'https://demo-ottoboni.gianfrancopedrol.com.br/api/ia',
  IG_GRAPH: '...', IG_VERSION: '...', IG_TOKEN: '...',
  SUPABASE_URL: '...', SUPABASE_KEY: '...', SUPABASE_TABELA: 'luna_memoria', SUPABASE_CONTA: 'luna',
}}];
```

## Valida HMAC

```javascript
// Sem assinatura valida, nada acontece. Mesmo contrato do FOLLOWUP EXECUTOR.
const crypto = require('crypto');
const cfg = $('CONFIG ENVIO').first().json;
let SECRET = ''; try { SECRET = $env.IA_WEBHOOK_SECRET || ''; } catch (e) {}
if (!SECRET) SECRET = String(cfg.IA_WEBHOOK_SECRET || '');
if (!SECRET || /COLE_AQUI/.test(SECRET)) { throw new Error('IA_WEBHOOK_SECRET nao configurado no no CONFIG ENVIO.'); }

const item = $('Webhook Aprovacao').first().json;
const corpo = typeof item.body === 'string' ? item.body : JSON.stringify(item.body ?? item);
const recebida = (item.headers && (item.headers['x-signature'] || item.headers['X-Signature'])) || '';
const esperada = 'sha256=' + crypto.createHmac('sha256', SECRET).update(corpo).digest('hex');

const a = Buffer.from(esperada);
const b = Buffer.from(String(recebida));
if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
  throw new Error('Assinatura HMAC invalida - requisicao descartada.');
}

const dados = typeof item.body === 'string' ? JSON.parse(item.body) : (item.body ?? item);
return [{ json: dados }];
```

## Envia e Confirma

```javascript
// Envia no Instagram o que o humano aprovou, ajusta a memoria da conversa e
// confirma no painel. O painel NUNCA toca no token do Instagram.
const crypto = require('crypto');
const rec = $input.first().json;
const cfg = $('CONFIG ENVIO').first().json;
const env = (k) => { try { return $env[k] || ''; } catch (e) { return ''; } };

const GRAPH = (env('IG_GRAPH') || cfg.IG_GRAPH || 'https://graph.instagram.com') + '/' + (env('IG_VERSION') || cfg.IG_VERSION || 'v23.0');
const TOKEN = env('IG_TOKEN_LUNA') || cfg.IG_TOKEN;
const PAINEL = env('PAINEL_URL') || cfg.PAINEL_URL;
const SECRET = env('IA_WEBHOOK_SECRET') || cfg.IA_WEBHOOK_SECRET;

const erros = [];
let comentarioEnviado = false;
let dmEnviado = false;
let igsid = String(rec.ig_id || '');
let mid = '';

if (rec.comentario && rec.comment_id) {
  try {
    await this.helpers.httpRequest({ method: 'POST', url: GRAPH + '/' + rec.comment_id + '/replies', qs: { message: rec.comentario, access_token: TOKEN }, json: true, timeout: 15000 });
    comentarioEnviado = true;
  } catch (e) { erros.push('comentario: ' + (e.message || e)); }
}

// direct: por comment_id quando veio de comentario, por id quando e conversa.
// Caminho /me/messages = a conta profissional dona do token (API do Instagram).
if (rec.dm && rec.ig_id) {
  try {
    const resp = await this.helpers.httpRequest({
      method: 'POST', url: GRAPH + '/me/messages', qs: { access_token: TOKEN },
      body: { recipient: (rec.canal === 'comentario' && rec.comment_id) ? { comment_id: rec.comment_id } : { id: rec.ig_id }, message: { text: rec.dm } },
      json: true, timeout: 15000,
    });
    dmEnviado = true;
    if (resp && resp.recipient_id) igsid = String(resp.recipient_id); // IGSID de quem comentou
    if (resp && resp.message_id) mid = String(resp.message_id);
  } catch (e) { erros.push('dm: ' + (e.message || e)); }
}

// ---- Materiais aprovados: imagem vai como anexo, documento/video/link vai como mensagem com o endereco ----
const materiaisEnviados = [];
const textosMateriais = [];
if (dmEnviado && igsid && Array.isArray(rec.materiais)) {
  for (const m of rec.materiais) {
    if (!m || !m.url) continue;
    const mensagem = m.tipo === 'imagem'
      ? { attachment: { type: 'image', payload: { url: m.url, is_reusable: true } } }
      : { text: (m.nome ? m.nome + ': ' : '') + m.url };
    try {
      await this.helpers.httpRequest({ method: 'POST', url: GRAPH + '/me/messages', qs: { access_token: TOKEN }, body: { recipient: { id: igsid }, message: mensagem }, json: true, timeout: 20000 });
      materiaisEnviados.push(m.codigo || m.nome);
      if (mensagem.text) textosMateriais.push(mensagem.text);
    } catch (e) { erros.push('material ' + (m.codigo || '') + ': ' + (e.message || e)); }
  }
}

// ---- Memoria: o que saiu de verdade entra no historico; o rascunho segurado sai ----
// Sem isso, o echo da mensagem volta para o fluxo da Luna como se um humano
// tivesse respondido, e ela se cala por 6 horas naquela conversa.
if (dmEnviado && igsid && cfg.SUPABASE_URL && cfg.SUPABASE_KEY) {
  const nrm = (s) => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
  const base = String(cfg.SUPABASE_URL).replace(/\/+$/, '') + '/rest/v1/';
  const hdr = { apikey: cfg.SUPABASE_KEY, Authorization: 'Bearer ' + cfg.SUPABASE_KEY };
  try {
    let hist = [], lead = {};
    try {
      const row = await this.helpers.httpRequest({ method: 'GET', url: base + (cfg.SUPABASE_TABELA || 'luna_memoria'), qs: { ig_id: 'eq.' + igsid, select: 'hist,lead_data', limit: 1 }, headers: hdr, json: true, timeout: 8000 });
      if (Array.isArray(row) && row[0]) { hist = Array.isArray(row[0].hist) ? row[0].hist : []; lead = row[0].lead_data || {}; }
    } catch (eG) {}
    const nRasc = nrm(rec.rascunho_dm || '');
    if (nRasc) { for (let i = hist.length - 1; i >= 0; i--) { const h = hist[i]; if (h && h.role === 'assistant' && nrm(h.content) === nRasc) { hist.splice(i, 1); break; } } }
    if (rec.canal === 'comentario' && rec.comentario_texto) hist.push({ role: 'user', content: '[comentou no post: ' + String(rec.comentario_texto).slice(0, 200) + ']' });
    hist.push({ role: 'assistant', content: rec.dm });
    for (const t of textosMateriais) hist.push({ role: 'assistant', content: t });
    if (lead && lead.aguardando_humano) { lead = Object.assign({}, lead, { aguardando_humano: false }); }
    await this.helpers.httpRequest({ method: 'POST', url: base + (cfg.SUPABASE_TABELA || 'luna_memoria'), headers: Object.assign({ Prefer: 'resolution=merge-duplicates,return=minimal' }, hdr), body: { conta: cfg.SUPABASE_CONTA || 'luna', ig_id: igsid, ig_username: rec.ig_username || null, hist: hist.slice(-30), lead_data: lead, updated_at: new Date().toISOString() }, json: true, timeout: 8000 });
  } catch (eM) { erros.push('memoria: ' + (eM.message || eM)); }

  // O fluxo da Luna tambem reconhece como "dela" o que esta em luna_escalacoes com status enviada.
  try {
    await this.helpers.httpRequest({ method: 'POST', url: base + 'luna_escalacoes', headers: Object.assign({ Prefer: 'return=minimal' }, hdr), body: { conta: cfg.SUPABASE_CONTA || 'luna', thread_id: igsid, ig_user_id: igsid, nome_paciente: rec.ig_username || '', pergunta: String(rec.comentario_texto || rec.mensagem_texto || '').slice(0, 500), contexto: 'painel: aprovacao #' + rec.approval_id + (rec.intent ? ' (' + rec.intent + ')' : ''), motivo: 'aprovado no painel', prioridade: 'normal', status: 'enviada', resposta_enviada: rec.dm, atualizado_em: new Date().toISOString() }, json: true, timeout: 8000 });
  } catch (eE) { /* tabela pode nao ter a coluna; a memoria acima ja cobre */ }
}

const corpo = JSON.stringify({
  approval_id: rec.approval_id,
  status: (comentarioEnviado || dmEnviado) ? 'enviado' : 'erro',
  comentario_enviado: comentarioEnviado,
  dm_enviado: dmEnviado,
  erro: erros.length ? erros.join(' | ') : null,
});

try {
  await this.helpers.httpRequest({ method: 'POST', url: PAINEL + '/envio/callback', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Signature': 'sha256=' + crypto.createHmac('sha256', SECRET).update(corpo).digest('hex') }, body: corpo, json: true, timeout: 10000 });
} catch (e) { erros.push('callback: ' + (e.message || e)); }

return [{ json: { ok: true, approval_id: rec.approval_id, comentarioEnviado, dmEnviado, materiaisEnviados, igsid, mid, erros } }];
```
