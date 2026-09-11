# Painel Ottoboni

Painel web para a clínica Ottoboni acompanhar os atendimentos feitos pelas
agentes de IA **Duda** (Dr. Eduardo) e **Luna** (Dra. Vanessa) no Kommo, e
disparar follow-ups para os leads.

- **Stack:** Laravel 12 · Filament v4 · PHP 8.3 · MySQL 8 · Pest · Larastan
- **Fonte dos dados:** API do Kommo ao vivo (não há espelho do CRM no banco)
- O briefing completo do projeto está na raiz da conversa/documentação
  interna; as regras dos números dos relatórios estão em
  [docs/metricas.md](docs/metricas.md).

## Estado atual — Fases 1, 2 e 3 prontas, mais a área de treinamento das IAs

### Fase 3 — gestão da clínica (protótipo de demonstração)

Agenda própria por médico com regra de conflito e reflexo no Kommo,
pacientes com CPF cifrado e prontuário com registro assinado, financeiro
(tabela de preços, contas a receber e a pagar, extrato OFX e conciliação),
motor de automações A1–A7 com simulador, e dashboard da clínica. Escopo
completo em [docs/escopo-fase-3.md](docs/escopo-fase-3.md). Roda com dados de
demonstração (`PAINEL_PROTOTIPO=true`, selo "Demonstração · dados fictícios").

### Área de treinamento das agentes de IA (painel `/ia`)

Painel separado, com login próprio, para a equipe revisar o que as agentes
querem responder no Instagram **antes** de sair. Fora do `/painel` de
propósito: quem treina a IA não precisa ver atendimentos e relatórios, e o
painel comercial pode ser apresentado sem essa área aparecer.

- **Portão de aprovação** (`PortaoDeAprovacao`): decide interação por
  interação se a agente envia sozinha. Começa em modo treinamento (tudo passa
  por humano) e libera por assunto quando ele prova 30+ revisões, acurácia
  ≥ 90% e zero rejeições na janela das últimas 50. Freio de mão: se a nota
  geral cai abaixo de 85%, tudo volta para a fila automaticamente.
- **"Não sei responder" nunca é liberado**, em nenhum modo. Pergunta no escopo
  sem card na base entra na fila com destaque, a paciente recebe só um direct
  de acolhimento, e a resposta que o humano escrever **vira card validado** —
  na próxima vez a agente responde sozinha. É o ciclo que faz a base crescer.
- **Acurácia medida pela edição humana**, não por auto-avaliação: aprovado sem
  tocar 1,00 · ajuste leve 0,80 · ajuste grande 0,40 · refeita ou rejeitada 0.
  O que saiu automático fica fora da conta — ninguém revisou.
- **Prompts e regras versionados**: publicar cria uma versão nova com autor,
  motivo e aceite de responsabilidade (exigido na camada de serviço, não no
  formulário). Rollback republica uma versão antiga. As 10 regras
  inegociáveis ficam em tabela separada, injetadas sempre no fim do prompt, e
  não têm campo de edição no painel.
- **Modelo em read-only**: fora do `$fillable`, alterável só por
  `php artisan ia:modelo <agente> <modelo>`, com registro na auditoria.
- **O painel não tem o token do Instagram.** Aprovar grava a decisão e
  despacha `EnviarRespostaAprovadaJob`, que chama o n8n com HMAC; o n8n envia e
  confirma no callback. Contrato em
  [docs/n8n-treinamento-ia.md](docs/n8n-treinamento-ia.md).
- **Vigia da fila** (`ia:vigia-fila`, a cada 5 min): avisa pendências no
  sininho, e-mail e push (FCM), e expira o que ficou 24h sem revisão. Nunca
  envia resposta por conta própria.
- **Base da Luna carregada para revisão** (`db:seed --class=LunaBaseSeeder`):
  o prompt que roda hoje no n8n entra como v1 dividida em blocos (Direct e
  comentário), marcada em amarelo como "sem revisão" até alguém publicar a
  v2 com o aceite. Os cards vêm do Supabase por `ia:importar-supabase luna`,
  com a estrutura real (código, módulo, perguntas equivalentes, resposta
  detalhada, status validado/revisar/pendente) — pelo n8n, sem chave do
  Supabase no servidor.
- **Materiais**: a Dra. sobe a foto ou o PDF de cada programa (ou cola o link
  do vídeo) em Materiais, diz quando usar e marca no card do assunto o que vai
  junto. A agente devolve os códigos na resposta, o revisor confere na fila
  (marcado = ela escolheu) e o n8n envia: imagem como anexo, PDF/vídeo/link
  como mensagem com o endereço. O arquivo sai por URL pública com token
  (`/materiais/{token}/{nome}`), sem depender de `storage:link`. Trocar o
  material na revisão vira exemplo aprovado (com o material), e um "não sei"
  respondido vira card já apontando para o documento — os exemplos entram no
  system prompt, então a revisão humana treina a agente de verdade.
- **Instalação sem variáveis extras**: o segredo do webhook e o token do cron
  derivam da `APP_KEY` quando o `.env` não define (`SegredosDoPainel`), e a
  tela Configuração da IA mostra os valores prontos para colar. Hospedagem
  compartilhada sem cron de shell: `GET /cron/{token}` roda o agendador.
- Telas: Fila de aprovação (um item por vez, com atalhos `A`/`R`/`S`),
  Histórico, Prompts e regras, Acurácia, Base de conhecimento, Exemplos,
  Gatilhos e Configuração.

Virada da base: `php artisan ia:importar-supabase luna --dry-run` traz cards e
gatilhos do Supabase. Memória de conversa e echo continuam no Supabase — é
caminho quente de cada mensagem, só o n8n usa.

### Fase 2 — motor de follow-up

- **Réguas** com passos ilimitados (offset em horas, canal, texto fixo ou
  IA com prompt), gatilhos por etapa, temperatura, sem-resposta ou manual.
- **Simulador obrigatório**: renderiza a régua inteira para um lead real
  (datas + textos) sem enviar nada.
- **Agendamento**: `followup:agendar` (varredura horária das réguas
  ativas, idempotente) e `followup:dispatch` (a cada 5 min, enfileira os
  runs vencidos dentro da janela de horário).
- **Execução**: texto fixo via tarefa/Salesbot vai direto na API do Kommo
  (o caminho seguro); IA, Instagram e canal automático vão para o n8n com
  HMAC (contrato em [docs/n8n-followup-executor.md](docs/n8n-followup-executor.md)).
- **Cancelamento automático** verificado imediatamente antes do envio:
  lead ganhou/perdeu, saiu da etapa do gatilho, ou tem tarefa humana
  aberta. Limites: 1 follow-up/lead a cada 24h, 5 por lead no total.
- **Callback** idempotente por `run_id`, 401 sem assinatura válida.
- Telas: Réguas, Simulador, Execuções (com escopo por médico) e ação
  "Follow-up" por linha em Atendimentos.

### Fase 1 — leitura

- `KommoClient` com throttle central (5 req/s), retry com backoff em
  429/5xx, bloqueio total em 403 e log de todas as chamadas.
- Campos personalizados resolvidos **por nome** via
  `GET /leads/custom_fields` (cache 24h) — nunca por ID chutado.
- Tela **Atendimentos** com todos os filtros do briefing; filtros de campo
  personalizado aplicados em memória sobre o cache (trocar filtro não
  dispara chamadas novas).
- **Ficha do atendimento** com notas sob demanda, tarefas e contato.
- **Relatórios** com os 7 blocos, export CSV/XLSX por bloco e PDF completo;
  período acima de 60 dias vira job na fila com notificação.
- **Dashboard** com indicadores, leads por dia e origem.
- Papéis: `admin`, `gestor`, `recepcao` (escopo por médico aplicado no
  filtro de pipeline — comprovado por teste de URL direta).
- 2FA por aplicativo autenticador, auditoria de exportações e fichas,
  retenção de logs de 90 dias.

Fases 2 (follow-up manual) e 3 (IA e canais) ainda não começaram — as
tabelas já existem, o motor não.

## Rodando localmente (WAMP)

```powershell
# dependências
php composer.phar install

# banco (MySQL do WAMP, engine InnoDB forçada no config)
php artisan migrate --seed

# assets do Filament já vêm publicados; suba o servidor:
php artisan serve
```

Acesse `http://localhost:8000/painel`. O seed cria o usuário
`admin@ottoboni.local` (senha em `SEED_ADMIN_PASSWORD` no `.env`, padrão
`trocar-esta-senha` — **troque no primeiro login**).

### .env obrigatório

```env
KOMMO_SUBDOMAIN=ottoboni
KOMMO_TOKEN=            # token de longa duração — NUNCA commitar
DUDA_PIPELINE_ID=6441483
LUNA_PIPELINE_ID=9219740
```

Sem `KOMMO_TOKEN`, o painel abre normalmente e mostra o erro amigável
"KOMMO_TOKEN não configurado" nas telas que consultam a API. Use
**Configurações → Testar conexão Kommo** para validar.

### Fila e agendador

Em produção rode os dois processos:

```bash
php artisan queue:work        # jobs de relatório (e follow-up na Fase 2)
php artisan schedule:work     # retenção de logs; followup:dispatch na Fase 2
```

No Windows/WAMP de desenvolvimento, `php artisan queue:work` num terminal
separado é suficiente (Horizon exige pcntl e não roda no Windows — usar
apenas em produção Linux, se desejado).

## Testes e análise estática

```bash
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
```

A API do Kommo é **sempre mockada** nos testes (fixtures em
`tests/Fixtures/kommo/`). Nada bate em produção.

## Regras do projeto (resumo)

- Nenhum `Http::get()` para o Kommo fora do `KommoClient`.
- Toda tela que mostra dado em cache exibe "dados de HH:MM" e tem botão
  "Atualizar agora".
- Rate limit do Kommo: 7 req/s (trabalhamos com 5). 403 = possível
  bloqueio de IP: o cliente para tudo e loga em nível crítico.
- Dados de pacientes são dado de saúde (LGPD): nada de PII em log,
  exportação restrita a admin/gestor e auditada.
- Perguntar antes de: criar tabela espelhando o Kommo, mudar o contrato do
  webhook, ou enviar mensagem real em ambiente de desenvolvimento.
