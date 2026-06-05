---

## 🔴 Crítico

### 1. Endpoint de debug exposto em produção
**Arquivo:** `api/debug-sb.php`

O arquivo expõe respostas brutas do Supabase, incluindo estrutura interna das tabelas, URLs de backend e status de configuração (`LOCAL_DATA_MODE`). Em produção, isso entrega informações de arquitetura para qualquer pessoa que acessar a rota.

**Ação:** Remover o arquivo do ambiente de produção. Se necessário para debug local, adicionar ao `.gitignore` e garantir que nunca seja publicado.

---

### 2. Webhook do Mercado Pago sem verificação de assinatura
**Arquivo:** `conta.php`

O endpoint que recebe notificações do Mercado Pago e atualiza o status do pedido não valida a assinatura da requisição. Isso significa que qualquer pessoa pode enviar um POST para essa rota e alterar o status de qualquer pedido no sistema.

**Ação:** Implementar validação da assinatura HMAC conforme documentação oficial do Mercado Pago (`x-signature` header). Rejeitar com `403` qualquer requisição com assinatura inválida ou ausente.

**Referência:** https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks

---

### 3. Pedido criado antes da confirmação do pagamento
**Arquivo:** `checkout-pagamento.php` (linhas ~551–570)

O pedido é inserido no banco de dados antes de o Mercado Pago confirmar a aprovação. Se o pagamento for recusado ou abandonado após esse ponto, o pedido fica registrado sem pagamento correspondente, gerando inconsistência.

**Ação:** Criar o pedido no banco somente após receber a confirmação de pagamento aprovado via webhook, ou utilizar um status intermediário (`aguardando_pagamento`) que só evolui para `pago` após confirmação do MP.

---

### 4. Ausência de proteção CSRF nos formulários
**Arquivos:** `atendimento.php`, `conta.php`, `institucional.php`

Os formulários de contato, edição de conta e outros não possuem token CSRF. Um atacante pode induzir um usuário autenticado a submeter formulários involuntariamente a partir de outro site.

**Ação:** Gerar um token CSRF por sessão no PHP (`$_SESSION['csrf_token']`) e validá-lo em todo POST antes de processar a requisição.

---

## 🟠 Alto

### 5. Carrinho perdido ao fechar a aba
**Arquivo:** `assets/js/main.js`

O carrinho é armazenado apenas em memória (`cartItems = []`). A sincronização com o Supabase tem um debounce de 600ms. Se o usuário fechar a aba ou o navegador dentro dessa janela, os itens adicionados são perdidos. Para usuários não autenticados, não há persistência alguma.

**Ação:**
- Para usuários autenticados: reduzir o debounce ou sincronizar imediatamente em eventos críticos (fechar aba via `beforeunload`).
- Para convidados: salvar `cartItems` em `localStorage` a cada alteração e recuperar ao carregar a página.

```javascript
// Recuperar ao iniciar
cartItems = JSON.parse(localStorage.getItem('cartItems')) || [];

// Salvar a cada alteração
localStorage.setItem('cartItems', JSON.stringify(cartItems));
```

---

### 6. Produto adicionado ao carrinho sem verificação de estoque
**Arquivo:** `assets/js/main.js`

Ao clicar em "Adicionar ao carrinho", o item é incluído diretamente em memória sem consultar a disponibilidade atual no Supabase. Um produto que ficou sem estoque durante a navegação do usuário pode ser adicionado normalmente.

**Ação:** Verificar `estoque_disponivel` no Supabase no momento do clique, ou ao finalizar o checkout, antes de prosseguir com o pagamento.

---

### 7. Lookup de CEP sem timeout
**Arquivo:** `assets/js/main.js` (função `lookupCep`, linhas ~573–592)

A chamada à API ViaCEP não tem timeout configurado. Se o serviço externo não responder, o usuário vê o spinner indefinidamente sem nenhuma mensagem.

**Ação:**

```javascript
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), 8000);

try {
    const res = await fetch(`https://viacep.com.br/ws/${cep}/json`, {
        signal: controller.signal
    });
    // ...
} catch (e) {
    if (e.name === 'AbortError') {
        // Exibir mensagem: "Não foi possível buscar o CEP. Preencha o endereço manualmente."
    }
} finally {
    clearTimeout(timeout);
}
```

---

### 8. Falha silenciosa no cálculo de frete (prazo)
**Arquivo:** `api/frete.php`

Quando o cálculo de preço do frete é bem-sucedido mas o cálculo de prazo falha, a API retorna apenas o valor sem a data estimada de entrega, sem nenhum aviso ao usuário. O usuário não sabe que a informação está incompleta.

**Ação:** Retornar um campo explícito na resposta indicando que o prazo não está disponível, e exibir uma mensagem como _"Prazo de entrega indisponível no momento"_ em vez de omitir a informação.

---

## 🟡 Médio

### 9. Race condition na sincronização do carrinho
**Arquivo:** `assets/js/main.js`

Alterações rápidas no carrinho (adicionar e remover um item em menos de 600ms) podem gerar requisições fora de ordem ao Supabase. A última a chegar pode não ser a última executada pelo usuário, resultando num carrinho desincronizado.

**Ação:** Implementar cancelamento da requisição anterior ao iniciar uma nova (usando `AbortController`) e garantir que o sync sempre reflita o estado atual da memória, não o estado no momento do envio.

---

### 10. Mensagem de CEP some rápido demais
**Arquivo:** `assets/js/main.js` (linha ~601)

O hint exibido após busca de CEP desaparece após 4 segundos (`setTimeout 4000`). Em conexões lentas, o usuário pode não ter tempo de processar a mensagem.

**Ação:** Aumentar para 7–8 segundos, ou manter a mensagem visível até o usuário interagir com o campo seguinte.

---

### 11. Sem rate limiting nos endpoints
**Arquivos:** `api/produtos.php`, `api/frete.php`

Os endpoints não têm limitação de requisições por IP ou sessão. Além do risco de uso abusivo, chamadas excessivas podem causar bloqueio pelas APIs externas (Correios, ViaCEP).

**Ação:** Implementar throttle simples por sessão PHP, ou configurar rate limiting no servidor web (nginx/Apache).

---

### 12. Spinner de frete sem timeout de exibição
**Arquivo:** `assets/js/main.js` (calculadora de frete)

O spinner de carregamento do frete é exibido mas não tem tempo máximo. Se a API não responder, o spinner fica ativo indefinidamente sem mensagem de erro ou opção alternativa para o usuário.

**Ação:** Definir um timeout máximo de exibição (~15s) e exibir mensagem de erro ao esgotar.

---

### 13. Sem histórico de alterações de status do pedido
**Arquivos:** `checkout-pagamento.php`, `conta.php`

Atualizações de status via webhook sobrescrevem o valor anterior sem registro do histórico. Não é possível auditar depois o que aconteceu com um pedido (ex: "estava pago, agora está cancelado — quando mudou?").

**Ação (coordenada com back-end):** Criar uma tabela `pedido_historico` para registrar cada transição de status com timestamp e origem (webhook, manual, integração Sankhya). Discutir com o time de back-end a melhor forma de implementar isso.

---

## 🟢 Experiência do Usuário

### 14. Imagens sem lazy loading — e oportunidade de otimização via Supabase Storage
Todas as imagens do catálogo são carregadas simultaneamente, aumentando o tempo de carregamento inicial da página de produtos.

As imagens de produtos já estão armazenadas no **Supabase Storage**, que é a mesma infraestrutura que serve toda a aplicação. Isso abre algumas oportunidades de otimização que valem explorar:

**Transformações de imagem via Supabase (Image Transformation API)**
O Supabase Storage suporta redimensionamento e conversão de formato na URL, sem precisar armazenar múltiplas versões do arquivo:

```
# Original
https://<projeto>.supabase.co/storage/v1/object/public/produtos/img.jpg

# Thumbnail 200x200 em WebP
https://<projeto>.supabase.co/storage/v1/render/image/public/produtos/img.jpg?width=200&height=200&format=webp

# Listagem (400px de largura)
https://<projeto>.supabase.co/storage/v1/render/image/public/produtos/img.jpg?width=400&format=webp

# Detalhe do produto (800px)
https://<projeto>.supabase.co/storage/v1/render/image/public/produtos/img.jpg?width=800&format=webp
```

Isso permite servir imagens no tamanho exato de cada contexto (thumbnail, listagem, detalhe) sem overhead de download.

**Ações recomendadas:**
1. Substituir as URLs de imagem pelos endpoints de transformação com `format=webp` e `width` adequado ao contexto
2. Adicionar `loading="lazy"` em todas as `<img>` do catálogo
3. Usar `srcset` para servir resoluções diferentes conforme a densidade de tela do dispositivo

```html
<img
  src="...?width=400&format=webp"
  srcset="...?width=400&format=webp 1x, ...?width=800&format=webp 2x"
  alt="Nome do produto"
  loading="lazy"
/>
```

> **Nota back-end:** Confirmar se o plano atual do Supabase inclui Image Transformations (disponível no plano Pro). Se não estiver ativo, a URL de transformação retorna a imagem original sem erro — é seguro implementar desde já.

---

### 15. Mensagens de erro técnicas exibidas ao usuário
Em alguns pontos, erros de API retornam mensagens técnicas diretamente na interface (ex: mensagens de banco, status HTTP crus).

**Ação:** Tratar os erros nas funções JS/PHP e exibir mensagens amigáveis e orientadas à ação. Ex: em vez de _"Error 500: Internal Server Error"_, exibir _"Não foi possível processar sua solicitação. Tente novamente em instantes."_

---

## Itens que exigem alinhamento com o back-end

Os itens abaixo têm impacto nos dois lados do projeto e precisam ser resolvidos de forma coordenada:

| Item | Descrição | Responsável principal |
|------|-----------|----------------------|
| Status `aguardando_codparc` | Pedidos pagos cujo cliente ainda não foi integrado ao ERP ficam presos sem feedback ao usuário | Back-end define o status; front-end exibe |
| Histórico de status do pedido | Tabela de auditoria de transições de pedido | Decisão conjunta de schema |
| Verificação de estoque no checkout | Consulta de disponibilidade real antes de fechar o pedido | Front chama API; back garante dado atualizado |
| Mapeamento Mercado Pago → ERP | Pedidos pagos via MP não chegam ao Sankhya (falta `codTipVenda`) | Back-end; front não precisa mudar |

---

## Resumo

| Prioridade | Quantidade | Itens principais |
|------------|-----------|-----------------|
| 🔴 Crítico | 4 | Debug em produção, webhook MP sem validação, pedido antes do pagamento, sem CSRF |
| 🟠 Alto | 4 | Carrinho volátil, sem verificação de estoque, sem timeout no CEP, frete sem feedback |
| 🟡 Médio | 5 | Race condition carrinho, hint CEP, rate limiting, spinner sem timeout, auditoria de pedido |
| 🟢 UX | 2 | Lazy loading, mensagens de erro amigáveis |



---

