# Spark Eletrônica — Setup Guide

## Requisitos
- PHP 7.4+ com cURL habilitado
- Servidor web: Apache (com mod_rewrite) ou Nginx
- Acesso à internet (para Supabase + ViaCEP)

## Como rodar localmente

### Opção 1 — PHP Built-in Server (mais simples)
```bash
cd C:\util\P4
php -S localhost:8080
```
Acesse: http://localhost:8080

### Opção 2 — XAMPP / WAMP
Copie a pasta `P4/` para `htdocs/spark/`
Acesse: http://localhost/spark/

### Opção 3 — Laragon (com domínio local apontando ao alvo de produção)
Coloque a pasta em `laragon/www/usinashop/` e adicione ao `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 usinashop.test
```
Acesse: http://usinashop.test/

> O alvo de produção é **https://usinashop.com.br/** — `APP_BASE_URL` no `.env`
> já está configurado para esse domínio, portanto webhooks do Mercado Pago e
> URLs de retorno são geradas corretamente quando o site é publicado.

## Estrutura de Arquivos
```
P4/
├── index.php          → Página inicial
├── products.php       → Listagem de produtos
├── product.php        → Detalhe do produto
├── checkout.php       → Finalizar compra
├── login.php          → Login / Cadastro
├── conta.php          → Minha Conta
├── admin/
│   ├── index.php      → Redireciona para clientes
│   ├── clientes.php   → Gestão de clientes
│   └── produtos.php   → Gestão de produtos
├── api/
│   └── frete.php      → Cálculo de frete
├── includes/
│   ├── config.php     → Configuração Supabase + helpers
│   ├── head.php       → <head> global
│   ├── header.php     → Header desktop
│   ├── footer.php     → Rodapé
│   ├── cart-sidebar.php   → Carrinho lateral
│   ├── search-sidebar.php → Busca lateral
│   ├── mobile-bar.php     → Nav mobile
│   └── product-card.php   → Card de produto
├── assets/
│   ├── css/style.css  → Todos os estilos
│   └── js/main.js     → JavaScript (auth, cart, UI)
└── logo/
    └── logo.png       → Logo da loja
```

## Logo
Coloque o arquivo `logo.png` em `/logo/logo.png`

## Variáveis de Ambiente
As chaves Supabase já estão configuradas em `includes/config.php`.
Para o frete via API dos Correios, configure a variável de ambiente:
```
CORREIOS_API_TOKEN=seu_token_aqui
```
Sem o token, o sistema usa cálculo simulado automaticamente.

## Banco de Dados (Supabase)
Tabelas necessárias (já existentes no projeto):
- `produto` — Produtos
- `preco` — Preços
- `estoque` — Estoque
- `produto_imagem` — Imagens dos produtos
- `categoria` — Categorias
- `especificacao` — Especificações técnicas
- `cliente` — Clientes (espelha auth.users)
- `endereco` — Endereços de entrega

## Funcionalidades Implementadas
- ✅ Página inicial com banner carousel + seções de produtos
- ✅ Listagem com filtro por categoria (nível 1 e 2)
- ✅ Detalhe do produto com galeria, calculadora de frete, especificações
- ✅ Carrinho (localStorage) com sidebar
- ✅ Busca de produtos
- ✅ Login / Cadastro via Supabase Auth
- ✅ Minha Conta (perfil + endereços)
- ✅ Checkout com seleção/cadastro de endereço + ViaCEP
- ✅ Painel Admin: gestão de clientes e produtos
- ✅ Design system fiel ao original (amarelo/preto)
- ✅ Mobile responsive com bottom nav bar
