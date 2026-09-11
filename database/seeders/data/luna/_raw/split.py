"""Divide os textos do nó CONFIG do n8n nos blocos editáveis do painel.
Uso: python3 split.py  (gera ../prompt-v1/*.md). Só documentação de como a v1 nasceu."""
import re, pathlib
raw = pathlib.Path(__file__).parent
out = raw.parent / 'prompt-v1'
out.mkdir(exist_ok=True)

def sections(text):
    parts = re.split(r'^(?=# )', text, flags=re.M)
    return [p.strip('\n') for p in parts if p.strip()]

persona = sections((raw / 'direct-persona.md').read_text(encoding='utf-8'))
base = sections((raw / 'direct-base.md').read_text(encoding='utf-8'))

def pick(secs, *starts):
    got = []
    for s in secs:
        title = s.split('\n', 1)[0]
        if any(title.startswith(st) for st in starts):
            got.append(s)
    return got

blocos = {}
blocos['PERSONA'] = pick(persona, '# FONTE DA VERDADE', '# REGRA ZERO', '# COMO FALAR DA EQUIPE', '# QUEM VOCÊ É')
blocos['ESCRITA'] = pick(persona, '# COMO NÃO PARECER IA', '# A REGRA MAIOR')
blocos['MODOS'] = pick(persona, '# REGRA DA DRA. VANESSA', '# REGRAS DE CONTINUIDADE', '# A PONTE', '# O QUE VOCÊ NÃO INVESTIGA', '# CONVERSA FLUIDA')
blocos['ATENCAO'] = pick(persona, '# PROIBIÇÕES ABSOLUTAS', '# CHECKLIST')

# "ESCRITA:" e "MODOS:" / "PROCEDIMENTOS:" sao subtitulos sem '#'. Tratamos na mao.
conversa = blocos['MODOS'][-1]
if 'ESCRITA:' in conversa:
    antes, escrita = conversa.split('\nESCRITA:', 1)
    blocos['MODOS'][-1] = antes.rstrip()
    blocos['ESCRITA'].append('# ESCRITA' + escrita)

programas = pick(base, '# O VÍDEO', '# O FLUXO DE ABERTURA', '# OS DOIS PROGRAMAS')
dois = programas[-1]
dois, modos = dois.split('\nMODOS:', 1)
programas[-1] = dois.rstrip()
blocos['BASE'] = programas
blocos['MODOS'].append('# MODOS DE CONVERSA' + modos)
blocos['MODOS'] += pick(base, '# TRANSFERÊNCIA PARA A PIETRA', '# FOLLOW-UPS')

queixas = pick(base, '# SCRIPTS POR QUEIXA')[0]
queixas, proc = queixas.split('\nPROCEDIMENTOS:', 1)
blocos['PROCEDIMENTOS'] = [queixas.rstrip(), '# PROCEDIMENTOS' + proc] + pick(base, '# OBJEÇÕES', '# MICRO-SITUAÇÕES', '# SITUAÇÕES ESPECIAIS')

# FORMATO DE SAIDA fica no n8n (contrato tecnico), fora do painel
last = blocos['MODOS'][-1]
if 'FORMATO DE SAIDA' in last:
    blocos['MODOS'][-1] = last.split('\nFORMATO DE SAIDA', 1)[0].rstrip()

blocos['HANDOFF_MSG'] = ['Já estou chamando a Pietra aqui pra te ajudar.']
blocos['COMENTARIO_INSTRUCOES'] = [(raw / 'comentario-instrucoes.md').read_text(encoding='utf-8').strip()]

for k, v in blocos.items():
    (out / f'{k}.md').write_text('\n\n'.join(v).strip() + '\n', encoding='utf-8')
    print(f'{k:22} {len((out / f"{k}.md").read_text(encoding="utf-8")):6} chars, {len(v)} seções')
