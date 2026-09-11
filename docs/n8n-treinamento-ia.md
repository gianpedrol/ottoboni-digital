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
{ "agente": "luna", "canal": "direct" }
```

`canal` é `direct` (conversa no Direct, padrão) ou `comentario` (resposta
curta a quem comentou num post). Cada canal tem os seus blocos de prompt
(`IaPromptVersion::BLOCOS_POR_CANAL`); a versão publicada é a mesma.

Resposta (campos que interessam):

| campo | para quê |
|---|---|
| `system_prompt` | pronto para ir ao modelo: blocos do canal + **regras inegociáveis no fim** |
| `prompt_revisado` | `false` = versão importada do n8n, ainda sem aceite humano (o painel avisa em amarelo) |
| `blocos` | os blocos separados, se o fluxo quiser montar o prompt do seu jeito |
| `guardrails` | as regras que não se negociam, em lista |
| `exemplos` | few-shot aprovado pela equipe: `{pergunta, resposta, evitar, materiais}` — também entram no `system_prompt` (bloco EXEMPLOS APROVADOS PELA EQUIPE) |
| `cards` | base de conhecimento: `{id, codigo, modulo, categoria, pergunta, perguntas_equivalentes, resposta, resposta_detalhada, status, tags}` |
| `cards_texto` | o bloco "CARDS OFICIAIS…" **no formato exato** que o fluxo já montava a partir do Supabase — o n8n só troca a fonte; cada card traz "Materiais para enviar junto" |
| `materiais` | o que a agente pode mandar: `{codigo, nome, tipo, url, quando_usar}`; o `system_prompt` ganha o bloco "MATERIAIS QUE VOCÊ PODE ENVIAR" e a IA devolve os códigos em `materiais` na saída |
| `modelo` | o modelo que a agente deve usar |
| `prompt_version_id` | manda de volta no `/rascunho` para rastrear qual versão gerou o texto |

Se o painel estiver fora do ar, **use a CONFIG estática como fallback** — o
fluxo não pode parar de receber por causa do painel. O contrato técnico de
saída (o bloco "FORMATO DE SAIDA" com o JSON que o `Parse AI` espera) fica no
n8n, fora do painel: não é coisa de a clínica editar.

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
  "materiais": ["raiz_foto"],
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

Código completo em [n8n/ia-envio-aprovado.md](n8n/ia-envio-aprovado.md).
Além de enviar e confirmar, ele **ajusta a memória da conversa no Supabase**
(tira o rascunho que o fluxo gravou antes do portão e grava o que saiu de
verdade). Sem isso o echo da mensagem aprovada volta para o fluxo da Luna
como se um humano tivesse respondido, e ela se cala por 6 horas.

O mesmo workflow expõe o webhook `ia-cards`: o painel pede por ele os cards
do Supabase na importação inicial (`php artisan ia:importar-supabase luna`),
então a chave do Supabase nunca precisa ir para o servidor do painel.

## O que mudou no fluxo da Luna v2

Patch aplicado nó a nó em [n8n/luna-v2-patch.js](n8n/luna-v2-patch.js) — cada
trecho substituído está lá, com o motivo. Resumo:

- **Extrai Comentario Meta** é o único lugar de configuração: `PAINEL_URL` e
  `PAINEL_SECRET` (o n8n é Community, não tem Variables; `$env` continua tendo
  prioridade se um dia for configurado no Docker).
- **CONFIG** (Direct) e **CONFIG Meta** (comentário) chamam `/api/ia/contexto`
  e trocam PERSONA/BASE pelo prompt publicado, o modelo e os cards do painel.
  Painel fora do ar = CONFIG estática, como antes.
- **Duda Comentario** parou de mandar a DM direto: quem decide é o portão.
  Sem card para a dúvida vira `intent: nao_sei` (fila + notificação), em vez
  de Telegram ou texto fixo.
- **Portao Comentario** manda também permalink, legenda e mídia do post
  (Graph API) para o revisor ver o contexto. **Portao DM** usa os campos reais
  do nó Normalize (`igId`, `igUsername`, `message`, `hist`).
- **DM de espera** (nos dois canais) entra na memória; no Direct, o rascunho
  segurado sai da memória — o `Parse AI` grava o histórico antes do portão.
- **Materiais** ([n8n/luna-v2-materiais-patch.js](n8n/luna-v2-materiais-patch.js)):
  a IA devolve `materiais` (códigos) na saída, o portão manda ao painel, o
  revisor confere na fila; no modo automático os nós "Envia Materiais" mandam
  imagem como anexo e o resto como link (Instagram não aceita PDF anexado).

Sobra do passado que não foi mexida: o nó **CONFIG Meta** carrega uma
PERSONA/BASE do Dr. Ottoboni que nenhum nó lê. Limpar quando der.

## Configurar (o mínimo)

1. Painel: `Configuração da IA` › "Ligação com o n8n e com o cron" mostra
   `PAINEL_URL`, o `IA_WEBHOOK_SECRET` (derivado da `APP_KEY` se o `.env`
   não definir) e a URL do cron.
2. n8n, fluxo "LUNA - DRA VANESSA v2 (PAINEL)": nó `Extrai Comentario Meta`,
   colar o segredo em `PAINEL_SECRET`.
3. n8n, fluxo "IA ENVIO APROVADO": nó `CONFIG ENVIO`, colar o mesmo segredo
   em `IA_WEBHOOK_SECRET`. Ativar o workflow.
4. Servidor: `php artisan db:seed --class=LunaBaseSeeder --force` (prompt v1,
   regras, gatilho) e `php artisan ia:importar-supabase luna` (cards, via n8n).
5. Hospedagem: cron chamando a URL do passo 1 a cada minuto.
6. Ativar a v2 (a original já está desligada). Modo treinamento: nada sai
   sem aprovação.

## O que fica no Supabase

Memória de conversa, echo e a trava de "humano no controle" continuam lá: é
caminho quente, só o n8n usa, e não faz sentido acoplar isso à
disponibilidade do painel. O que o humano edita — cards, gatilhos, prompt,
fila, exemplos — passa a viver no MySQL do painel.
