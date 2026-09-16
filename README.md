> ## ⚠️ This repository is archived
>
> Since **1.0.2.0** the OJS and the OMP editions of this plugin are the same code, in
> **[OJSBR/reviewerDirectory](https://github.com/OJSBR/reviewerDirectory)**. One
> package installs on OJS 3.5 and on OMP 3.5. Get new versions there; the releases below stay
> available for older installations.
>
> ## ⚠️ Este repositório está arquivado
>
> A partir da **1.0.2.0**, as edições OJS e OMP deste plugin são o mesmo código, em
> **[OJSBR/reviewerDirectory](https://github.com/OJSBR/reviewerDirectory)**. Um
> pacote só instala no OJS 3.5 e no OMP 3.5. Baixe as versões novas lá; as releases abaixo
> continuam disponíveis para instalações antigas.

# Reviewer Directory — OMP plugin

[![OMP](https://img.shields.io/badge/OMP-3.5-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.0.3-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OMP 3.5](https://github.com/OJSBR/reviewerDirectoryOmp/releases/download/1.0.0.3/reviewerDirectory-1.0.0.3.tar.gz) — or browse all [Releases](../../releases).

A generic plugin for **Open Monograph Press (OMP)** that adds an **internal, editor-only
directory of reviewers** — pulling the accounts that already hold the *Reviewer* role in the
press, with their profiles and review statistics — plus a **reviewer roster (nominata)** for
a period or issue, ready to publish as an acknowledgement.

> **Developed and maintained by [OJSBR](https://ojsbr.com.br).** See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.0.3 |

**Looking for the OJS edition?** It lives in its own repository,
[OJSBR/reviewerDirectory](https://github.com/OJSBR/reviewerDirectory). This repository is the same plugin with the
terminology and the data model of a book publisher.

## What it does

- **Reviewer directory** — a backend page, restricted to **Managers, Section Editors and
  Administrators**, listing every user with the *Reviewer* role in the current press.
- **One-click shortcut in the dashboard menu** — once enabled, the plugin appends a
  **Reviewer Directory** entry to the end of the editorial sidebar menu, so editors reach the
  page directly instead of going through the plugins screen. It is shown only to the roles the
  page itself authorises (Managers, Section Editors and Administrators).
- **Profile + review data per reviewer** — name, affiliation, country, review interests, ORCID
  (with a verified badge), plus statistics: reviews completed, in progress, declined, average
  days to complete, quality rating, last assignment date and **last completion date**.
- **Active reviews with submission IDs** — the *Active* column lists the submissions currently
  under review by each reviewer, each linking straight to its editorial workflow.
- **Instant search** across name, affiliation, country, interest, e-mail, username and active
  submission IDs, plus an **"only with ORCID"** filter.
- **Configurable columns** — a checkbox bar toggles columns on/off; the choice is remembered
  per browser. Affiliation, country, ORCID, username and e-mail are hidden by default.
- **Sort** by any column (numeric or text).
- **Export to Excel** — one click exports exactly what is filtered/visible to a UTF-8 CSV that
  Excel opens natively.
- **Reviewer roster (nominata)** — pick a **date range** (review completion date) and/or an
  **issue**, and get the reviewers who completed reviews within that scope, with a count and
  the submissions they reviewed — its own Excel export included.

## Installation

1. Download the release (or clone the matching branch).
2. Install via **Settings → Website → Plugins → Upload A New Plugin**, or extract the folder
   into `plugins/generic/` so you get `plugins/generic/reviewerDirectory/`.
3. Enable **Reviewer Directory** under the *Generic* plugins list.
4. Open it via the **"Open directory"** action on the plugins list, or go to
   `/<press>/reviewerdirectory`.

> **Keep the folder name.** PKP 3.5 derives the plugin's class namespace from its installation
> directory, so the folder must be `reviewerDirectory` — the `Omp` suffix belongs to the repository
> name only. The release tarball already unpacks with the right name.

## How it works (technical)

- Reviewers come straight from OMP accounts via `Repo::user()->getCollector()` with
  `includeReviewerData()` (statistics in a single query) and `preloadInterests()` (interests
  batched). Nothing is duplicated or stored by the plugin.
- Active submissions, last completion date and the roster are resolved with batched
  `review_assignments` queries (context-scoped through `submissions`; issues through
  `publications.series_id`), mirroring the core's own definitions of *incomplete* / *completed*.
- The page renders inside the OMP backend (Vue) using a `v-pre` wrapper so server-rendered
  content is untouched; search, column toggles, sorting and export are inline, event-delegated
  JavaScript. Access is enforced by `ContextAccessPolicy` with the manager/sub-editor roles.

## Languages

Ships in **7 languages**: English, Portuguese (Brazil), Portuguese (Portugal), Spanish, French,
Italian and German. Note the French folder is `locale/fr` — PKP 3.5 has no `fr_FR` locale, so a
`fr_FR` folder would never be loaded.

## Tests

Verified on **OMP 3.5.0.4** against a live press. The route `/reviewerdirectory` redirects
anonymous visitors to the login screen (an unknown route returns 404, so the handler is really
registered and protected). With a throw-away scenario — a series, a book in it, a reviewer and
one completed review — the series selector, the unfiltered roster, the roster filtered by
series, an empty series, a matching date range and a non-matching one all behaved as expected.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com.br) — original plugin.
- Distributed under the **GNU GPL v3**.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OMP version you
are working against. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin genérico para o **Open Monograph Press (OMP)** que adiciona um **diretório interno de
avaliadores, restrito a editores** — puxando as contas que já têm o papel de *Avaliador* na
editora, com seus perfis e estatísticas de avaliação — além de uma **nominata de avaliadores**
por período ou edição, pronta para publicar como agradecimento.

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com.br).** Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### Compatibilidade e branches

| Versão do OMP | Branch | Release do plugin |
|---------------|--------|-------------------|
| OMP 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.0.3 |

**Procurando a versão de OJS?** Ela tem repositório próprio,
[OJSBR/reviewerDirectory](https://github.com/OJSBR/reviewerDirectory). Este repositório é o mesmo plugin com a
terminologia e o modelo de dados de uma editora de livros.

### O que faz

- **Diretório de avaliadores** — uma página de backend, restrita a **Gerentes, Editores de
  seção e Administradores**, listando todos os usuários com papel de *Avaliador* na editora.
- **Atalho de um clique no menu do painel** — quando ativado, o plugin acrescenta um item
  **Diretório de Avaliadores** ao fim do menu lateral do painel editorial, para o editor abrir
  a página direto, sem passar pela tela de plugins. Aparece apenas para os papéis que a própria
  página autoriza (Gerentes, Editores de seção e Administradores).
- **Perfil + dados de avaliação por avaliador** — nome, afiliação, país, interesses de
  avaliação, ORCID (com selo de autenticação) e estatísticas: avaliações concluídas, em
  andamento, recusadas, média de dias para concluir, nota de qualidade, data da última
  designação e **data da última conclusão**.
- **Avaliações ativas com IDs de submissão** — a coluna *Ativas* lista as submissões que cada
  avaliador está avaliando no momento, cada uma com link direto para o fluxo editorial.
- **Busca instantânea** por nome, afiliação, país, interesse, e-mail, usuário e IDs de
  submissão ativa, além do filtro **"somente com ORCID"**.
- **Colunas configuráveis** — uma barra de checkboxes liga/desliga colunas; a escolha é
  lembrada por navegador. Afiliação, país, ORCID, usuário e e-mail ficam ocultos por padrão.
- **Ordenação** por qualquer coluna (numérica ou texto).
- **Exportar para Excel** — um clique exporta exatamente o que está filtrado/visível para um
  CSV UTF-8 que o Excel abre nativamente.
- **Nominata de avaliadores** — escolha um **período** (data de conclusão da avaliação) e/ou
  uma **edição**, e obtenha os avaliadores que concluíram avaliações naquele escopo, com a
  contagem e as submissões avaliadas — com exportação própria para Excel.

### Instalação

Instale em **Configurações → Website → Plugins → Enviar um novo plugin**, ou extraia a pasta
em `plugins/generic/` (ficando `plugins/generic/reviewerDirectory/`). Depois ative o
**Reviewer Directory** na lista de plugins *Genéricos*. Acesse pela ação **"Abrir diretório"**
na lista de plugins, ou em `/<editora>/reviewerdirectory`.

> **Não renomeie a pasta.** O PKP 3.5 deriva o namespace da classe do diretório de instalação,
> então a pasta precisa se chamar `reviewerDirectory` — o sufixo `Omp` é só do repositório. O pacote
> de release já descompacta com o nome certo.

### Idiomas

Em **7 idiomas**: inglês, português (Brasil), português (Portugal), espanhol, francês, italiano
e alemão. A pasta do francês é `locale/fr` — o PKP 3.5 não tem o locale `fr_FR`, então uma pasta
`fr_FR` nunca seria carregada.

### Testes

Verificado no **OMP 3.5.0.4** em uma editora real. A rota `/reviewerdirectory` manda o visitante
anônimo para o login (uma rota inexistente devolve 404, então o handler está mesmo registrado e
protegido). Com um cenário descartável — uma série, um livro nela, um avaliador e um parecer
concluído — o seletor de séries, a nominata sem filtro, a nominata filtrada pela série, uma
série vazia, um período que casa e outro que não casa se comportaram como esperado.

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com.br) — plugin autoral.
- Distribuído sob a **GNU GPL v3**.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
