// Patch 2 (11/09/2026): MATERIAIS — a agente escolhe pelo código o que vai
// junto (foto do programa, PDF, link); o painel resolve a URL; o n8n envia.
// Aplicado no editor do n8n via Pinia store, mesmo mecanismo do luna-v2-patch.js.
//
//  1. CONFIG / CONFIG Meta: _PAINEL.materiais; no Direct o FORMATO_SAIDA ganha
//     o campo "materiais" no JSON.
//  2. Parse AI e Duda Comentario: capturam `materiais` da resposta da IA.
//  3. Portões: mandam `materiais` no /rascunho.
//  4. Modo automático: nós novos "Envia Materiais (Direct|Comentario)" mandam
//     imagem como anexo e o resto como link, e registram na memória.

const app = document.querySelector('#app').__vue_app__;
const pinia = app.config.globalProperties.$pinia;
const ws = pinia._s.get('workflows');
const wf = ws.workflow;
const relatorio = [];
const node = (nm) => { const n = wf.nodes.find((x) => x.name === nm); if (!n) throw new Error('nó não encontrado: ' + nm); return n; };
const esc = (t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const rep = (code, antes, depois, rotulo) => {
  const re = new RegExp(esc(antes).replace(/ /g, '[ \\t]+').replace(/\n/g, '\\n[ \\t]*'), 'g');
  const n = (code.match(re) || []).length;
  if (n !== 1) throw new Error(rotulo + ': trecho encontrado ' + n + 'x (esperado 1)');
  return code.replace(re, () => depois);
};
const pendentes = [];
const setCode = (nm, code) => { node(nm); pendentes.push([nm, code]); };

// 1) CONFIG / CONFIG Meta
const ANTES_PAINEL = "cards_ids: (ctxP.cards || []).map((x) => x.id),\n      };";
const DEPOIS_PAINEL = "cards_ids: (ctxP.cards || []).map((x) => x.id),\n        materiais: Array.isArray(ctxP.materiais) ? ctxP.materiais : [],\n      };";
{
  let c = node('CONFIG').parameters.jsCode;
  c = rep(c, ANTES_PAINEL, DEPOIS_PAINEL, 'CONFIG: materiais');
  c = rep(c, "cfg.BASE = cfg.FORMATO_SAIDA; // o contrato tecnico de saida (JSON) continua aqui, fora do painel",
    "if (cfg._PAINEL.materiais.length) {\n        // O JSON de saida ganha o campo materiais (a regra de uso esta no bloco MATERIAIS do prompt).\n        cfg.FORMATO_SAIDA = String(cfg.FORMATO_SAIDA).replace('\"pergunta_escalada\":\"\"}', '\"pergunta_escalada\":\"\",\"materiais\":[]}') + String.fromCharCode(10) + 'materiais: lista com os codigos dos materiais que devem ir junto com a mensagem, somente os listados em MATERIAIS QUE VOCE PODE ENVIAR e so quando o assunto for exatamente aquele; caso contrario, lista vazia. Nunca escreva o endereco do material no reply: o envio e automatico.';\n      }\n      cfg.BASE = cfg.FORMATO_SAIDA; // o contrato tecnico de saida (JSON) continua aqui, fora do painel",
    'CONFIG: formato saida');
  setCode('CONFIG', c);
}
{
  let c = node('CONFIG Meta').parameters.jsCode;
  c = rep(c, ANTES_PAINEL, DEPOIS_PAINEL, 'CONFIG Meta: materiais');
  setCode('CONFIG Meta', c);
}

// 2) Parse AI e Duda Comentario
{
  let c = node('Parse AI').parameters.jsCode;
  c = rep(c, "reply,\nclassification,", "reply,\nmateriais: Array.isArray(ai.materiais) ? ai.materiais.filter((x) => typeof x === 'string').slice(0, 5) : [],\nclassification,", 'parse ai: materiais');
  setCode('Parse AI', c);
}
{
  let c = node('Duda Comentario').parameters.jsCode;
  c = rep(c, "'SAIDA em JSON valido: {\"dm\":\"...\",\"escalar\":false,\"motivo\":\"\"}'",
    "'SAIDA em JSON valido: {\"dm\":\"...\",\"escalar\":false,\"motivo\":\"\",\"materiais\":[]}. materiais: codigos dos materiais listados em MATERIAIS QUE VOCE PODE ENVIAR que devem ir junto, so os do assunto perguntado; senao []. Nunca escreva o endereco do material na dm.'",
    'duda: saida');
  c = rep(c, "let escalarCom = false; let motivoEsc = '';", "let escalarCom = false; let motivoEsc = '';\nlet materiaisCom = [];", 'duda: declara');
  c = rep(c, "if (out && out.escalar !== true && String(out.dm||'').trim()) { texto_dm = _fmtV(String(out.dm).trim()); }",
    "if (out && out.escalar !== true && String(out.dm||'').trim()) { texto_dm = _fmtV(String(out.dm).trim()); materiaisCom = Array.isArray(out.materiais) ? out.materiais.filter((x) => typeof x === 'string').slice(0, 5) : []; }",
    'duda: captura');
  c = rep(c, "_escalar: escalarCom, _motivo_escalar: motivoEsc,", "_escalar: escalarCom, _motivo_escalar: motivoEsc, _materiais: materiaisCom,", 'duda: retorno');
  setCode('Duda Comentario', c);
}

// 3) Portões
{
  let c = node('Portao Comentario').parameters.jsCode;
  c = rep(c, "cards_usados: (cfgP._PAINEL && cfgP._PAINEL.cards_ids) || j.cards_usados || [],\n", "cards_usados: (cfgP._PAINEL && cfgP._PAINEL.cards_ids) || j.cards_usados || [],\nmateriais: Array.isArray(j._materiais) ? j._materiais : [],\n", 'portao com: materiais');
  setCode('Portao Comentario', c);
}
{
  let c = node('Portao DM').parameters.jsCode;
  c = rep(c, "cards_usados: (cfgP._PAINEL && cfgP._PAINEL.cards_ids) || [],\n", "cards_usados: (cfgP._PAINEL && cfgP._PAINEL.cards_ids) || [],\nmateriais: Array.isArray(pa.materiais) ? pa.materiais : [],\n", 'portao dm: materiais');
  setCode('Portao DM', c);
}

// 4) Nós de envio no modo automático
const enviaMateriais = (configNode, portaoNode, canal) => `// Modo automatico: manda os materiais que a agente escolheu (imagem como anexo,
// o resto como link) e registra na memoria, para o echo nao virar "humano".
// Na fila, quem manda e o workflow IA ENVIO APROVADO — este no nem roda.
const cfg = $('${configNode}').first().json;
const portao = $('${portaoNode}').first().json;
const ex = $('Extrai Comentario Meta').first().json;
const entrada = $input.first().json || {};
const corpoResp = (entrada.body !== undefined) ? entrada.body : entrada;
const codigos = Array.isArray(portao.materiais) ? portao.materiais : (Array.isArray(portao._materiais) ? portao._materiais : []);
const lista = (cfg._PAINEL && Array.isArray(cfg._PAINEL.materiais)) ? cfg._PAINEL.materiais : [];
${canal === 'direct'
  ? "const dest = String(ex.from_id || '');"
  : "const dest = String((corpoResp && corpoResp.recipient_id) || '');"}
const enviados = [];
const textos = [];
for (const codigo of codigos) {
  const m = lista.find((x) => x && x.codigo === codigo);
  if (!m || !m.url || !dest) continue;
  const mensagem = m.tipo === 'imagem'
    ? { attachment: { type: 'image', payload: { url: m.url, is_reusable: true } } }
    : { text: m.nome + ': ' + m.url };
  try {
    await this.helpers.httpRequest({ method: 'POST', url: cfg.IG_GRAPH + '/' + cfg.IG_VERSION + '/' + ex.ig_id + '/messages', qs: { access_token: cfg.IG_TOKEN }, body: { recipient: { id: dest }, message: mensagem }, json: true, timeout: 20000 });
    enviados.push(codigo);
    if (mensagem.text) textos.push(mensagem.text);
  } catch (e) { /* material que falhou nao derruba a resposta */ }
}
if (textos.length) {
  try {
    const sd = $getWorkflowStaticData('global');
    const nrm = (s) => String(s || '').toLowerCase().replace(/\\s+/g, ' ').trim();
    sd.lunaOutAll = Array.isArray(sd.lunaOutAll) ? sd.lunaOutAll : [];
    sd.lunaOut = sd.lunaOut || {};
    sd.hist = sd.hist || {};
    const o = Array.isArray(sd.lunaOut[dest]) ? sd.lunaOut[dest] : [];
    const h = Array.isArray(sd.hist[dest]) ? sd.hist[dest] : [];
    for (const t of textos) { sd.lunaOutAll.push(nrm(t)); o.push(nrm(t)); h.push({ role: 'assistant', content: t }); }
    sd.lunaOutAll = sd.lunaOutAll.slice(-60); sd.lunaOut[dest] = o.slice(-12); sd.hist[dest] = h.slice(-30);
  } catch (eS) {}
}
return [{ json: { ...entrada, materiais_enviados: enviados } }];`;

const novosNos = [];
const novasConexoes = [];
if (!wf.nodes.find((n) => n.name === 'Envia Materiais (Direct)')) {
  const ref = node('Salva MID Luna');
  novosNos.push({ id: crypto.randomUUID(), name: 'Envia Materiais (Direct)', type: 'n8n-nodes-base.code', typeVersion: 2, position: [ref.position[0] + 224, ref.position[1]], parameters: { mode: 'runOnceForAllItems', language: 'javaScript', jsCode: enviaMateriais('CONFIG', 'Portao DM', 'direct') } });
  novasConexoes.push(['Salva MID Luna', 'Envia Materiais (Direct)']);
}
if (!wf.nodes.find((n) => n.name === 'Envia Materiais (Comentario)')) {
  const ref = node('Enviar DM IG');
  novosNos.push({ id: crypto.randomUUID(), name: 'Envia Materiais (Comentario)', type: 'n8n-nodes-base.code', typeVersion: 2, position: [ref.position[0] + 224, ref.position[1]], parameters: { mode: 'runOnceForAllItems', language: 'javaScript', jsCode: enviaMateriais('CONFIG Meta', 'Portao Comentario', 'comentario') } });
  novasConexoes.push(['Enviar DM IG', 'Envia Materiais (Comentario)']);
}

// aplica tudo de uma vez pela API (nós editados + nós novos + conexões)
const nodes = wf.nodes.map((n) => JSON.parse(JSON.stringify(n)));
for (const [nm, code] of pendentes) { nodes.find((n) => n.name === nm).parameters.jsCode = code; relatorio.push(nm + ': ' + code.length + ' chars'); }
for (const n of novosNos) { nodes.push(n); relatorio.push('novo: ' + n.name); }
const connections = JSON.parse(JSON.stringify(wf.connections));
for (const [de, para] of novasConexoes) {
  connections[de] = connections[de] || { main: [[]] };
  connections[de].main[0] = (connections[de].main[0] || []).concat([{ node: para, type: 'main', index: 0 }]);
}
const saved = await ws.updateWorkflow(wf.id, { name: wf.name, nodes, connections, settings: wf.settings, versionId: wf.versionId });
relatorio.push('salvo: ' + saved.versionId);
relatorio.join('\n');
