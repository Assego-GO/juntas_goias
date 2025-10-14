<![CDATA[# Juntas Goiás

Este projeto consiste em uma landing page, um painel administrativo e um backend PHP para gerenciar demandas e serviços.

## Estrutura do Projeto

```
juntas-goias/
├── frontend/
│   ├── index.html              # Landing Page
│   ├── admin.html              # Painel Administrativo
│   ├── banner1.jpg             # Banners
│   └── banner2.jpg             # Banners
├── backend/
│   ├── db_connect.php          # Script de conexão com o MySQL
│   ├── demandas_api.php        # Script PHP para receber o formulário (API)
│   ├── admin_api.php           # Script PHP para o Painel Admin (buscar demandas e serviços)
│   └── init_db.sql             # Script SQL para criar as tabelas no MySQL
└── README.md
```

## Configuração do Banco de Dados

1.  **Crie o banco de dados e as tabelas:**
    Execute o script `backend/init_db.sql` no seu servidor MySQL. Você pode fazer isso via linha de comando:
    ```bash
    mysql -u seu_usuario -p < backend/init_db.sql
    ```
    (Substitua `seu_usuario` pelo seu usuário do MySQL e digite sua senha quando solicitado).

2.  **Configure a conexão com o banco de dados:**
    Edite o arquivo `backend/db_connect.php` com suas credenciais do MySQL:
    ```php
    <?php
    $servername = "localhost";
    $username = "root"; // Altere para o seu usuário do MySQL
    $password = "";     // Altere para a sua senha do MySQL
    $dbname = "juntas_goias";
    // ...
    ?>
    ```

## Como Rodar o Projeto

1.  Certifique-se de ter um servidor web (como Apache ou Nginx) com PHP e MySQL configurados.
2.  Coloque a pasta `juntas-goias` no diretório raiz do seu servidor web (ex: `htdocs` para Apache, `www` para Nginx).
3.  Acesse a landing page em `http://localhost/juntas-goias/frontend/index.html` (ou o caminho correspondente no seu servidor).
4.  Acesse o painel administrativo em `http://localhost/juntas-goias/frontend/admin.html`.

## Funcionalidades

*   **Landing Page (`frontend/index.html`):** Exibe informações sobre o projeto e permite que os usuários enviem demandas através de um formulário.
*   **Painel Administrativo (`frontend/admin.html`):** Permite visualizar as demandas recebidas e os serviços cadastrados. Os dados são carregados via AJAX do backend.
*   **API de Demandas (`backend/demandas_api.php`):** Recebe os dados do formulário da landing page e os salva no banco de dados.
*   **API Administrativa (`backend/admin_api.php`):** Fornece endpoints para buscar as demandas e os serviços do banco de dados para exibição no painel administrativo.
]]>
