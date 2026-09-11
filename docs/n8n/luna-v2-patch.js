// Patch aplicado no console do editor do n8n (via Pinia store) no workflow
// "LUNA - DRA VANESSA v2 (PAINEL)" em 11/09/2026. Guardado aqui como registro
// do que mudou em relação ao fluxo original — cada `rep()` exige que o trecho
// exista exatamente uma vez, então o patch não roda "meio" em silêncio.
//
// O que ele faz:
//  1. Extrai Comentario Meta: ÚNICO lugar para configurar PAINEL_URL e o segredo.
//  2. CONFIG / CONFIG Meta: buscam /api/ia/contexto (prompt publicado + cards);
//     painel fora do ar = segue com a CONFIG estática.
//  3. Normalize e Duda Comentario: cards vêm do painel quando existem.
//  4. Duda Comentario: NÃO manda mais a DM direto; sem card = intent nao_sei.
//  5. Portões e DMs de espera: leem URL/segredo da CONFIG (com fallback $env);
//     DM de espera entra na memória (senão o echo vira "humano no controle").
//  6. Portao Comentario: busca permalink/legenda/mídia do post para o painel.
//  7. Portao DM: usa os campos reais do nó Normalize (igId, igUsername, message, hist).

const app = document.querySelector('#app').__vue_app__;
const pinia = app.config.globalProperties.$pinia;
const ws = pinia._s.get('workflows');
const wf = ws.workflow;
const relatorio = [];

const node = (nm) => { const n = wf.nodes.find((x) => x.name === nm); if (!n) throw new Error('nó não encontrado: ' + nm); return n; };
const esc = (t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
// Tolerante a espacamento: cada espaco do trecho procurado aceita 1+ espacos
// (o codigo tem comentarios alinhados) e cada quebra de linha aceita
// indentacao na linha seguinte.
const rep = (code, antes, depois, rotulo) => {
  const re = new RegExp(esc(antes).replace(/ /g, '[ \\t]+').replace(/\n/g, '\\n[ \\t]*'), 'g');
  const n = (code.match(re) || []).length;
  if (n !== 1) throw new Error(rotulo + ': trecho encontrado ' + n + 'x (esperado 1)');
  return code.replace(re, () => depois); // funcao: o texto novo nunca vira padrao de substituicao
};
// Duas fases: primeiro calcula todos os codigos novos; so aplica no store se
// TODOS os trechos casaram. Nada fica pela metade no editor.
const pendentes = [];
const setCode = (nm, code) => { node(nm); pendentes.push([nm, code]); };
const aplicar = () => {
  for (const [nm, code] of pendentes) {
    const n = node(nm);
    ws.setNodeParameters({ name: nm, value: { ...n.parameters, jsCode: code } });
    relatorio.push(nm + ': ' + code.length + ' chars');
  }
};

// Helper comum: ajusta a memória (Supabase luna_memoria + static data) ao que
// REALMENTE saiu. Tira o rascunho que o fluxo gravou antes do portão (se foi
// segurado, ele nunca foi enviado) e registra o que a Luna acabou de mandar,
// para o echo reconhecer que foi ela e não um humano.
const HELPER_MEMORIA = `
const memoriaLuna = async (cfgM, igIdM, usernameM, entradasM, rascunhoM) => {
  const nrmM = (s) => String(s || '').toLowerCase().replace(/\\s+/g, ' ').trim();
  const nRasc = nrmM(rascunhoM || '');
  const semRascunho = (lista) => {
    if (!nRasc || !Array.isArray(lista)) return Array.isArray(lista) ? lista : [];
    for (let i = lista.length - 1; i >= 0; i--) {
      const e = lista[i];
      if (e && e.role === 'assistant' && nrmM(e.content) === nRasc) { return lista.slice(0, i).concat(lista.slice(i + 1)); }
    }
    return lista;
  };
  try {
    const sdM = $getWorkflowStaticData('global');
    sdM.lunaOutAll = Array.isArray(sdM.lunaOutAll) ? sdM.lunaOutAll : [];
    sdM.lunaOut = sdM.lunaOut || {};
    sdM.hist = sdM.hist || {};
    if (nRasc) {
      sdM.lunaOutAll = sdM.lunaOutAll.filter((t) => t !== nRasc);
      if (igIdM && Array.isArray(sdM.lunaOut[igIdM])) sdM.lunaOut[igIdM] = sdM.lunaOut[igIdM].filter((t) => t !== nRasc);
      if (igIdM) sdM.hist[igIdM] = semRascunho(sdM.hist[igIdM]);
    }
    for (const e of entradasM) {
      if (e.role !== 'assistant' || !e.content) continue;
      sdM.lunaOutAll.push(nrmM(e.content)); sdM.lunaOutAll = sdM.lunaOutAll.slice(-60);
      if (igIdM) { const o = Array.isArray(sdM.lunaOut[igIdM]) ? sdM.lunaOut[igIdM] : []; o.push(nrmM(e.content)); sdM.lunaOut[igIdM] = o.slice(-12); }
    }
    if (igIdM && entradasM.length) { const h = Array.isArray(sdM.hist[igIdM]) ? sdM.hist[igIdM] : []; sdM.hist[igIdM] = h.concat(entradasM).slice(-30); }
  } catch (eS) {}
  if (!igIdM || !cfgM.SUPABASE_URL || !cfgM.SUPABASE_KEY) return;
  const baseM = String(cfgM.SUPABASE_URL).replace(/\\/+$/, '') + '/rest/v1/' + (cfgM.SUPABASE_TABELA || 'luna_memoria');
  const hdrM = { apikey: cfgM.SUPABASE_KEY, Authorization: 'Bearer ' + cfgM.SUPABASE_KEY };
  let histM = [], leadM = {}, existiaM = false;
  try {
    const rowM = await this.helpers.httpRequest({ method: 'GET', url: baseM, qs: { ig_id: 'eq.' + igIdM, select: 'hist,lead_data', limit: 1 }, headers: hdrM, json: true, timeout: 8000 });
    if (Array.isArray(rowM) && rowM[0]) { existiaM = true; histM = Array.isArray(rowM[0].hist) ? rowM[0].hist : []; leadM = rowM[0].lead_data || {}; }
  } catch (eG) {}
  const novoHist = semRascunho(histM).concat(entradasM).slice(-30);
  if (!existiaM && !entradasM.length) return;
  try {
    await this.helpers.httpRequest({ method: 'POST', url: baseM, headers: Object.assign({ Prefer: 'resolution=merge-duplicates,return=minimal' }, hdrM), body: { conta: cfgM.SUPABASE_CONTA || 'luna', ig_id: igIdM, ig_username: usernameM || null, hist: novoHist, lead_data: leadM, updated_at: new Date().toISOString() }, json: true, timeout: 8000 });
  } catch (eP) {}
};
`;

// Helper comum: de onde vêm URL e segredo do painel.
const HELPER_LER = `
const lerPainel = (cfgL, chaveEnv, campo) => {
  let v; try { v = $env[chaveEnv]; } catch (e) {}
  if (v) return String(v);
  if (cfgL && cfgL[campo]) return String(cfgL[campo]);
  try { v = $('Extrai Comentario Meta').first().json[campo === 'PAINEL_URL' ? 'painel_url' : 'painel_secret']; } catch (e) {}
  return v ? String(v) : '';
};
`;

// ---------------------------------------------------------------------------
// 1) Extrai Comentario Meta — único lugar de configuração
// ---------------------------------------------------------------------------
{
  let c = node('Extrai Comentario Meta').parameters.jsCode;
  c = rep(c,
    "// Retorna [] para eventos irrelevantes (echo, leitura, entrega, reacao, duplicados)\n",
    "// Retorna [] para eventos irrelevantes (echo, leitura, entrega, reacao, duplicados)\n" +
    "\n// ---------- PAINEL DE TREINAMENTO (unico lugar para configurar) ----------\n" +
    "// Pegue os dois valores em Configuracao da IA > 'Ligacao com o n8n e com o cron'.\n" +
    "const PAINEL_URL = 'https://demo-ottoboni.gianfrancopedrol.com.br/api/ia';\n" +
    "const PAINEL_SECRET = 'COLE_AQUI_O_IA_WEBHOOK_SECRET';\n",
    'extrai: cabeçalho');
  c = rep(c, "evento: 'comentario',\n", "evento: 'comentario',\npainel_url: PAINEL_URL, painel_secret: PAINEL_SECRET,\n", 'extrai: comentario');
  c = rep(c, "evento: 'dm',\n", "evento: 'dm',\npainel_url: PAINEL_URL, painel_secret: PAINEL_SECRET,\n", 'extrai: dm');
  setCode('Extrai Comentario Meta', c);
}

// ---------------------------------------------------------------------------
// 2) CONFIG e CONFIG Meta — buscam o contexto no painel
// ---------------------------------------------------------------------------
const blocoPainel = (canal, aplicaNoPrompt) => `
// ============================================================================
// PAINEL DE TREINAMENTO (v2): prompt publicado, modelo e cards vem do painel.
// URL e segredo: no 'Extrai Comentario Meta' ($env.PAINEL_URL / $env.IA_WEBHOOK_SECRET
// tem prioridade, se um dia forem configurados no Docker). Painel fora do ar
// = segue com a CONFIG estatica acima; o portao e quem decide se pode enviar.
// ============================================================================
cfg.PAINEL_CANAL = '${canal}';
cfg.FORMATO_SAIDA = (() => { const i = String(cfg.BASE || '').indexOf('FORMATO DE SAIDA'); return i >= 0 ? String(cfg.BASE).slice(i) : ''; })();
${HELPER_LER}
cfg.PAINEL_URL = lerPainel(null, 'PAINEL_URL', 'PAINEL_URL');
cfg.IA_WEBHOOK_SECRET = lerPainel(null, 'IA_WEBHOOK_SECRET', 'IA_WEBHOOK_SECRET');
cfg._PAINEL = null;
if (cfg.PAINEL_URL && cfg.IA_WEBHOOK_SECRET && !/COLE_AQUI/.test(cfg.IA_WEBHOOK_SECRET)) {
  try {
    const cryptoP = require('crypto');
    const corpoP = JSON.stringify({ agente: 'luna', canal: cfg.PAINEL_CANAL });
    const respP = await this.helpers.httpRequest({
      method: 'POST',
      url: cfg.PAINEL_URL + '/contexto',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Signature': 'sha256=' + cryptoP.createHmac('sha256', cfg.IA_WEBHOOK_SECRET).update(corpoP).digest('hex') },
      body: corpoP, json: true, timeout: 8000,
    });
    const ctxP = (typeof respP === 'string') ? JSON.parse(respP) : respP;
    if (ctxP && ctxP.prompt_version_id) {
      cfg._PAINEL = {
        prompt_version_id: ctxP.prompt_version_id, prompt_versao: ctxP.prompt_versao, revisado: !!ctxP.prompt_revisado,
        modelo: ctxP.modelo || null, system_prompt: String(ctxP.system_prompt || ''),
        cards_texto: String(ctxP.cards_texto || ''), cards_ids: (ctxP.cards || []).map((x) => x.id),
      };
      ${aplicaNoPrompt ? `cfg.PERSONA = cfg._PAINEL.system_prompt || cfg.PERSONA;
      cfg.BASE = cfg.FORMATO_SAIDA; // o contrato tecnico de saida (JSON) continua aqui, fora do painel` : `// (o prompt de comentario e aplicado no no 'Duda Comentario')`}
      if (ctxP.modelo) cfg.OPENAI_MODEL = ctxP.modelo;
    }
  } catch (ePainel) { /* painel fora do ar: CONFIG estatica */ }
}
return [{ json: cfg }];
`;
{
  let c = node('CONFIG').parameters.jsCode;
  c = rep(c, 'return [{ json: {', 'const cfg = {', 'CONFIG: abre');
  if (!/\}\}\];\s*$/.test(c)) throw new Error('CONFIG: fim inesperado');
  c = c.replace(/\}\}\];\s*$/, '};\n' + blocoPainel('direct', true));
  setCode('CONFIG', c);
}
{
  let c = node('CONFIG Meta').parameters.jsCode;
  c = rep(c, 'return [{ json: {', 'const cfg = {', 'CONFIG Meta: abre');
  if (!/\}\s*\}\];\s*$/.test(c)) throw new Error('CONFIG Meta: fim inesperado');
  c = c.replace(/\}\s*\}\];\s*$/, '};\n' + blocoPainel('comentario', false));
  setCode('CONFIG Meta', c);
}

// ---------------------------------------------------------------------------
// 3) Normalize — cards do painel quando existem
// ---------------------------------------------------------------------------
{
  let c = node('Normalize').parameters.jsCode;
  c = rep(c, "let baseCards = '';\ntry {", "let baseCards = (cfg._PAINEL && cfg._PAINEL.cards_texto) ? cfg._PAINEL.cards_texto : '';\nif (!baseCards) try {", 'normalize: cards');
  setCode('Normalize', c);
}

// ---------------------------------------------------------------------------
// 4) Duda Comentario — prompt/cards do painel; nao envia DM; sem card = nao_sei
// ---------------------------------------------------------------------------
{
  let c = node('Duda Comentario').parameters.jsCode;
  c = rep(c, "let cardsTxt = '';\ntry {", "let cardsTxt = (cfg._PAINEL && cfg._PAINEL.cards_texto) ? cfg._PAINEL.cards_texto : '';\nif (!cardsTxt) try {", 'duda: cards');
  c = rep(c, "const sysC = 'Voce e a Luna, assistente da equipe",
    "const sysC = (cfg._PAINEL && cfg._PAINEL.system_prompt) ? (cfg._PAINEL.system_prompt + String.fromCharCode(10,10) + 'SAIDA em JSON valido: {\"dm\":\"...\",\"escalar\":false,\"motivo\":\"\"}' + String.fromCharCode(10,10) + cardsTxt) : 'Voce e a Luna, assistente da equipe",
    'duda: sysC');
  c = rep(c, "model: cfg.OPENAI_MODEL || 'gpt-4o'", "model: (cfg._PAINEL && cfg._PAINEL.modelo) || cfg.OPENAI_MODEL || 'gpt-4o'", 'duda: modelo');
  c = rep(c, "if (escalarCom) {\nlet tgOk = false;", "if (escalarCom && cfg._PAINEL) {\n// v2: sem card = nao_sei no portao; o humano responde no painel e vira card.\ntexto_dm = '';\n}\nelse if (escalarCom) {\nlet tgOk = false;", 'duda: escalar telegram');
  c = rep(c, "if (!texto_dm) { texto_dm = _fmtV(_abre + _MEIO[tipoPergunta] + _fecha); }", "if (!texto_dm && !(escalarCom && cfg._PAINEL)) { texto_dm = _fmtV(_abre + _MEIO[tipoPergunta] + _fecha); }", 'duda: fallback fixo');
  c = rep(c, "let _igsid = '';\ntry {\nconst _resp = await", "let _igsid = '';\nif (cfg._PAINEL) {\n// v2: NADA sai daqui. O portao decide e, se liberado, o no 'Enviar DM IG' manda.\nacao = 'duvida';\n}\nelse try {\nconst _resp = await", 'duda: envio direto');
  c = rep(c, "sd.lunaOutAll.push(_nTxt);", "if (_nTxt) sd.lunaOutAll.push(_nTxt);", 'duda: lunaOutAll');
  c = rep(c, "if (escalarCom) {\ntry {\nconst hdrE", "if (escalarCom && !cfg._PAINEL) {\ntry {\nconst hdrE", 'duda: escalacao supabase');
  c = rep(c, 'origem_resposta: "pergunta_direta_fixa",', 'origem_resposta: (cfg._PAINEL ? "painel" : "pergunta_direta_fixa"),\n_escalar: escalarCom, _motivo_escalar: motivoEsc,', 'duda: retorno');
  setCode('Duda Comentario', c);
}

// ---------------------------------------------------------------------------
// 5) Portao Comentario
// ---------------------------------------------------------------------------
{
  let c = node('Portao Comentario').parameters.jsCode;
  c = rep(c, "const PAINEL = $env.PAINEL_URL; // ex.: https://painel.../api/ia\nconst SECRET = $env.IA_WEBHOOK_SECRET;",
    "const cfgP = $('CONFIG Meta').first().json;" + HELPER_LER + "const PAINEL = lerPainel(cfgP, 'PAINEL_URL', 'PAINEL_URL');\nconst SECRET = lerPainel(cfgP, 'IA_WEBHOOK_SECRET', 'IA_WEBHOOK_SECRET');", 'portao com: config');
  c = rep(c, "const ex = $('Extrai Comentario Meta').first().json;\nconst out = [];",
    "const ex = $('Extrai Comentario Meta').first().json;\nconst out = [];\n\n// Post comentado (permalink, legenda, midia) para o revisor ver o contexto no painel.\nlet post = {};\ntry {\n  if (ex.media_id && cfgP.IG_TOKEN) {\n    post = (await this.helpers.httpRequest({ method: 'GET', url: cfgP.IG_GRAPH + '/' + cfgP.IG_VERSION + '/' + ex.media_id, qs: { fields: 'permalink,caption,media_url,thumbnail_url,media_type', access_token: cfgP.IG_TOKEN }, json: true, timeout: 6000 })) || {};\n  }\n} catch (ePost) { post = {}; }", 'portao com: post');
  c = rep(c, "// Sem texto util = ela nao soube responder. Isso nunca e liberado pelo portao.",
    "// O proprio no anterior avisou que nao tinha card para a duvida.\nif (j._escalar) intent = 'nao_sei';\n\n// Sem texto util = ela nao soube responder. Isso nunca e liberado pelo portao.", 'portao com: escalar');
  c = rep(c, "post_id: ex.media_id || null,\n", "post_id: ex.media_id || null,\npost_permalink: post.permalink || null,\npost_caption: post.caption ? String(post.caption).slice(0, 2000) : null,\npost_media_url: post.thumbnail_url || post.media_url || null,\n", 'portao com: post payload');
  c = rep(c, "cards_usados: j.cards_usados || [],\n", "cards_usados: (cfgP._PAINEL && cfgP._PAINEL.cards_ids) || j.cards_usados || [],\nmodelo: (cfgP._PAINEL && cfgP._PAINEL.modelo) || cfgP.OPENAI_MODEL || null,\nprompt_version_id: (cfgP._PAINEL && cfgP._PAINEL.prompt_version_id) || null,\n", 'portao com: modelo');
  setCode('Portao Comentario', c);
}

// ---------------------------------------------------------------------------
// 6) Portao DM — campos reais do Normalize
// ---------------------------------------------------------------------------
{
  let c = node('Portao DM').parameters.jsCode;
  c = rep(c, "const PAINEL = $env.PAINEL_URL;\nconst SECRET = $env.IA_WEBHOOK_SECRET;",
    "const cfgP = $('CONFIG').first().json;" + HELPER_LER + "const PAINEL = lerPainel(cfgP, 'PAINEL_URL', 'PAINEL_URL');\nconst SECRET = lerPainel(cfgP, 'IA_WEBHOOK_SECRET', 'IA_WEBHOOK_SECRET');", 'portao dm: config');
  c = rep(c, "const pa = $('Parse AI').first().json;\nconst out = [];", "const pa = $('Parse AI').first().json;\nlet nz = {};\ntry { nz = $('Normalize').first().json || {}; } catch (eNz) { nz = {}; }\nconst out = [];", 'portao dm: normalize');
  c = rep(c, "const texto = norm(ex.texto || ex.mensagem_texto || ex.comentario_texto || '');", "const texto = norm(nz.message || ex.texto_dm_recebido || ex.texto || '');", 'portao dm: texto');
  c = rep(c, "ig_id: ex.from_id || ex.ig_id || null,\nig_username: ex.username || ex.ig_username || null,\nmensagem_texto: ex.texto || ex.mensagem_texto || null,\nrascunho_dm: j.reply || null,\nhistorico: (pa._mem && pa._mem.hist) || [],",
    "ig_id: nz.igId || ex.from_id || null,\nig_username: nz.igUsername || ex.username || null,\nmensagem_texto: nz.message || ex.texto_dm_recebido || null,\nrascunho_dm: j.reply || null,\nhistorico: Array.isArray(nz.hist) ? nz.hist.slice(-20) : [],\ncards_usados: (cfgP._PAINEL && cfgP._PAINEL.cards_ids) || [],\nmodelo: (cfgP._PAINEL && cfgP._PAINEL.modelo) || cfgP.OPENAI_MODEL || null,\nprompt_version_id: (cfgP._PAINEL && cfgP._PAINEL.prompt_version_id) || null,", 'portao dm: payload');
  setCode('Portao DM', c);
}

// ---------------------------------------------------------------------------
// 7) DMs de espera — config da CONFIG + memoria
// ---------------------------------------------------------------------------
{
  let c = node('DM de Espera (Comentario)').parameters.jsCode;
  c = rep(c, "const PAINEL = $env.PAINEL_URL;\nconst SECRET = $env.IA_WEBHOOK_SECRET;\nconst cfg = $('CONFIG Meta').first().json;",
    "const cfg = $('CONFIG Meta').first().json;" + HELPER_LER + HELPER_MEMORIA + "const PAINEL = lerPainel(cfg, 'PAINEL_URL', 'PAINEL_URL');\nconst SECRET = lerPainel(cfg, 'IA_WEBHOOK_SECRET', 'IA_WEBHOOK_SECRET');", 'espera com: config');
  c = rep(c, "let enviado = false;\ntry {\nawait this.helpers.httpRequest({", "let enviado = false;\nlet igsidEspera = '';\ntry {\nconst respEspera = await this.helpers.httpRequest({", 'espera com: envio');
  c = rep(c, "enviado = true;\n} catch (e) { /* falhar aqui nunca pode quebrar o fluxo */ }",
    "enviado = true;\nigsidEspera = String((respEspera && respEspera.recipient_id) || '');\n} catch (e) { /* falhar aqui nunca pode quebrar o fluxo */ }\n\n// A DM de espera entra na memoria: sem isso o echo dela vira 'humano no controle' por 6h.\nif (enviado) { try { await memoriaLuna(cfg, igsidEspera, ex.username || '', [{ role: 'user', content: '[comentou no post: ' + String(ex.comentario_original || '').slice(0, 200) + ']' }, { role: 'assistant', content: p.dm_espera_texto }], ''); } catch (eM) {} }", 'espera com: memoria');
  setCode('DM de Espera (Comentario)', c);
}
{
  let c = node('DM de Espera (Direct)').parameters.jsCode;
  c = rep(c, "const PAINEL = $env.PAINEL_URL;\nconst SECRET = $env.IA_WEBHOOK_SECRET;\nconst cfg = $('CONFIG').first().json;",
    "const cfg = $('CONFIG').first().json;" + HELPER_LER + HELPER_MEMORIA + "const PAINEL = lerPainel(cfg, 'PAINEL_URL', 'PAINEL_URL');\nconst SECRET = lerPainel(cfg, 'IA_WEBHOOK_SECRET', 'IA_WEBHOOK_SECRET');", 'espera dm: config');
  // O rascunho ja foi gravado na memoria pelo 'Parse AI' antes do portao. Se o
  // portao segurou, ele NAO foi enviado: sai da memoria (com ou sem DM de espera).
  c = rep(c, "if (!p.enviar_dm_espera || !p.dm_espera_texto || !dest) {\nreturn [{ json: { ok: true, pulou: 'sem dm de espera' } }];\n}",
    "const rascunhoSeguro = String($input.first().json.reply || '');\nlet nzE = {};\ntry { nzE = $('Normalize').first().json || {}; } catch (e2) {}\n\nif (!p.enviar_dm_espera || !p.dm_espera_texto || !dest) {\ntry { await memoriaLuna(cfg, dest, nzE.igUsername || '', [], rascunhoSeguro); } catch (eM0) {}\nreturn [{ json: { ok: true, pulou: 'sem dm de espera', rascunho_removido_da_memoria: !!rascunhoSeguro } }];\n}", 'espera dm: rascunho');
  c = rep(c, "enviado = true;\n} catch (e) {}",
    "enviado = true;\n} catch (e) {}\n\n// A DM de espera entra na memoria (e o rascunho sai): sem isso o echo dela vira 'humano no controle' por 6h.\ntry { await memoriaLuna(cfg, dest, nzE.igUsername || '', enviado ? [{ role: 'assistant', content: p.dm_espera_texto }] : [], rascunhoSeguro); } catch (eM) {}", 'espera dm: memoria');
  setCode('DM de Espera (Direct)', c);
}

aplicar();
relatorio.join('\n');
