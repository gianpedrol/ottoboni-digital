# Contrato n8n ↔ painel — treinamento das agentes de IA

O fluxo do n8n para de decidir sozinho. Ele passa a perguntar ao painel
**duas coisas**: com quais instruções responder, e se pode enviar.

Mesma convenção do [FOLLOWUP EXECUTOR](n8n-followup-executor.md): HMAC-SHA256
do corpo cru no header `X-Signature`, sem assinatura válida é 401 e a tentativa
fica em `webhook_logs`.

```
Webhook da Meta
   │
   ├─ guards atuais (allowlist, echo, humano no controle, 6h de silêncio)
   │
   ├─ POST /api/ia/contexto   ──► prompt publicado + regras + exemplos + cards
   │
   ├─ OpenAI ──► rascunho (comentário público + DM)
   │
   └─ POST /api/ia/rascunho   ──► o portão decide
         │
         ├─ enviar_direto = true  ─► envia e repete a chamada com ja_enviado
         │
         └─ enviar_direto = false ─► NÃO envia. O item já está na fila.
                                     Manda só o DM de espera, se pedido.

                   ── humano aprova no painel /ia ──

   Painel ──► webhook "IA ENVIO APROVADO" (HMAC)
                ├─ responde o comentário no Instagram
                ├─ manda o DM
                └─ POST /api/ia/envio/callback
```

## 1. `POST /api/ia/contexto`

```json
{ "agente": "luna", "intent": "consulta" }
```

Resposta (campos que interessam):

| campo | para quê |
|---|---|
| `system_prompt` | pronto para ir ao modelo: blocos editáveis + **regras inegociáveis no fim** |
| `blocos` | os blocos separados, se o fluxo quiser montar o prompt do seu jeito |
| `guardrails` | as regras que não se negociam, em lista |
| `exemplos` | few-shot aprovado pela equipe: `{pergunta, resposta, evitar}` |
| `cards` | base de conhecimento validada: `{id, pergunta, resposta, tags}` |
| `modelo` | o modelo que a agente deve usar |
| `prompt_version_id` | manda de volta no `/rascunho` para rastrear qual versão gerou o texto |

Se o painel estiver fora do ar, **use a CONFIG estática como fallback** — o
fluxo não pode parar de receber por causa do painel.

## 2. `POST /api/ia/rascunho`

O nó mais importante. Vai **depois** de gerar o texto e **antes** de qualquer
chamada à Graph API.

```json
{
  "agente": "luna",
  "canal": "comentario",
  "intent": "consulta",
  "confianca": 0.85,
  "ig_id": "9988776655",
  "ig_username": "paciente",
  "post_id": "17895...",
  "post_permalink": "https://www.instagram.com/p/...",
  "post_caption": "legenda do post",
  "post_media_url": "https://...jpg",
  "comment_id": "17912...",
  "comentario_texto": "quanto custa a consulta?",
  "mensagem_texto": null,
  "historico": [{ "role": "paciente", "content": "oi" }],
  "rascunho_comentario": "Te chamei no direct! 💛",
  "rascunho_dm": "A consulta custa R$ 900,00...",
  "modelo": "gpt-4.1",
  "prompt_version_id": 7,
  "cards_usados": [4, 11],
  "tokens_prompt": 1840,
  "tokens_resposta": 96
}
```

Resposta:

```json
{
  "enviar_direto": false,
  "motivo": "modo_treinamento",
  "motivo_rotulo": "Modo treinamento",
  "modelo": "gpt-4.1",
  "timeout_min": 30,
  "dm_espera": true,
  "dm_espera_texto": "Oi! Vi sua mensagem 💛 Já estou verificando...",
  "approval_id": 412,
  "ja_na_fila": false,
  "enviar_dm_espera": true
}
```

Regras que o fluxo precisa respeitar:

- **`enviar_direto: false` significa não enviar nada.** Nem comentário, nem DM.
  O item já está na fila do painel — não tente reenviar depois.
- `enviar_dm_espera: true` → manda só o DM de acolhimento e confirma em
  `/api/ia/dm-espera`. Não é resposta, é "já te respondo".
- `ja_na_fila: true` → o webhook da Meta repetiu. Não faça nada.
- **Erro ou timeout do painel também é não enviar.** A idempotência é por
  `comment_id`, então repetir a chamada é seguro; enviar sem permissão não é.
- `enviar_direto: true` → envie e **repita a mesma chamada com
  `"ja_enviado": true`** para o histórico registrar o que saiu automático.
  Isso não entra na conta da acurácia (ninguém revisou), mas aparece no painel.

### `motivo` — por que foi para a fila

| motivo | significado |
|---|---|
| `modo_treinamento` | a agente está em treinamento, tudo passa por humano |
| `nao_sei` | pergunta no escopo sem card na base. **Nunca é liberado** |
| `intent_sempre_revisa` | assunto marcado para sempre passar por humano (caminho 3) |
| `amostras_insuficientes` | o assunto ainda não tem revisões suficientes |
| `acuracia_insuficiente` | abaixo do limiar, ou o freio de mão geral disparou |
| `erro_base` | o painel não conseguiu decidir — na dúvida, silêncio |

### Como marcar `intent: "nao_sei"`

É a regra que faz a base crescer. Marque `nao_sei` quando a pergunta está
**dentro** do escopo (teste genético, consulta, queixa de pele) e **nenhum
card** da base cobre a resposta. A resposta que o humano escrever vira card
validado automaticamente, e na próxima vez a agente responde sozinha.

## 3. `POST /api/ia/dm-espera`

```json
{ "approval_id": 412, "enviado": true }
```

## 4. Workflow "IA ENVIO APROVADO"

Recebe do painel quando um humano aprova. Payload:

```json
{
  "approval_id": 412,
  "agente": "luna",
  "ig_user_id": "17841400000000000",
  "canal": "comentario",
  "ig_id": "9988776655",
  "comment_id": "17912...",
  "comentario": "Te chamei no direct! 💛",
  "dm": "O teste genético faz parte do Flor&Ser Pleno..."
}
```

Campo nulo = aquele canal não recebe nada. Depois de enviar, confirme:

`POST /api/ia/envio/callback`

```json
{ "approval_id": 412, "status": "enviado", "comentario_enviado": true, "dm_enviado": true }
```

`status: "erro"` com `erro` preenchido marca o item como erro no painel, onde
a equipe vê e decide o que fazer. O callback é **idempotente por
`approval_id`**: o primeiro resolve, os repetidos devolvem
`{"ignorado": "item já resolvido"}`.

## Nós do workflow "IA ENVIO APROVADO"

1. **Webhook** (POST, **Raw body ativado** — o HMAC é sobre o corpo cru)
2. **Code — Valida HMAC** (mesmo código do FOLLOWUP EXECUTOR, trocando o
   segredo por `$env.IA_WEBHOOK_SECRET`)
3. **Code — Envia e confirma** (abaixo)
4. **Respond to Webhook** (200)

### Código do nó "Envia e confirma"

```javascript
const rec = $input.first().json.body ?? $input.first().json;

const GRAPH = 'https://graph.instagram.com/v23.0';
const TOKEN = $env.IG_TOKEN_LUNA;          // um por agente
const PAINEL = $env.PAINEL_URL;            // https://painel.../api/ia
const SECRET = $env.IA_WEBHOOK_SECRET;
const crypto = require('crypto');

const erros = [];
let comentarioEnviado = false;
let dmEnviado = false;

// resposta pública
if (rec.comentario && rec.comment_id) {
  try {
    await this.helpers.httpRequest({
      method: 'POST',
      url: `${GRAPH}/${rec.comment_id}/replies`,
      qs: { message: rec.comentario, access_token: TOKEN },
      json: true,
      timeout: 15000,
    });
    comentarioEnviado = true;
  } catch (e) { erros.push('comentario: ' + (e.message || e)); }
}

// direct
if (rec.dm && rec.ig_id) {
  try {
    await this.helpers.httpRequest({
      method: 'POST',
      url: `${GRAPH}/${rec.ig_id}/messages`,
      qs: { access_token: TOKEN },
      body: { recipient: { id: rec.ig_id }, message: { text: rec.dm } },
      json: true,
      timeout: 15000,
    });
    dmEnviado = true;
  } catch (e) { erros.push('dm: ' + (e.message || e)); }
}

// confirma no painel, assinado
const corpo = JSON.stringify({
  approval_id: rec.approval_id,
  status: (comentarioEnviado || dmEnviado) ? 'enviado' : 'erro',
  comentario_enviado: comentarioEnviado,
  dm_enviado: dmEnviado,
  erro: erros.length ? erros.join(' | ') : null,
});

await this.helpers.httpRequest({
  method: 'POST',
  url: `${PAINEL}/envio/callback`,
  headers: {
    'Content-Type': 'application/json',
    'X-Signature': 'sha256=' + crypto.createHmac('sha256', SECRET).update(corpo).digest('hex'),
  },
  body: corpo,
  timeout: 10000,
});

return [{ json: { ok: true, comentarioEnviado, dmEnviado, erros } }];
```

### Código do nó "Portão" (no fluxo principal)

```javascript
const crypto = require('crypto');
const PAINEL = $env.PAINEL_URL;
const SECRET = $env.IA_WEBHOOK_SECRET;

const assina = (corpo) =>
  'sha256=' + crypto.createHmac('sha256', SECRET).update(corpo).digest('hex');

const out = [];

for (const item of $input.all()) {
  const j = item.json;

  const payload = {
    agente: 'luna',
    canal: j.evento === 'dm' ? 'direct' : 'comentario',
    intent: j.intent,
    confianca: j.confianca ?? null,
    ig_id: j.ig_id ?? null,
    ig_username: j.ig_username ?? null,
    post_id: j.media_id ?? null,
    post_permalink: j.post_permalink ?? null,
    post_caption: j.post_caption ?? null,
    post_media_url: j.post_media_url ?? null,
    comment_id: j.comment_id ?? null,
    comentario_texto: j.comentario_texto ?? null,
    mensagem_texto: j.mensagem_texto ?? null,
    historico: j._mem?.hist ?? [],
    rascunho_comentario: j.resposta_comentario ?? null,
    rascunho_dm: j.resposta_dm ?? null,
    modelo: j._prompt_modelo ?? null,
    prompt_version_id: j._prompt_version_id ?? null,
    cards_usados: j.cards_usados ?? [],
  };

  const corpo = JSON.stringify(payload);

  let decisao;

  try {
    decisao = await this.helpers.httpRequest({
      method: 'POST',
      url: `${PAINEL}/rascunho`,
      headers: { 'Content-Type': 'application/json', 'X-Signature': assina(corpo) },
      body: corpo,
      json: true,
      timeout: 10000,
    });
  } catch (e) {
    // Painel fora do ar não vira permissão para falar.
    decisao = { enviar_direto: false, motivo: 'erro_base', erro: String(e.message || e) };
  }

  out.push({ json: { ...j, _portao: decisao } });
}

return out;
```

Depois dele, um **IF** em `{{ $json._portao.enviar_direto }}`:

- **true** → segue para "Responder Comentario IG" / "Enviar DM IG", e no fim
  repete a chamada com `ja_enviado: true`
- **false** → se `_portao.enviar_dm_espera`, manda o DM de espera com
  `_portao.dm_espera_texto` e confirma em `/api/ia/dm-espera`; senão, encerra
  em silêncio

## Variáveis no n8n

| variável | o que é |
|---|---|
| `PAINEL_URL` | `https://<painel>/api/ia` |
| `IA_WEBHOOK_SECRET` | o mesmo valor do `.env` do painel |
| `IG_TOKEN_LUNA` / `IG_TOKEN_DUDA` | token da Graph API por agente |

> Hoje o token do Instagram, a chave do Supabase e a da OpenAI estão em texto
> puro no nó CONFIG do fluxo. Migre para variáveis de ambiente / Credentials do
> n8n: qualquer pessoa com acesso à interface lê o nó.

## O que fica no Supabase

Memória de conversa, echo e a trava de "humano no controle" continuam lá: é
caminho quente, só o n8n usa, e não faz sentido acoplar isso à
disponibilidade do painel. O que o humano edita — cards, gatilhos, prompt,
fila, exemplos — passa a viver no MySQL do painel.
