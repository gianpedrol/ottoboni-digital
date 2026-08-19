# FOLLOWUP EXECUTOR — workflow novo no n8n

> **Não mexa** nos workflows `DUDA - OTTOBONI` e `LUNA - DRA VANESSA V2` —
> eles atendem em produção. Este é um terceiro workflow, só para executar
> follow-ups que o painel dispara.

O painel já faz a parte dele: agenda, verifica cancelamento, respeita
janela de horário e limites, e **executa sozinho** os passos de texto
fixo via tarefa/Salesbot do Kommo. O n8n só entra quando o passo usa
**canal automático, Instagram ou texto gerado por IA**.

## Variáveis (dos dois lados)

No `.env` do painel:

```env
N8N_FOLLOWUP_URL=https://<seu-n8n>/webhook/followup-executor
N8N_WEBHOOK_SECRET=<segredo forte, o mesmo dos dois lados>
```

## O que o painel envia (contrato — não mudar sem conversar)

`POST {N8N_FOLLOWUP_URL}` com header `X-Signature: sha256=<hmac>`:

```json
{
  "run_id": 1234,
  "conta": "luna",
  "lead_id": 987654,
  "ig_username": "fulana",
  "canal": "auto",
  "modo": "ia",
  "texto": "Oi! Passando pra saber se ficou alguma dúvida 🌿",
  "prompt_ia": "Retomar a conversa sobre o procedimento que ela perguntou, sem citar valores.",
  "contexto": {
    "procedimento": "Mommy Makeover",
    "temperatura": "Morno",
    "ultima_etapa": "EM ATENDIMENTO",
    "dias_sem_resposta": 3
  },
  "callback_url": "https://painel.../api/followup/callback",
  "enviado_em": "2026-08-19T14:00:00-03:00"
}
```

- `texto` é o texto fixo do passo — no modo `ia` ele é o **template de
  fallback** quando a validação reprova o texto gerado.
- O HMAC é `sha256` do **corpo cru** com o segredo compartilhado.

## Nós do workflow

1. **Webhook** (POST, responder imediatamente com 202)
2. **Code — Valida HMAC** (abaixo). Assinatura errada → para tudo.
3. **Code — Resolve Conta**: escolhe a CONFIG da `duda` ou da `luna` pelo
   campo `conta` (token do Instagram, tabela de memória, persona).
4. **Supabase**: lê `duda_memoria`/`luna_memoria` e calcula **horas desde
   a última mensagem da pessoa**.
5. **IF janela 24h** (`horas < 24` e `ig_username` presente e canal
   `auto` ou `instagram`):
   - **Sim → ramo Instagram**: mesmo padrão do nó `Enviar DM IG` (Graph
     API). `canal_usado = "instagram"`.
   - **Não → ramo Kommo**: se a conta tem Salesbot →
     `POST /api/v4/bots/{bot_id}/run`; senão → `POST /api/v4/tasks`.
     `canal_usado = "kommo_bot"` ou `"kommo_task"`.
     Se o canal pedido era `instagram` (fixo) e a janela fechou, **não
     envia por outro canal**: devolve `status = "fora_da_janela"`.
6. **IF modo = ia**: gera o texto com a persona da conta e **passa pela
   mesma ancoragem** do nó de comentário (vocabulário seguro + dados do
   próprio lead). Reprovou → usa o `texto` do payload e marca
   `origem_texto = "template"`. Aprovou → `origem_texto = "ia_ancorada"`.
7. **HTTP Request — callback** assinado para `callback_url` (abaixo).

## Código do nó "Valida HMAC" (Code node, JavaScript)

```javascript
const crypto = require('crypto');

const secret = $env.N8N_WEBHOOK_SECRET; // configure no n8n
const corpo = $input.first().json.body ?? JSON.stringify($input.first().json);
const recebida = $input.first().json.headers?.['x-signature'] ?? '';

const esperada = 'sha256=' + crypto
  .createHmac('sha256', secret)
  .update(typeof corpo === 'string' ? corpo : JSON.stringify(corpo))
  .digest('hex');

if (!crypto.timingSafeEqual(Buffer.from(esperada), Buffer.from(recebida))) {
  throw new Error('Assinatura HMAC inválida — requisição descartada.');
}

return $input.all();
```

> Atenção: no Webhook node, ative "Raw body" para o HMAC ser calculado
> sobre o corpo cru, byte a byte.

## O callback que o painel espera

`POST {callback_url}` com header `X-Signature: sha256=<hmac do corpo>`:

```json
{
  "run_id": 1234,
  "status": "enviado",
  "canal_usado": "instagram",
  "texto_final": "Oi Fulana! Passando pra saber se ficou alguma dúvida sobre o Mommy Makeover 🌿",
  "origem_texto": "ia_ancorada",
  "erro": null
}
```

- `status`: `enviado` | `falhou` | `fora_da_janela` | `cancelado`
- `canal_usado`: `instagram` | `kommo_bot` | `kommo_task`
- `origem_texto`: `ia_ancorada` | `template` | `fixo`

Regras do lado do painel (já implementadas e testadas):

- **Sem assinatura válida → 401.** O log fica em `webhook_logs`.
- **Idempotente por `run_id`**: o primeiro callback resolve o run; os
  repetidos são registrados e ignorados (`{"ignorado": "run já resolvido"}`).
- `fora_da_janela` marca o run como falhou com o motivo
  "fora da janela de 24h do Instagram" — critério de aceite 7.

## Assinando o callback no n8n (Code node antes do HTTP Request)

```javascript
const crypto = require('crypto');

const payload = {
  run_id: $json.run_id,
  status: $json.status,
  canal_usado: $json.canal_usado,
  texto_final: $json.texto_final,
  origem_texto: $json.origem_texto,
  erro: $json.erro ?? null,
};

const corpo = JSON.stringify(payload);

return [{
  json: {
    corpo,
    assinatura: 'sha256=' + crypto
      .createHmac('sha256', $env.N8N_WEBHOOK_SECRET)
      .update(corpo)
      .digest('hex'),
  },
}];
```

No HTTP Request: método POST, body = `{{ $json.corpo }}` (raw, JSON),
header `X-Signature` = `{{ $json.assinatura }}`.

## Pergunta em aberto (não implementar sem alinhar)

O contrato não prevê o n8n avisar o painel quando o lead **responde**
depois do envio. Hoje isso entra no painel por três caminhos: o lead
mudou de etapa (cancela os próximos passos antes do envio), tarefa
humana aberta, ou o botão "Lead respondeu" na tela de Execuções.
Se quisermos automatizar (os workflows das agentes avisarem o painel),
é uma **extensão do contrato** — decidir juntos antes.
