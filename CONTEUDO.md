# Boticário LMS · De-para do conteúdo "Da Base ao Look Completo"

Análise do material entregue pelo cliente (2026-09-13) e mapeamento para o
engine. Fonte: `boticario/materiais/` (fora do repo).

## Status (2026-09-13, fim do dia)

- ✅ Passo 0: material movido para `materiais/`.
- ✅ Engine: E1 (sequência com imagem + função revelada), E2 (explicação no quiz),
  E3 (áudio por carta + modo domínio na investigação), vídeo em pé e MP4 pelo
  editor (`api/midia` + `.user.ini`). Só em `boticario/` — portar pro Atlântica
  quando fizer sentido.
- ✅ Mídia: `assets/make/` (imagens do cliente renomeadas, stories em WebP, 4 MP3).
  Vídeo comprimido em `materiais/video-make-720p.mp4` (35 MB, 720×1280), já
  enviado pra produção pelo editor (`data/midia`) e gravado na etapa 3.
- ✅ Carga: `_lib/conteudo/servico-make.php` + migração v32 — trilha, treinamento,
  5 fases e 11 etapas entraram em produção no primeiro acesso após o deploy.
- ✅ Mapa provisório `assets/make/mapa-loja.webp` (SVG em `materiais/mapa-loja.svg`).
- ⏳ Imagens em alta e marca oficial: aguardando o cliente.


## O que veio

| Item | Detalhe |
|---|---|
| 1 HTML | `treinamento2_servico_make (2).html` — "Da Base ao Look Completo — Serviço de Maquiagem". Tripa linear de **11 etapas**, barra de progresso (bolinhas), botões "Pular (teste)". Poppins; paleta magenta `#A84E84`, âmbar `#FCCA5B`, navy `#2A4971`, teal `#1CAE8D`. Todas as mídias apontam pro Google Drive (thumbnails w400/w600 e iframes de preview). |
| 25 PNG | 4 stories Boti Beleza (455×821) · 4 estilos de maquiagem (**98×98**) · 7 produtos de pele (~150×185) · 6 passos de olhos (~170×201) · 4 produtos de lábios (~111×108). Tamanhos abaixo do que o HTML exibia (círculos de 156 px vindos do Drive em w600). |
| 4 MP3 | Falas da cliente "Amanda" na simulação (~10 s cada, 0,2 MB). |
| 1 MP4 | "Introdução à categoria de maquiagem: passo a passo de aplicação para vendas" — 4 min 08 s, **58,7 MB**. |

## De-para etapa a etapa

| # | Etapa do cliente | Mecânica no HTML | Tipo no engine | Fidelidade | Observação |
|---|---|---|---|---|---|
| 1 | Abertura | Texto + Continuar | `leitura` (1 parágrafo + 1 confirmação) — ou vira a **apresentação da trilha** | ✅ | O texto de abertura serve de narrativa da trilha. |
| 2 | Os 4 estilos | Flip-cards com imagem; virar as 4 libera | `historia` (4 cenas com imagem + título + texto) ou `caca` (grid de revelação) | ✅ 90 % | Não existe flip-card no engine. `historia` preserva imagem; o selo "Foco da venda" vira texto na cena Social. |
| 3 | Vídeo | iframe do Drive (4 min) | `video` | ✅ | Preview do Drive **não roda** no player. Precisa de URL direta (MP4 hospedado) ou YouTube/Vimeo. Conclui sozinho ao terminar, sem pular. |
| 4 | Aquecimento "quantos produtos?" | Palpite (sem certo/errado) + revelação "16 itens" | `quiz` (1 pergunta, correta "16 ou mais") | ✅ com ajuste | Engine pontua certo/errado. A revelação vira explicação (gap E2). |
| 5 | Pele — 7 produtos | Cartas com imagem; tocar na ordem; acerto revela função | `sequencia` (7 passos) | ⚠️ 70 % | Engine: passos só texto, sem imagem, sem função revelada → **gap E1**. |
| 6 | Olhos — 6 passos | Idem | `sequencia` (6 passos) | ⚠️ 70 % | Idem E1. |
| 7 | Lábios — 4 produtos | Flip-cards (revelar função) | `ligue_pontos` (produto ↔ função) — upgrade gamificado; ou `historia` fiel | ✅ | 4 pares. |
| 8 | Resumida × Completa | Toggle entre 2 textos | `leitura` (2 blocos + confirmações) | ✅ | Upgrade possível: `arrastar` produtos para "versão resumida" / "versão completa". |
| 9 | Relembrando o Boti Beleza | 4 chips → story 9:16 + texto | `historia` (4 cenas com imagem) + opcional `sequencia` (ordenar Acolha → Personalize → Potencialize → Encante) | ✅ | Stories 455×821 servem. |
| 10 | Simulação "Amanda" | 4 nós: áudio + pergunta + 3 opções com feedback **por opção**; errou → escolhe de novo | `investigacao` (4 cartas lineares, feedback por opção) | ⚠️ 80 % | Gaps: **E3** áudio por carta; regra de retry (engine trava após a 1ª escolha e segue — o HTML deixa tentar até acertar). |
| 11 | Quiz final | 5 perguntas, 1 correta, explicação, placar "X de 5" | `quiz` (5 perguntas) | ✅ 90 % | **Gap E2** explicação por pergunta. Placar e nota mínima da fase resolvem o "acertou X de 5". |

## Gaps do engine (código pequeno, antes da carga)

| Gap | O quê | Onde | Estimativa |
|---|---|---|---|
| E1 | `sequencia`: passo aceita `imagem` e `descricao` opcionais; ao acertar, mostra a função (como o HTML). Revisão idem. | `admin/etapas.php` (VAZIOS + form + validação) · `assets/js/player.js` M12 | ~1 h |
| E2 | `quiz`: `explicacao` opcional por pergunta, exibida após responder. | etapas.php · player.js M2 | ~30 min |
| E3 | `investigacao` (e `cenario`): `audio` opcional por carta — player nativo igual ao M14, antes das opções. | etapas.php · player.js M18 (M7) | ~45 min |
| E3b | Decisão de regra: "tentar de novo até acertar" (HTML) × "1 chance + feedback" (engine, conta na nota). Se for retry, ~30 min no M18. | player.js M18 | decisão |
| E4 | Vídeo de 58 MB: `midia.php` só serve imagem/áudio e o upload tem limite. Opções: **A** YouTube não listado (zero infra, melhor player) · **B** FTP direto em `boticario/assets/video/` (público, noindex) · **C** estender `midia.php` pra MP4 + Range e subir por FTP em `data/midia/` (exige sessão). | infra / midia.php | A: 0 · B: 10 min · C: 40 min |
| E5 (opcional) | Tipo novo "Cartas viradas" (flip-card) pra fidelidade total nas etapas 2 e 7. Não recomendo agora: `historia`/`ligue_pontos` cobrem. | player.js M20 | ~1 h 30 |

> Paridade: o engine é compartilhado com `atlantica/`. Implementar aqui e portar
> pro Atlântica depois (ou o inverso) — decisão de manutenção, não bloqueia.

## Estrutura proposta na plataforma

- **Trilha**: "Universo da Maquiagem". Narrativa: *"Da base ao look completo — a jornada de quem transforma atendimento em experiência de beleza."* Mapa: **cenário a produzir** (loja / bancada de maquiagem, isométrico; períodos do dia opcionais). O cliente não enviou arte de mapa.
- **Treinamento**: "Da Base ao Look Completo — Serviço Completo de Make".
- **Fases** (pins no mapa) — 5 fases, 11 etapas 1:1 com o HTML, desbloqueio em sequência:
  1. **Bem-vindo ao universo da maquiagem** — etapas 1 · 2 · 3
  2. **Os 16 produtos** — etapas 4 · 5 · 6 · 7
  3. **Adaptando a venda** — etapa 8
  4. **Jornada Boti Beleza** — etapas 9 · 10
  5. **Quiz final** — etapa 11 (nota mínima a definir; engine sugere 80 %)
- Alternativa: 11 fases 1:1 (um pin por etapa, como as bolinhas do HTML). Mais pins, menos sensação de progresso por bloco.

## Decisões que precisam do cliente

1. **Vídeo**: YouTube não listado ou arquivo no nosso servidor?
2. **Imagens em alta**: originais do Drive (as entregues têm 98–220 px).
3. **Simulação**: manter "tentar de novo até acertar" ou 1 chance com feedback (pontua)?
4. **Nota mínima** do quiz final / das fases (o HTML só mostra placar).
5. **Mapa**: aprovar o conceito do cenário (loja / bancada) — arte a produzir por nós.
6. **Identidade**: paleta do treinamento (magenta/âmbar/navy/teal) × verde institucional — qual vale para a plataforma? Logo oficial pra `admin/logos/boticario.svg`.
7. **Nomes**: trilha × treinamento (o HTML chama tudo de "trilha"). Virão outras trilhas?
8. **Gamificação**: XP, badges e ranking seguem ligados (padrão do engine)?

## O que faremos em seguida

0. **Mover o material** para `boticario/materiais/` — a pasta atual iria pro repo e pro servidor num `git add`; `/*/materiais/` já está no `.gitignore`.
1. **Engine**: E1 + E2 + E3 (+ E3b conforme decisão) — ~2 h 30 com teste.
2. **Mídia**: otimizar PNG → WebP, pedir originais em alta, subir MP3 pelo editor, hospedar o vídeo (E4).
3. **Carga**: script `_lib/conteudo/servico-make.php` (mesmo padrão do SSMS no Atlântica) que cria trilha → treinamento → 5 fases → 11 etapas com os textos do HTML, idempotente, disparado uma vez pelo admin. Versionado e reproduzível — melhor que cadastrar 11 etapas à mão.
4. **Mapa**: produzir a arte do cenário e posicionar os 5 pins em `admin/mapa`.
5. **Revisão com o cliente** na visão de aluno → ajustes → abrir para os usuários.
