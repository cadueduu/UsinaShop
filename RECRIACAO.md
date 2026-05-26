# Guia Completo de Recriação — E-commerce Usina Spark

> Documento gerado para permitir a recriação fiel do sistema em qualquer linguagem ou framework.  
> Cobre: design system, banco de dados, cada página, cada componente, fluxos de negócio e comportamentos interativos.

---

## 1. Identidade Visual e Design System

### 1.1 Paleta de Cores

| Papel | Cor | Hex |
|---|---|---|
| Primária (destaque) | Amarelo | `#facc15` (yellow-400) |
| Primária hover | Amarelo claro | `#fde047` (yellow-300) |
| Fundo geral das páginas | Cinza muito claro | `#f9fafb` (gray-50) |
| Fundo de cards / inputs | Branco | `#ffffff` |
| Texto principal | Quase preto | `#111827` (gray-900) |
| Texto secundário | Cinza médio | `#6b7280` (gray-500) |
| Texto de rótulos leves | Cinza claro | `#9ca3af` (gray-400) |
| Botão primário / fundo | Preto | `#000000` |
| Texto em botão preto | Amarelo | `#facc15` |
| Rodapé | Preto | `#000000` |
| Texto do rodapé | Cinza claro | `#d1d5db` (gray-300) |
| Link hover no rodapé | Vermelho | `#ef4444` (red-500) |
| Estoque disponível | Verde | `#16a34a` (green-600) |
| Estoque esgotado | Vermelho | `#ef4444` (red-500) |
| Borda padrão | Cinza suave | `#e5e7eb` (gray-200) |
| Borda ativa / destaque | Amarelo | `#facc15` |
| Erro | Vermelho suave | `#dc2626` (red-600) |

### 1.2 Tipografia

- **Fonte:** Padrão do sistema (Next.js / Tailwind sem fonte customizada declarada)
- **Tamanhos frequentes:**
  - `text-xs` = 12px — rótulos e badges
  - `text-sm` = 14px — texto de apoio, nav links
  - `text-base` = 16px — corpo de texto
  - `text-lg` = 18px — títulos de seção menores
  - `text-xl` = 20px — preços, títulos de sidebar
  - `text-2xl` = 24px — títulos de página
  - `text-3xl` = 30px — títulos grandes
  - `text-4xl` = 36px — título do hero / banner
  - `text-5xl` = 48px — título principal do banner desktop

### 1.3 Bordas e Sombras

- `rounded-lg` (8px) — botões, inputs padrão
- `rounded-xl` (12px) — cards, formulários
- `rounded-2xl` (16px) — container de produto no detalhe
- `rounded-3xl` (24px) — painel principal da página de produto
- `rounded-full` — badges, dots de carrossel, avatares
- `shadow-sm` — cards de produto em repouso
- `shadow-xl` — cards de produto no hover
- `shadow-2xl` — sidebars (carrinho e busca)

### 1.4 Transições e Animações

- Todas as transições de cor/fundo: `duration-300`
- Sidebar desliza pela direita: `transform transition-transform duration-300 ease-in-out`
- Sticky header com blur ao rolar: `backdrop-blur-md` + `bg-white/90`
- Banner carousel: troca de slide com transição de opacidade/posição (`x: ±100%`)
- Auto-slide do banner: 5000ms
- Auto-slide das imagens do produto: 4000ms
- Hover em botões: escala 1.05, hover em social icons: rotação 360°

---

## 2. Layout Global

### 2.1 Estrutura do `layout.tsx` (raiz)

O layout raiz envolve toda a aplicação com a seguinte estrutura:

```
<html>
  <body>
    <AuthProvider>        ← inicializa sessão Supabase ao carregar
      <Header />          ← só aparece em desktop (hidden em mobile), oculto em /admin/*
      <MobileBottomBar /> ← só aparece em mobile (sm:hidden), oculto em /admin/*
      <CartSidebar />     ← overlay/drawer lateral direito, global
      <SearchSidebar />   ← overlay/drawer lateral direito, global
      {children}          ← conteúdo da rota atual
    </AuthProvider>
  </body>
</html>
```

**Breakpoint mobile/desktop:** `sm` = 640px. Abaixo disso é mobile.

---

## 3. Banco de Dados

### 3.1 Tabela `produto`

| Coluna | Tipo | Descrição |
|---|---|---|
| `codprod` | int (PK) | Código único do produto |
| `descrprod` | text | Descrição técnica interna (fallback do nome) |
| `comnome` | text | Nome comercial — usado para exibição se preenchido |
| `desccurta` | text | Descrição curta do produto |
| `descrprodoed` | text | Descrição longa/completa |
| `codgrupoprod` | int (FK) | Categoria do produto — aponta para nível 2 |
| `syncsite` | text | `'S'` = visível no site, `'N'` = oculto |
| `peso` | numeric | Peso em gramas |
| `altura` | numeric | Altura em cm |
| `largura` | numeric | Largura em cm |
| `comprimento` | numeric | Comprimento em cm |

**Regra:** O nome exibido é `comnome` se não nulo/vazio, caso contrário `descrprod`.

### 3.2 Tabela `preco`

| Coluna | Tipo | Descrição |
|---|---|---|
| `codprod` | int (FK) | Referência ao produto |
| `vlr_venda` | numeric | Preço de venda em reais |

**Regra crítica:** Produtos com `vlr_venda = 0` NUNCA são exibidos.

### 3.3 Tabela `estoque`

| Coluna | Tipo | Descrição |
|---|---|---|
| `codprod` | int (FK) | Referência ao produto |
| `estoque_disponivel` | numeric | Quantidade disponível |

### 3.4 Tabela `produto_imagem`

| Coluna | Tipo | Descrição |
|---|---|---|
| `codprod` | int (FK) | Referência ao produto |
| `url` | text | URL completa da imagem |
| `ordem` | int | Menor número = imagem principal |

**Regra:** Ordenar sempre por `ordem` ascendente. Fallback se sem imagens: `/logo/logo.png`.

### 3.5 Tabela `categoria`

| Coluna | Tipo | Descrição |
|---|---|---|
| `codgrupoprod` | int (PK) | Código da categoria |
| `descr_grupo` | text | Nome em MAIÚSCULAS (ex: `"FONTE 12V"`) |
| `codgrupopai` | int (FK self) | Referência à categoria pai |

**Hierarquia de 3 níveis:**
- **Nível 0 (raiz):** `3000000` (PRODUTO ACABADO) e `4000000` (PRODUTO REVENDA) — nunca exibidos
- **Nível 1 (grupos):** Filhos diretos do nível 0 — ex: `3160000 CARREGADOR`, `3250000 FONTE`
- **Nível 2 (sub-categorias):** Filhos do nível 1 — ex: `3160001 CARREGADOR 12V` — onde ficam os produtos

**Formatação obrigatória de nomes:** `nome.charAt(0) + nome.slice(1).toLowerCase()`
- Exemplo: `"FONTE 12V"` → `"Fonte 12v"`

### 3.6 Tabela `especificacao`

| Coluna | Tipo | Descrição |
|---|---|---|
| `codprod` | int (FK) | Referência ao produto |
| `label` | text | Ex: `"Tensão de entrada"` |
| `valor` | text | Ex: `"10V ~ 16V"` |

### 3.7 Tabela `cliente`

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | uuid (PK) | Espelha o `auth.users.id` do Supabase Auth |
| `codparc` | int | Código de parceiro (sistema legado) |
| `nome` | text | Nome completo |
| `email` | text | Somente leitura (vem do Auth) |
| `telefone` | text | Telefone sem formatação |
| `cpf_cnpj` | text | CPF ou CNPJ sem formatação |
| `is_admin` | bool | `true` = acesso ao painel admin |

### 3.8 Tabela `endereco`

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | int (PK) | Identificador do endereço |
| `cliente_id` | uuid (FK) | Referência ao cliente |
| `tipo` | text | Ex: `"Entrega"` |
| `cep` | text | Formato `"38190-000"` (com hífen) |
| `logradouro` | text | Nome da rua |
| `numero` | text | Número |
| `complemento` | text | Opcional (apto, bloco, etc.) |
| `bairro` | text | Bairro |
| `cidade` | text | Cidade |
| `uf` | text | Sigla do estado, 2 chars, maiúsculas |
| `is_padrao` | bool | Apenas 1 por cliente pode ser `true` |

---

## 4. Header (Barra de Navegação Desktop)

### 4.1 Visibilidade e Comportamento Geral

- **Exibição:** Somente em desktop (`sm:` e acima = ≥640px). Em mobile é `display: none`.
- **Posição:** `sticky top-0` com `z-index: 50`.
- **Altura:** `h-24` (96px fixos).
- **Oculto em:** todas as rotas que começam com `/admin`.
- **Scroll:** Quando o usuário rola mais de 10px, o fundo muda de `bg-white shadow-md` para `bg-white/90 backdrop-blur-md shadow-[0_4px_24px_rgba(0,0,0,0.08)]` com um gradiente sutil de 6px na borda inferior.

### 4.2 Estrutura Interna (Flex horizontal)

O header é um `container mx-auto px-4 h-full flex items-center justify-between` com três zonas:

```
[ LOGO ] ──────────── [ NAVEGAÇÃO DE CATEGORIAS ] ──────────── [ AÇÕES ]
  (esquerda)                    (centro)                         (direita)
```

### 4.3 Zona da Logo (esquerda)

- Espaço reservado: `w-30` (120px) com `flex-shrink-0`.
- A logo é um `<Image>` com posição absoluta, centralizada verticalmente com `top-1/2 -translate-y-1/2`.
- Tamanho da logo: `w-55 h-55` (220px × 220px), se ajusta com `object-contain`.
- Arquivo: `/logo/logo.png`.
- Clicável: leva para `/`.

### 4.4 Zona de Categorias (centro)

- Container: `flex-1 max-w-xl px-6`, flex horizontal, centralizado.
- **Dropdown "Todas as Categorias":**
  - Botão com texto "Todas as Categorias" + ícone `ChevronDown` (16px).
  - Estilo padrão: `font-semibold text-gray-600`.
  - Hover: `text-yellow-500`.
  - Ativa ao passar o mouse sobre o container (não ao clicar).
  - **Painel do dropdown:**
    - Posição: absoluta, `top: calc(100% - 8px)`, alinhado à esquerda do botão.
    - Largura: `w-72` (288px).
    - Background: `bg-white`.
    - Sombra: `shadow-[0_10px_20px_rgba(0,0,0,0.1)]`.
    - Bordas: `rounded-b-xl border border-gray-100 border-t-0`.
    - Scroll: `overflow-y-auto max-h-96`.
    - Primeiro item: "Todos os Produtos" → `/products`, com `font-semibold`, fundo hover `yellow-50`, texto hover `yellow-600`, borda inferior `border-gray-100`.
    - Grupos (nível 1): texto em `text-xs font-bold text-gray-400 uppercase tracking-widest`, com padding `px-4 pt-3 pb-1`. Clicável → `/products?categoria={id}`.
    - Sub-categorias (nível 2): texto `text-sm`, padding `px-6 py-2`, hover `bg-yellow-50 text-yellow-600`.
    - Todos os nomes formatados com capitalização inicial.
- **Links rápidos:** Os 4 primeiros grupos pai (nível 1) aparecem ao lado do dropdown, `gap-6`, `text-sm font-medium text-gray-600`, hover `text-yellow-500`, com `whitespace-nowrap`.

### 4.5 Zona de Ações (direita)

Flex horizontal com `space-x-6` entre os itens:

**1. Botão Busca:**
- Ícone `Search` (24px), cor `text-gray-700`, hover `text-yellow-500`.
- Ao clicar: abre o `SearchSidebar`.

**2. Botão Admin (condicional):**
- Visível apenas se `user.is_admin === true`.
- Ícone `ShieldAlert` (24px) + duas linhas de texto.
  - Linha 1: `"Acesso Restrito"` em `text-[10px] text-gray-500`.
  - Linha 2: `"Admin"` em `font-medium text-sm`.
- Leva para `/admin`.

**3. Botão Conta / Login:**
- Se logado: ícone `User` (24px) + duas linhas.
  - Linha 1: `"Olá, {primeiro nome}"` em `text-[10px] text-gray-500`.
  - Linha 2: `"Minha Conta"` em `font-medium text-sm`.
  - Leva para `/conta`.
- Se não logado: ícone `User` + texto `"Entrar"` (em `font-medium`).
  - Leva para `/login`.

**4. Botão Carrinho:**
- Ícone de carrinho SVG customizado (24px × 24px), `text-gray-700`, hover `text-yellow-500`.
- Badge com contagem: posição absoluta `-top-2 -right-3`, `bg-yellow-400 text-black text-xs font-bold px-1.5 py-0.5 rounded-full shadow-sm`.
- Badge só aparece quando há itens no carrinho.
- Ao clicar: abre o `CartSidebar`.

---

## 5. Footer (Rodapé)

### 5.1 Estrutura Geral

- Fundo preto (`bg-black`), texto branco.
- `container mx-auto px-4 pt-16 pb-4`.
- Grade de 4 colunas em desktop, 1 coluna em mobile (`grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8`).

### 5.2 Coluna 1 — Informações da Empresa

- Logo: `Logo_Spark-ML.png`, tamanho `w-56` (224px), com `transform -translate-x-4` (recuo de 16px à esquerda).
- Parágrafo descritivo: `text-gray-300 leading-relaxed`.

### 5.3 Coluna 2 — Links Rápidos

- Título: `"Links Rápidos"` em `text-lg font-semibold mb-4`.
- Links: Sobre Nós, Produtos, Suporte, Institucional.
- Cor padrão: `text-gray-300`, hover `text-red-500 transition-colors duration-300`.
- Cada link: `py-1 block`.

### 5.4 Coluna 3 — Atendimento

- Título: `"Atendimento"` em `text-lg font-semibold mb-4`.
- Links: Central de Ajuda, Garantia, Trocas e Devoluções, Fale Conosco.
- Mesma estilização da coluna 2.

### 5.5 Coluna 4 — Redes Sociais

- Título: `"Siga-nos"` em `text-lg font-semibold mb-4`.
- Ícones em linha horizontal, `flex gap-4`.
- Cada ícone: círculo `w-11 h-11` com `bg-white border-2 border-white rounded-full`.
- **Efeito hover:** Fundo colorido da rede social sobe de baixo para cima com `transition-all duration-500`, ícone rotaciona 360°.
- Redes: Facebook (`#3b5999`), LinkedIn (`#0077b5`), Instagram (gradiente amarelo→rosa→roxo), TikTok (preto).

### 5.6 Barra Inferior

- Borda superior `border-t border-gray-800 mt-8 pt-4 pb-4`.
- Texto centralizado: `text-gray-400 text-sm`.
- Conteúdo: `© 2026 SPARK ELETRÔNICA. Todos os direitos reservados.` + CNPJ e endereço físico.

---

## 6. Página Inicial — `/`

### 6.1 Comportamento Geral

- Fundo: `bg-gray-50`.
- Suporta busca via query string `?q=termo`.
- Se há busca ativa: exibe apenas os resultados da busca (sem banner nem seções).
- Se sem busca: exibe banner + destaques + faixa de chamada + mais vendidos.

### 6.2 Banner Hero Desktop

- Visível apenas em `sm:` e acima (`hidden sm:block`).
- Componente `BannerCarousel`:
  - Proporção `21:7` (aproximadamente 3:1).
  - Fundo preto enquanto carrega.
  - 3 slides fixos em código (pode ser configurado), cada um com: imagem de fundo `object-cover`, gradiente overlay da esquerda (`from-black/60 via-black/20 to-transparent`).
  - **Conteúdo de cada slide** (dentro da imagem, alinhado à esquerda):
    - Badge: pill `bg-yellow-400 text-black text-xs font-black uppercase tracking-widest px-3 py-1 rounded-full mb-3`.
    - Título: `text-white text-3xl md:text-5xl font-black leading-tight drop-shadow-lg mb-2`.
    - Subtítulo: `text-white/80 text-sm md:text-base font-medium mb-5`.
    - Botão CTA: `bg-yellow-400 hover:bg-yellow-300 text-black font-bold text-sm px-6 py-3 rounded-full` com sombra e efeito de escala 1.05 no hover.
  - **Controles de navegação:**
    - Setas esquerda/direita: círculos `w-9 h-9 md:w-11 md:h-11 rounded-full bg-white/10 backdrop-blur-sm border border-white/20`.
    - Dots de progresso na parte inferior: bolinha ativa tem `w-28px` (expandida), inativa tem `w-8px`. Cor ativa: `#facc15`, inativa: `rgba(255,255,255,0.4)`.
    - Barra de progresso superior: `h-[2px] bg-white/10` com preenchimento `bg-yellow-400`.
  - **Auto-play:** 5000ms. Pausado ao passar o mouse.
  - **Swipe:** Suporte a swipe em touch (delta > 50px).

### 6.3 Hero Mobile

- Visível apenas em mobile (`sm:hidden`).
- Componente `MobileHero` (implementação simplificada para telas pequenas).

### 6.4 Seção "Destaques"

- Container: `container mx-auto px-4 py-10 sm:py-16`.
- Cabeçalho: flex `justify-between items-end mb-6 sm:mb-8`.
  - Título: `"Destaques"` em `text-xl sm:text-2xl font-bold text-gray-900 border-l-4 border-yellow-400 pl-3`.
  - Link "Ver todos →": `text-sm font-semibold text-yellow-600 hover:text-yellow-500`.
- Grid de produtos: `grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-8`.
- Exibe 8 produtos.

### 6.5 Faixa de Chamada ("Potência que Transforma")

- Fundo: `bg-gray-100 py-10 sm:py-12`.
- Texto centralizado, `container mx-auto px-4 text-center`.
- Título: `text-2xl sm:text-4xl font-black text-gray-900 uppercase tracking-wider`, com a palavra "Transforma" em `text-yellow-500`.
- Subtítulo: `text-gray-600 text-base sm:text-xl max-w-3xl mx-auto font-medium`.

### 6.6 Seção "Mais Vendidos"

- Estrutura idêntica à seção "Destaques".
- Título: `"Mais Vendidos"`.
- Exibe 8 produtos.

### 6.7 Modo Busca

- Quando `?q=` está na URL, exibe o título: `"Resultados para: "{termo}""` com `border-l-4 border-yellow-400 pl-3`.
- Grid de resultados: `grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-8`.
- Se vazio: `"Nenhum produto encontrado."` em `text-gray-600`.

---

## 7. Card de Produto — `ProductCard`

### 7.1 Estrutura Visual

```
┌─────────────────────────────────────┐
│         Área da Imagem (h-56)       │
│      [imagem object-contain p-4]    │
│      Fundo: bg-gray-50              │
├─────────────────────────────────────┤
│   Nome do produto (text-lg          │  bg-gray-50
│   font-semibold, 2 linhas max)      │
│   Altura fixa: h-14                 │
│                                     │
│   R$ 999,00 (text-2xl font-bold)    │
│                                     │
│  [🛒 Adicionar]  ← botão full width │
└─────────────────────────────────────┘
```

### 7.2 Especificações

- Container: `bg-white rounded-xl shadow-sm hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 flex flex-col h-full`.
- Área da imagem: `Link` para `/product/{id}`, altura `h-56`, `bg-gray-50`, `overflow-hidden`.
- Área de texto: `p-5 flex flex-col flex-grow bg-gray-50`.
- Nome: `text-lg font-semibold text-gray-900 mb-2 hover:text-yellow-500 line-clamp-2 h-14`.
- Preço: `text-2xl font-bold text-gray-900 mb-4`. Formatado em BRL (`R$ 1.234,56`).
- Botão "Adicionar": `w-full bg-black text-yellow-400 font-bold py-3 rounded-lg hover:bg-yellow-400 hover:text-black transition-colors flex items-center justify-center space-x-2 shadow-md shadow-black/5`.
  - Ícone `ShoppingCart` (20px) + texto "Adicionar".
  - Ao clicar: adiciona ao carrinho e abre o `CartSidebar`.

---

## 8. Sidebar do Carrinho — `CartSidebar`

### 8.1 Comportamento

- Está sempre montado no DOM (global no layout).
- Estado controlado pelo `cartStore` (`isOpen`).
- Desliza da direita: `translate-x-full` → `translate-x-0` com `duration-300 ease-in-out`.
- Overlay escuro `bg-black/50` cobre a tela quando aberto. Clicar no overlay fecha.
- Largura: `100%` em mobile, `400px` em desktop (`sm:w-[400px]`).
- `z-index: 60` (acima do header).

### 8.2 Estrutura Interna

**Cabeçalho:**
- `p-4 border-b border-gray-200`, flex `justify-between`.
- Título: ícone `ShoppingBag` (20px) + `"Seu Carrinho"` em `text-lg font-bold text-gray-900`.
- Botão fechar: ícone `X` (20px), `p-2 rounded-full`, hover `bg-gray-100`.

**Área dos Itens (scrollável):**
- `flex-1 overflow-y-auto p-4 pb-24`.
- **Se vazio:** ícone `ShoppingBag` grande (64px, `text-gray-300`) + texto `"Seu carrinho está vazio."` + link `"Continuar comprando"` em `text-yellow-600`.
- **Cada item:** `flex gap-4 border-b border-gray-100 pb-4`.
  - Imagem: `w-20 h-20 rounded-md bg-gray-50`, `object-contain p-2`.
  - Coluna direita: nome (`text-sm font-medium`, 2 linhas max), preço em `text-sm font-bold text-yellow-600`.
  - Controle de quantidade: borda `border border-gray-300 rounded-md h-8`. Botões `-` e `+` com ícones `Minus`/`Plus` (12px). Quantidade no centro em `w-8 text-center text-sm font-medium`.
  - Botão remover: ícone `Trash2` (16px), `text-gray-400 hover:text-red-500`.

**Rodapé (quando há itens):**
- `border-t border-gray-200 p-4 bg-gray-50`.
- Linha de subtotal: flex `justify-between`. Label `"Subtotal"` em `text-gray-600 font-medium`, valor em `text-xl font-bold text-gray-900`.
- Botão "Finalizar Compra": `w-full bg-black text-white font-bold py-3.5 rounded-lg hover:bg-yellow-400 hover:text-black transition-colors shadow-lg shadow-black/10`. Ao clicar: fecha o carrinho e navega para `/checkout`.

---

## 9. Sidebar de Busca — `SearchSidebar`

### 9.1 Comportamento

- Idêntico ao CartSidebar em posicionamento e animação.
- Estado controlado pelo `searchSidebarStore` (`isOpen`).

### 9.2 Estrutura Interna

**Cabeçalho:**
- Ícone `Search` (20px) + `"Buscar Produtos"` em `text-lg font-bold text-black`.
- Botão fechar: ícone `X`, `rounded-full hover:bg-gray-100`.

**Campo de Busca:**
- `p-4`, flex horizontal `gap-3`.
- Input: `flex-1 border border-gray-300 px-4 py-3 rounded-lg focus:ring-2 focus:ring-yellow-400`.
  - Placeholder: `"O que você procura?"`.
  - Ao pressionar Enter: executa a busca.
- Botão "Buscar": `bg-black text-white font-bold px-4 rounded-lg hover:bg-yellow-400 hover:text-black`.
- Ao buscar: navega para `/?q={termo}` e fecha a sidebar.

**Lista de Categorias:**
- `flex-1 overflow-y-auto border-t border-gray-100 p-4`.
- Título: `"CATEGORIAS"` em `text-sm font-bold text-gray-500 uppercase tracking-wider mb-4`.
- Skeleton loader (6 itens `h-11 rounded-lg bg-gray-100 animate-pulse`) enquanto carrega do Supabase.
- Primeiro item: "Todos os Produtos" → `/products`, `font-semibold`.
- Cada categoria: `flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 text-gray-700 hover:text-yellow-600`. Ícone `ChevronRight` (20px) à direita.

---

## 10. Página de Listagem — `/products`

### 10.1 Estrutura Geral

- Fundo: `bg-gray-50 min-h-screen flex flex-col`.
- Container: `container mx-auto px-4 py-8 sm:py-16`.
- Footer ao final.

### 10.2 Cabeçalho da Página

- Título: nome da categoria ativa ou `"Todos os Produtos"`, em `text-2xl sm:text-3xl font-bold text-gray-900 border-l-4 border-yellow-400 pl-3`.
- Subtítulo: `"{N} produto(s) encontrado(s)"` em `text-gray-500 text-sm mt-2`.

### 10.3 Filtros de Categoria (Pills)

Dois conjuntos de pills empilhados verticalmente com `space-y-3 mb-8`:

**Linha 1 — Grupos Principais (Nível 1):**
- `flex flex-wrap gap-2 overflow-x-auto pb-1`.
- Primeiro pill: `"Todos"` → `/products`.
- Um pill por grupo pai que tenha sub-categorias com produtos.
- **Pill ativo:** `bg-yellow-400 text-black border-yellow-400 shadow-sm`.
- **Pill inativo:** `bg-white text-gray-600 border border-gray-200 hover:border-yellow-400 hover:text-yellow-600`.
- Estilo base: `flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-semibold border transition-all duration-200`.

**Linha 2 — Sub-categorias (Nível 2):**
- Só aparece quando um grupo pai está selecionado.
- `flex flex-wrap gap-2 pl-3 border-l-2 border-yellow-400 overflow-x-auto pb-1`.
- Primeiro pill: `"Todos"` → filtra pela categoria pai (mostra tudo do grupo).
- Um pill por sub-categoria filha do grupo ativo.
- Pills menores: `text-xs py-1 px-3`.

### 10.4 Grid de Produtos

- `grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-8`.
- Se vazio: texto centralizado `"Nenhum produto encontrado nesta categoria."` + link "Ver todos os produtos".

---

## 11. Página de Detalhe do Produto — `/product/[id]`

### 11.1 Estado de Loading / Erro

- Loading: spinner centralizado `w-8 h-8 border-4 border-yellow-400 border-t-transparent rounded-full animate-spin`.
- Erro: texto `"Produto não encontrado."` centralizado.

### 11.2 Breadcrumb (apenas desktop)

- `hidden sm:flex items-center gap-1.5 text-sm mb-8 text-gray-400 flex-wrap`.
- Formato: `Home / Produtos / [Grupo Pai] / [Sub-categoria] / [Nome do Produto]`.
- Separador: `/` em `text-gray-400`.
- Links clicáveis: hover `text-yellow-600`.
- Último item (produto): `text-gray-900 font-medium truncate max-w-xs` (não é link).

### 11.3 Container Principal

- `bg-white p-4 sm:p-8 rounded-3xl shadow-lg shadow-black/5 border border-gray-100`.
- Grid interno: `grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12`.

### 11.4 Coluna Esquerda — Galeria de Imagens

**Imagem principal:**
- `bg-gray-50 rounded-2xl aspect-square relative overflow-hidden`.
- `<Image>` com `object-contain p-8`.
- Auto-slide a cada 4000ms entre as imagens disponíveis.

**Thumbnails** (só aparece se houver mais de 1 imagem):
- `grid grid-cols-4 gap-3`.
- Cada thumbnail: `aspect-square rounded-lg border-2 overflow-hidden bg-gray-50`.
- **Ativa:** `border-yellow-400 shadow-md`.
- **Inativa:** `border-gray-100 hover:border-yellow-200`.
- Ao clicar: muda a imagem principal e reinicia o timer do auto-slide.

### 11.5 Coluna Direita — Detalhes e Compra

**Título do produto:**
- `text-2xl sm:text-4xl font-bold text-gray-900 mb-3`.

**Preço:**
- `text-3xl font-black text-yellow-600 mb-2`.
- Sufixo: `"à vista ou em 12x no cartão"` em `text-sm text-gray-500 font-normal ml-2`.

**Status de estoque:**
- Se disponível (> 0): `"✓ Em estoque (N un.)"` em `text-sm font-semibold text-green-600`.
- Se esgotado (= 0): `"✗ Fora de estoque"` em `text-sm font-semibold text-red-500`.

**Seletor de Quantidade:**
- Borda `border border-gray-300 rounded-xl sm:rounded-lg`.
- Altura `h-14` mobile, `h-12` desktop. Largura `w-full sm:w-36`.
- Botões `–` e `+` com ícones `Minus`/`Plus` (20px). Não vai abaixo de 1.
- Quantidade ao centro: `font-bold text-gray-900 text-lg`.

**Botão "Comprar Agora":**
- `w-full bg-black hover:bg-yellow-400 hover:text-black text-white h-16 sm:h-12 rounded-xl sm:rounded-lg font-black text-lg sm:text-sm flex items-center justify-center gap-3 transition-colors shadow-xl shadow-black/25`.
- Ícone `ShoppingCart` (24px mobile, 20px desktop).
- Desabilitado se estoque = 0 (opacity-50, cursor-not-allowed).
- Ao clicar: adiciona ao carrinho (com a quantidade selecionada) e abre o `CartSidebar`.

**Divisor:** `<hr />` entre ações e calculadora de frete.

**Calculadora de Frete:**
- Título: ícone `Truck` (20px, `text-yellow-500`) + `"Calcular Frete e Prazo"` em `text-lg font-semibold`.
- Aviso: `"O valor do frete pode variar de acordo com a quantidade de itens."` em `text-xs text-gray-500`.
- Input CEP: `border border-gray-300 px-4 py-2.5 rounded-lg`, placeholder `"Digite seu CEP"`. Aceita apenas dígitos, máximo 8.
- Botão "Calcular": `bg-yellow-400 text-black font-medium px-6 py-2.5 rounded-lg`. Desabilitado se CEP < 8 dígitos.
- Resultado: lista de opções (SEDEX / PAC) cada uma em card `flex justify-between items-center p-4 bg-white border border-gray-100 rounded-lg shadow-sm`.
  - Esquerda: nome do serviço (`font-bold text-gray-900`) + prazo (`text-sm text-gray-500`).
  - Direita: valor em `font-bold text-lg`.
  - Recalcula automaticamente quando a quantidade muda.

**Especificações Técnicas:**
- `mt-8 bg-gray-50 p-5 rounded-xl`.
- Título: linha amarela vertical `w-1.5 h-4 bg-yellow-400 rounded-full` + `"ESPECIFICAÇÕES"` em `text-sm font-bold text-gray-900 uppercase tracking-wider`.
- Lista: `space-y-2`. Cada item: ícone de checkmark SVG (`text-yellow-500 w-4 h-4`) + `"{label}: {valor}"` em `text-sm text-gray-700`, onde o label é `font-semibold`.

**Descrição do Produto (abaixo do grid de 2 colunas):**
- `mt-10 pt-10 border-t border-gray-200`.
- Título: `"Descrição do Produto"` em `text-2xl font-bold text-gray-900 mb-6`.
- Texto: `text-gray-700 leading-relaxed whitespace-pre-wrap text-base`.

---

## 12. Página de Checkout — `/checkout`

### 12.1 Guards de Acesso

- Se carrinho vazio: redireciona para `/`.
- Se não logado: pode prosseguir (guard de login comentado no código, fluxo futuro).

### 12.2 Estrutura Geral

- Fundo: `bg-gray-50 min-h-screen`.
- Container: `container mx-auto px-4 py-10 max-w-5xl`.
- Layout em duas colunas em desktop (`flex flex-col lg:flex-row gap-8`).

### 12.3 Cabeçalho da Página

- Título: `"Finalizar Compra"` com `border-l-4 border-yellow-400 pl-3 text-3xl font-bold`.
- Subtítulo: `"Selecione ou cadastre o endereço de entrega."` em `text-gray-500 mb-8 pl-4`.

### 12.4 Coluna Esquerda — Endereços

**Lista de endereços salvos:**
- Cada endereço: card com `p-5 rounded-xl border-2`.
  - **Selecionado:** `border-yellow-400 bg-yellow-50/40 shadow-sm`.
  - **Não selecionado:** `border-gray-200 bg-white hover:border-yellow-200`.
- Dentro do card: radio customizado (círculo `w-5 h-5 rounded-full border-2`) + dados do endereço + botão de editar (ícone `Pencil`).
- Badge `"Padrão"`: `bg-yellow-400 text-black text-xs font-bold px-2 py-0.5 rounded` com ícone `CheckCircle2`.
- Ao clicar no botão editar: abre formulário inline abaixo do card (aba de edição conectada ao card com bordas).

**Formulário inline de edição:**
- `bg-white border-2 border-yellow-400 border-t-yellow-200 rounded-b-xl p-5`.
- Grid `grid-cols-1 sm:grid-cols-2 gap-4`.
- Campos: CEP (com busca automática ViaCEP), Logradouro (col-span-2), Número, Complemento (opcional), Bairro, Cidade, UF (máx 2 chars, uppercase).
- Botões: "Salvar" (`bg-black hover:bg-yellow-400`) + "Cancelar".

**Botão "Adicionar novo endereço":**
- `border-2 border-dashed border-gray-300 rounded-xl py-4`, flex centralizado.
- Ícone `Plus` + texto. Hover: `border-yellow-400 text-yellow-600`.

**Formulário de novo endereço:**
- `bg-white border border-gray-200 rounded-xl p-6`.
- Título: `"Novo Endereço"` (ou `"Cadastre seu endereço de entrega"` se não houver nenhum).
- Mesmos campos do formulário de edição.

**Busca automática de CEP:**
- Ao digitar 8 dígitos: faz GET para `https://viacep.com.br/ws/{cep}/json/`.
- Preenche automaticamente: logradouro, bairro, cidade, UF.
- Spinner `Loader2 animate-spin text-yellow-500` dentro do input durante a busca.

### 12.5 Coluna Direita — Resumo do Pedido

- `w-full lg:w-80 shrink-0`.
- Card: `bg-white border border-gray-200 rounded-xl p-6 sticky top-28`.
- Título: ícone `ShoppingBag` + `"Resumo do Pedido"` em `font-bold text-lg mb-4`.
- Lista de itens: `max-h-52 overflow-y-auto`. Cada item: nome + `×quantidade` à esquerda, valor total à direita.
- Total: `font-bold text-yellow-600 text-xl`.
- Endereço selecionado: caixinha `bg-gray-50 rounded-lg p-3 border border-gray-100 text-xs`. Ícone `MapPin text-yellow-500` + logradouro + cidade-UF.
- Botão "Continuar para Pagamento": `w-full bg-black text-white font-bold py-3.5 rounded-lg hover:bg-yellow-400 hover:text-black`. Desabilitado se nenhum endereço selecionado ou formulário aberto. Navega para `/checkout/pagamento?endereco={id}`.

---

## 13. Página de Login/Cadastro — `/login`

### 13.1 Layout

- Fundo: `bg-gray-50 min-h-screen flex items-center justify-center px-4`.
- Card central: `w-full max-w-md bg-white border border-gray-100 rounded-2xl shadow-xl shadow-black/5 p-8`.
- Em mobile: logo da loja exibida no topo do card (`w-24 h-24 relative`, `sm:hidden`).

### 13.2 Tabs (Entrar / Cadastrar)

- Duas abas side-by-side com `border-b border-gray-200`.
- Aba ativa: `text-gray-900 font-semibold`.
- Aba inativa: `text-gray-500 hover:text-yellow-400`.
- Indicador amarelo: barra de `h-0.5 bg-yellow-400 w-1/2` animada na posição correta.

### 13.3 Formulário de Login

Campos (em ordem):
1. **E-mail:** ícone `Mail` + `type="email"` + placeholder `"Seu e-mail"`.
2. **Senha:** ícone `Lock` + `type="password"` + placeholder `"Senha"`.

Validação com Zod: email válido + senha não vazia.

Botão: `"Entrar"` (durante loading: `"Entrando..."`). Estilo: `w-full bg-black text-white font-bold py-3.5 rounded-lg hover:bg-yellow-400 hover:text-black`.

Erros: caixa `bg-red-50 text-red-600 p-3 rounded-lg text-center font-medium`.

### 13.4 Formulário de Cadastro

Campos (em ordem):
1. **Nome** + **Sobrenome** (grid 2 colunas). Ícones `User`.
2. **E-mail.** Ícone `Mail`.
3. **Telefone.** Ícone `Phone`. Máscara automática: `(XX) XXXXX-XXXX`.
4. **CPF.** Ícone `IdCard`. Máscara automática: `XXX.XXX.XXX-XX`. Validação de dígitos verificadores.
5. **Senha** (mín. 6 chars). Ícone `Lock`.
6. **Confirmar senha.** Ícone `Lock`.

Botão: `"Cadastrar"` (durante loading: `"Cadastrando..."`).

**Pós-cadastro com confirmação de e-mail:**
- Tela especial: ícone `Mail` em círculo `bg-yellow-100 rounded-full w-16 h-16`.
- Mensagem: `"Verifique seu e-mail"` + instrução + botão `"Ir para o Login"`.

### 13.5 Link de Retorno

- `"Voltar para a loja"` em `text-sm text-gray-500 hover:text-yellow-600 font-medium`. Leva para `/`.

---

## 14. Página Minha Conta — `/conta`

### 14.1 Guard

- Se não logado: redireciona para `/login`.

### 14.2 Layout

- Container: `container mx-auto px-4 py-12 max-w-5xl`.
- Título: `"Minha Conta"` com `border-l-4 border-yellow-400 pl-3 text-3xl font-bold mb-8`.
- Layout: `flex flex-col md:flex-row gap-8`.

### 14.3 Sidebar de Navegação

- `w-full md:w-64 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden`.
- Abas verticais:
  - **Meu Perfil:** ícone `User`.
  - **Endereços:** ícone `MapPin`.
- Aba ativa: `bg-yellow-50 text-yellow-700 font-semibold border-r-4 border-yellow-400`.
- Aba inativa: `text-gray-600 hover:bg-gray-50`.
- **Botão "Sair":** `text-red-600 hover:bg-red-50`, `border-t border-gray-100`.
- **Botão "Painel Admin"** (se is_admin): `text-blue-600 hover:bg-blue-50`, `border-t border-gray-100`.

### 14.4 Aba Perfil

**Modo visualização:**
- Grid 2 colunas. Cada campo: `flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100`.
  - Ícone amarelo (20px) + rótulo `text-xs text-gray-400` + valor `font-semibold text-gray-900`.
- Campos exibidos: Nome completo, E-mail, Telefone, CPF/CNPJ.
- CPF formatado: `XXX.XXX.XXX-XX`.
- Botão "Editar dados": `border border-gray-200 px-3 py-2 rounded-lg text-sm font-medium text-gray-500 hover:text-yellow-600 hover:border-yellow-400`.

**Modo edição:**
- Formulário com campos: Nome (editável), E-mail (somente leitura, `bg-gray-50 text-gray-400 cursor-not-allowed`), Telefone (com máscara), CPF (somente leitura).
- Cada campo tem ícone prefixado.
- Botões: "Salvar alterações" (`bg-black hover:bg-yellow-400`) + "Cancelar".

### 14.5 Aba Endereços

- Botão "Novo Endereço": `bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded-lg font-medium text-sm` + ícone `Plus`.
- **Cada endereço (modo visualização):**
  - `p-5 rounded-xl border-2`. Padrão: `border-yellow-400 bg-yellow-50/30`. Não padrão: `border-gray-100 bg-white hover:border-gray-200`.
  - Badge `"Padrão"`: pill amarelo com ícone `CheckCircle2`.
  - Ações à direita: Estrela (definir padrão, `hover:text-yellow-500`), Lápis (editar, `hover:text-blue-500`), Lixeira (excluir, `hover:text-red-500`).
  - **Confirmação de exclusão inline:** `border-t border-red-100 mt-4 pt-4`. Botões "Sim, excluir" (`bg-red-500`) e "Cancelar" (`bg-gray-100`).
- **Estado vazio:** ícone `MapPin` grande cinza + `"Nenhum endereço cadastrado"` + link `"+ Adicionar meu primeiro endereço"`.

---

## 15. Barra Inferior Mobile — `MobileBottomBar`

### 15.1 Estrutura

- Visível apenas em mobile (`sm:hidden`).
- Oculto em rotas `/admin/*`.
- Fixada na base: `fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50`.
- `py-3 px-6`. Grade dinâmica com `repeat(N, minmax(0, 1fr))`.

### 15.2 Itens (da esquerda para direita)

1. **Home** (ícone `House`) — aparece apenas quando não está na home (`/`). Leva para `/`.
2. **Busca** (ícone `Search`) — abre o `SearchSidebar`.
3. **Carrinho** (ícone `ShoppingCart`) — abre o `CartSidebar`. Badge vermelho com contagem (`min-w-[20px] h-5 bg-red-500 text-white text-[11px] font-black rounded-full ring-2 ring-white`).
4. **Conta** (ícone `User`) — leva para `/conta` se logado, `/login` se não logado.
5. **Admin** (ícone `ShieldAlert`) — aparece apenas se `user.is_admin === true`. Leva para `/admin`.

---

## 16. Fluxo de Autenticação — `AuthProvider`

- Componente cliente que executa no boot da aplicação.
- Escuta `supabase.auth.onAuthStateChange`.
- Ao detectar `SIGNED_IN`: busca dados do cliente na tabela `cliente` pelo `user.id` e popula o `userStore`.
- Ao detectar `SIGNED_OUT`: limpa o `userStore`.
- Define `authReady = true` após a verificação inicial.
- Renderiza apenas `{children}` (sem output visual).

---

## 17. Painel Administrativo — `/admin`

### 17.1 Layout do Admin

- Sidebar lateral com logo e links de navegação.
- Proteção: verifica `user.is_admin`. Se falso, exibe tela `"Acesso Negado"`.
- `/admin` redireciona automaticamente para `/admin/clientes`.

### 17.2 `/admin/clientes` — Gestão de Clientes

- Tabela com: nome, email (somente leitura), telefone, CPF/CNPJ, is_admin.
- Busca por texto em tempo real.
- Ordenação por qualquer coluna clicável.
- Edição inline: clicar em um campo abre um input. Salvar atualiza o registro.

### 17.3 `/admin/produtos` — Gestão de Produtos

- Tabela com: thumbnail, nome, categoria, preço, estoque, syncsite.
- Busca por texto.
- Filtro: "No site" / "Fora do site" (campo `syncsite`).
- **Badge de estoque:**
  - Verde: estoque > 10.
  - Laranja: estoque entre 1 e 10.
  - Vermelho: estoque = 0.
- Link para edição individual em `/admin/produtos/{id}`.

---

## 18. API de Frete — `POST /api/frete`

### 18.1 Request

```json
{ "cepDestino": "38190000", "quantity": 2 }
```

### 18.2 Processamento

- CEP de origem fixo: `38190000` (Sacramento/MG).
- Peso calculado: `300g × quantity`.
- Consulta os serviços: SEDEX (código 03220) e PAC (código 03298).
- Requer variável de ambiente `CORREIOS_API_TOKEN`.

### 18.3 Response

Array de objetos com:
- `Codigo`: `"03220"` ou `"03298"`.
- `Valor`: preço formatado (ex: `"25,60"`).
- `PrazoEntrega`: número de dias úteis.

---

## 19. Gerenciamento de Estado Global (Stores)

### 19.1 `cartStore` (Zustand + localStorage)

Persiste apenas `items` no localStorage.

| Ação | Comportamento |
|---|---|
| `addToCart(item)` | Se ID já existe, soma a quantidade. Se não, adiciona. Abre o carrinho automaticamente. |
| `removeFromCart(id)` | Remove o item completamente. |
| `updateQuantity(id, qty)` | Atualiza a quantidade. Mínimo: 1. |
| `clearCart()` | Esvazia o carrinho. |
| `getTotalItems()` | Soma de todas as quantidades. |
| `getTotalPrice()` | Soma de (price × quantity) de todos os itens. |

### 19.2 `userStore` (Zustand, sem persistência)

| Campo/Ação | Descrição |
|---|---|
| `user` | `{ id, name, email, telefone, cpf_cnpj, is_admin }` |
| `isLoggedIn` | Boolean derivado de `user !== null`. |
| `authReady` | `true` após Supabase verificar a sessão inicial. |
| `addresses` | Array de endereços do usuário logado. |
| `logout()` | Chama `supabase.auth.signOut()` e limpa o estado. |
| `updateProfile()` | Atualiza `nome` e `telefone` na tabela `cliente`. |
| `fetchAddresses()` | Busca endereços da tabela `endereco` pelo `cliente_id`. |
| `addAddress()` | Insere endereço. Se for o primeiro, define como padrão. |
| `setDefaultAddress(id)` | Desmarca todos (`is_padrao = false`), depois marca o selecionado. |

### 19.3 `searchSidebarStore`

Estado simples: `isOpen`, `open()`, `close()`, `toggle()`.

---

## 20. Regras de Negócio Críticas

1. **Produtos sem preço nunca são exibidos.** Sempre filtrar `vlr_venda > 0`.
2. **Nome de exibição:** usar `comnome` se não nulo/vazio; caso contrário, `descrprod`.
3. **Formatação de categoria:** sempre `nome.charAt(0) + nome.slice(1).toLowerCase()`.
4. **Imagem principal:** a de menor `ordem` na tabela `produto_imagem`. Fallback: `/logo/logo.png`.
5. **Endereço padrão:** apenas 1 por cliente. Ao definir um novo padrão, o store desmarca todos antes.
6. **CEP:** armazenado no banco como `XXXXX-XXX`. Busca automática via ViaCEP ao digitar 8 dígitos.
7. **Categoria no filtro de `/products`:** pode receber nível 1 ou nível 2. Se nível 1, expande para todos os filhos.
8. **Acesso admin:** duas camadas — sessão ativa (Supabase Auth) + `cliente.is_admin = true`.

---

## 21. Fluxo Completo de Compra

```
1. Usuário navega pela home ou /products
2. Clica em um produto → /product/{id}
3. Seleciona quantidade
4. Clica "Comprar Agora" → CartSidebar abre automaticamente
5. No CartSidebar clica "Finalizar Compra" → /checkout
   [Se não logado: fluxo para implementação futura de guard]
6. Seleciona endereço salvo OU cadastra novo endereço
   - CEP é preenchido automaticamente via ViaCEP
7. Clica "Continuar para Pagamento" → /checkout/pagamento?endereco={id}
   [Integração de pagamento: a implementar]
```

---

## 22. Variáveis de Ambiente Necessárias

```
NEXT_PUBLIC_SUPABASE_URL       → URL do projeto Supabase
NEXT_PUBLIC_SUPABASE_ANON_KEY  → Chave anon pública do Supabase
CORREIOS_API_TOKEN             → Token Bearer para a API dos Correios
```

---

## 23. Integrações Externas

| Serviço | Uso | Endpoint |
|---|---|---|
| Supabase Auth | Login, cadastro, sessão | SDK `@supabase/supabase-js` |
| Supabase Database | Todas as queries | SDK (PostgREST) |
| ViaCEP | Preenchimento automático de endereço | `https://viacep.com.br/ws/{cep}/json/` |
| Correios API | Cálculo de frete | Via proxy `/api/frete` |

---

*Documento gerado em 16/04/2026 — baseado na análise completa do código-fonte do projeto.*
